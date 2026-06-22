<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Entitlement;
use App\Entity\RefundRequest;
use App\Entity\UserLogType;
use App\Enum\OrderStatus;
use App\Enum\RefundStatus;
use App\Exceptions\OrderException;
use App\Repository\EntitlementRepository;
use App\Repository\OrderRepository;
use App\Repository\RefundRequestRepository;
use DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\Uid\Ulid;

class RefundService
{
    public function __construct(
        private readonly RefundRequestRepository $refundRequestRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EntitlementRepository $entitlementRepository,
        private readonly AccessService $accessService,
        private readonly PayFastService $payFastService,
        private readonly UserLogService $userLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createPendingQueryBuilder(): QueryBuilder
    {
        return $this->refundRequestRepository->createPendingQueryBuilder();
    }

    /**
     * @throws OrderException
     */
    public function getRefundRequest(Ulid $publicId): RefundRequest
    {
        $refundRequest = $this->refundRequestRepository->findOneByPublicId($publicId);

        if (is_null($refundRequest)) {
            throw OrderException::refundRequestNotFound();
        }

        return $refundRequest;
    }

    /**
     * Approves a refund: revokes the order's entitlements, marks the order
     * refunded and issues the gateway refund.
     *
     * @throws OrderException
     */
    public function approve(RefundRequest $refundRequest): RefundRequest
    {
        $this->assertPending($refundRequest);

        $this->entityManager->beginTransaction();
        try {
            // Lock the request to serialise concurrent approvals and re-check it
            // is still pending under the lock — guards double-refund.
            $refundRequest = $this->entityManager->find(RefundRequest::class, $refundRequest->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$refundRequest instanceof RefundRequest || $refundRequest->getStatus() !== RefundStatus::Pending) {
                $this->entityManager->commit();
                throw OrderException::refundNotActionable();
            }

            $order = $refundRequest->getOrder();

            // A paid order must carry a gateway payment reference; without one we
            // cannot move money, so never silently mark it refunded.
            if (is_null($order->getPfPaymentId())) {
                $this->entityManager->rollback();
                throw OrderException::refundNotCharged();
            }

            // Issue the gateway refund first; only revoke access once the money is
            // actually returned. A failure leaves everything pending and retriable.
            $result = $this->payFastService->refund($order->getPfPaymentId(), $order->getAmount()->getAmountCents());
            if ($result['status'] !== 'success') {
                $this->entityManager->rollback();
                throw OrderException::refundGatewayFailed();
            }

            foreach ($order->getPurchasedCourses() as $course) {
                $entitlement = $this->entitlementRepository->findOneByUserAndCourse($order->getUser(), $course);
                if ($entitlement instanceof Entitlement) {
                    $this->accessService->revoke($entitlement);
                }
            }

            $order->setStatus(OrderStatus::Refunded);
            $order->setRefundedAt(new DateTime());
            $this->orderRepository->save($order);

            $refundRequest->setStatus(RefundStatus::Approved);
            $refundRequest->setResolvedAt(new DateTime());
            $this->refundRequestRepository->save($refundRequest);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (OrderException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        $order = $refundRequest->getOrder();

        $this->userLogService->log(
            UserLogType::REFUND_APPROVED,
            'Refund approved',
            $order->getUser()->getEmail(),
            context: ['order' => (string) $order->getPublicId()],
        );

        return $refundRequest;
    }

    /**
     * @throws OrderException
     */
    public function reject(RefundRequest $refundRequest): RefundRequest
    {
        $this->assertPending($refundRequest);

        $refundRequest->setStatus(RefundStatus::Rejected);
        $refundRequest->setResolvedAt(new DateTime());
        $this->refundRequestRepository->save($refundRequest, true);

        $this->userLogService->log(
            UserLogType::REFUND_REJECTED,
            'Refund rejected',
            $refundRequest->getOrder()->getUser()->getEmail(),
            context: ['order' => (string) $refundRequest->getOrder()->getPublicId()],
        );

        return $refundRequest;
    }

    /**
     * @throws OrderException
     */
    private function assertPending(RefundRequest $refundRequest): void
    {
        if ($refundRequest->getStatus() !== RefundStatus::Pending) {
            throw OrderException::refundNotActionable();
        }
    }
}
