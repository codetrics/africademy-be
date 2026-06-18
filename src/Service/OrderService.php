<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bundle;
use App\Entity\Money;
use App\Entity\Order;
use App\Entity\RefundRequest;
use App\Entity\User;
use App\Entity\UserLogType;
use App\Enum\BundleStatus;
use App\Enum\CourseStatus;
use App\Enum\EntitlementSource;
use App\Enum\OrderStatus;
use App\Exceptions\OrderException;
use App\Repository\BundleRepository;
use App\Repository\CourseRepository;
use App\Repository\OrderRepository;
use App\Repository\RefundRequestRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\Uid\Ulid;

class OrderService
{
    public const int REFUND_WINDOW_DAYS = 30;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CourseRepository $courseRepository,
        private readonly BundleRepository $bundleRepository,
        private readonly RefundRequestRepository $refundRequestRepository,
        private readonly AccessService $accessService,
        private readonly EnrollmentService $enrollmentService,
        private readonly PayFastService $payFastService,
        private readonly CouponService $couponService,
        private readonly UserLogService $userLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Creates a pending order for a one-off bundle purchase.
     *
     * @throws OrderException
     */
    public function createBundlePurchase(User $user, Ulid $bundlePublicId, ?string $couponCode = null): Order
    {
        $bundle = $this->bundleRepository->findOneByPublicId($bundlePublicId);

        if (is_null($bundle)) {
            throw OrderException::bundleNotFound();
        }

        if ($bundle->getStatus() !== BundleStatus::Published || $bundle->getPrice()->getAmountCents() <= 0 || $bundle->getCourses()->isEmpty()) {
            throw OrderException::bundleNotPurchasable();
        }

        if ($this->ownsEntireBundle($user, $bundle)) {
            throw OrderException::bundleAlreadyOwned();
        }

        $order = new Order();
        $order->setUser($user);
        $order->setBundle($bundle);
        $this->applyCoupon($order, $user, $bundle->getPrice(), $couponCode);

        $this->orderRepository->save($order, true);

        return $order;
    }

    /**
     * @throws \App\Exceptions\CouponException
     */
    private function applyCoupon(Order $order, User $user, Money $price, ?string $couponCode): void
    {
        $original = $price->getAmountCents();

        if (is_null($couponCode) || trim($couponCode) === '') {
            $order->setAmount(new Money($original, $price->getCurrency()));

            return;
        }

        $coupon = $this->couponService->validate($couponCode, $user, $original);
        $discount = $this->couponService->computeDiscount($coupon, $original);

        $order->setCoupon($coupon);
        $order->setDiscountAmountCents($discount);
        $order->setAmount(new Money($original - $discount, $price->getCurrency()));
    }

    private function ownsEntireBundle(User $user, Bundle $bundle): bool
    {
        foreach ($bundle->getCourses() as $course) {
            if (!$this->accessService->hasAccess($user, $course)) {
                return false;
            }
        }

        return !$bundle->getCourses()->isEmpty();
    }

    /**
     * Records a (pending) refund request for a paid order within the refund
     * window. Approval and the actual PayFast refund are handled by admin later.
     *
     * @throws OrderException
     */
    public function requestRefund(User $user, Ulid $orderPublicId, ?string $reason): RefundRequest
    {
        $order = $this->getUserOrder($user, $orderPublicId);

        if (!$order->isPaid() || is_null($order->getPaidAt())) {
            throw OrderException::notRefundable();
        }

        $deadline = (clone $order->getPaidAt())->modify(sprintf('+%d days', self::REFUND_WINDOW_DAYS));
        if (new DateTime() > $deadline) {
            throw OrderException::notRefundable();
        }

        if (!is_null($this->refundRequestRepository->findOneByOrder($order))) {
            throw OrderException::refundAlreadyRequested();
        }

        $refundRequest = new RefundRequest();
        $refundRequest->setOrder($order);
        $refundRequest->setUser($user);
        $refundRequest->setReason($reason);
        $this->refundRequestRepository->save($refundRequest, true);

        $this->userLogService->log(
            UserLogType::REFUND_REQUESTED,
            'Refund requested',
            $user->getEmail(),
            context: ['order' => (string) $order->getPublicId()],
        );

        return $refundRequest;
    }

    /**
     * Creates a pending order for a one-off course purchase.
     *
     * @throws OrderException
     */
    public function createCoursePurchase(User $user, Ulid $coursePublicId, ?string $couponCode = null): Order
    {
        $course = $this->courseRepository->findOneByPublicId($coursePublicId);

        if (is_null($course)) {
            throw OrderException::courseNotFound();
        }

        if (
            !$course->isPurchasable()
            || $course->isFree()
            || $course->getStatus() !== CourseStatus::Published
            || $course->getPrice()->getAmountCents() <= 0
        ) {
            throw OrderException::courseNotPurchasable();
        }

        if ($this->accessService->hasAccess($user, $course)) {
            throw OrderException::alreadyOwned();
        }

        $order = new Order();
        $order->setUser($user);
        $order->setCourse($course);
        $this->applyCoupon($order, $user, $course->getPrice(), $couponCode);

        $this->orderRepository->save($order, true);

        return $order;
    }

    /**
     * @throws OrderException
     */
    public function getUserOrder(User $user, Ulid $publicId): Order
    {
        $order = $this->orderRepository->findOneByPublicIdAndUser($publicId, $user);

        if (is_null($order)) {
            throw OrderException::orderNotFound();
        }

        return $order;
    }

    public function createUserOrdersQueryBuilder(User $user): QueryBuilder
    {
        return $this->orderRepository->createUserOrdersQueryBuilder($user);
    }

    /**
     * Processes a PayFast ITN: validates the signature, matches the order and
     * amount, and on a COMPLETE payment marks it paid, grants the entitlement
     * and enrolls the student. Returns true when the ITN was accepted.
     *
     * @param array<string, mixed> $data
     */
    public function handlePayFastItn(array $data): bool
    {
        if (!$this->payFastService->validateItn($data)) {
            return false;
        }

        $merchantPaymentId = (string) ($data['m_payment_id'] ?? '');
        if (!Ulid::isValid($merchantPaymentId)) {
            return false;
        }

        $order = $this->orderRepository->findOneByPublicId(Ulid::fromString($merchantPaymentId));
        if (is_null($order)) {
            return false;
        }

        if ((string) ($data['payment_status'] ?? '') !== 'COMPLETE') {
            return false;
        }

        $expectedAmount = $this->payFastService->formatAmount($order->getAmount()->getAmountCents());
        if ((float) ($data['amount_gross'] ?? 0) !== (float) $expectedAmount) {
            return false;
        }

        if ($order->isPaid()) {
            return true;
        }

        $this->completeOrder($order, $data['pf_payment_id'] ?? null);

        return true;
    }

    private function completeOrder(Order $order, ?string $pfPaymentId): void
    {
        $this->entityManager->beginTransaction();
        try {
            $order->setStatus(OrderStatus::Paid);
            $order->setPaidAt(new DateTime());
            $order->setPfPaymentId($pfPaymentId);
            $this->orderRepository->save($order);

            foreach ($this->orderCourses($order) as $course) {
                $this->accessService->grant($order->getUser(), $course, $this->grantSource($order));
                $this->enrollmentService->ensureEnrolled($order->getUser(), $course);
            }

            if (!is_null($order->getCoupon())) {
                $this->couponService->redeem($order->getCoupon(), $order->getUser(), $order->getDiscountAmountCents(), $order);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        $this->userLogService->log(
            UserLogType::PAYMENT_COMPLETED,
            'Purchase completed',
            $order->getUser()->getEmail(),
            context: ['order' => (string) $order->getPublicId()],
        );
    }

    /**
     * @return iterable<\App\Entity\Course>
     */
    private function orderCourses(Order $order): iterable
    {
        if (!is_null($order->getBundle())) {
            return $order->getBundle()->getCourses();
        }

        return is_null($order->getCourse()) ? [] : [$order->getCourse()];
    }

    private function grantSource(Order $order): EntitlementSource
    {
        return is_null($order->getBundle()) ? EntitlementSource::CoursePurchase : EntitlementSource::BundlePurchase;
    }
}
