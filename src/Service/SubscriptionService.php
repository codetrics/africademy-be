<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Money;
use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Entity\UserLogType;
use App\Enum\SubscriptionPaymentStatus;
use App\Enum\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Repository\PaymentMethodRepository;
use App\Repository\SubscriptionPaymentRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;
use Throwable;

class SubscriptionService
{
    private const int MAX_FAILED_ATTEMPTS = 3;

    public function __construct(
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPaymentRepository $subscriptionPaymentRepository,
        private readonly PayFastService $payFastService,
        private readonly TokenCipher $tokenCipher,
        private readonly CouponService $couponService,
        private readonly UserLogService $userLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return \App\Entity\SubscriptionPlan[]
     */
    public function listPlans(): array
    {
        return $this->subscriptionPlanRepository->findActive();
    }

    public function getCurrent(User $user): ?Subscription
    {
        return $this->subscriptionRepository->findCurrentByUser($user);
    }

    /**
     * Subscribes the user to a plan, charging the chosen card immediately and
     * starting the first period now.
     *
     * @throws SubscriptionException
     */
    public function subscribe(User $user, Ulid $planPublicId, Ulid $paymentMethodPublicId, ?string $couponCode = null): Subscription
    {
        $plan = $this->subscriptionPlanRepository->findOneByPublicId($planPublicId);
        if (is_null($plan) || !$plan->isActive()) {
            throw SubscriptionException::planNotFound();
        }

        $paymentMethod = $this->paymentMethodRepository->findOneByPublicIdAndUser($paymentMethodPublicId, $user);
        if (is_null($paymentMethod)) {
            throw SubscriptionException::paymentMethodNotFound();
        }

        $token = $this->tokenCipher->decrypt($paymentMethod->getToken());
        if (is_null($token)) {
            throw SubscriptionException::chargeFailed();
        }

        // Coupons discount only the first charge.
        $fullAmount = $plan->getPrice()->getAmountCents();
        $coupon = is_null($couponCode) || trim($couponCode) === ''
            ? null
            : $this->couponService->validate($couponCode, $user, $fullAmount);
        $discount = is_null($coupon) ? 0 : $this->couponService->computeDiscount($coupon, $fullAmount);
        $chargeAmount = $fullAmount - $discount;

        $periodStart = new DateTime();
        $periodEnd = (clone $periodStart)->modify($plan->getInterval()->modifier());

        $this->entityManager->beginTransaction();
        try {
            // Lock the user row so concurrent subscribe() calls for the same user
            // serialise — there is no DB "one active subscription" constraint.
            $this->entityManager->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!is_null($this->subscriptionRepository->findActiveByUser($user))) {
                throw SubscriptionException::alreadySubscribed();
            }

            $charge = $this->payFastService->chargeToken($token, $chargeAmount, $plan->getName());
            if ($charge['status'] !== 'success') {
                throw SubscriptionException::chargeFailed();
            }

            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($plan);
            $subscription->setPaymentMethod($paymentMethod);
            $subscription->setStatus(SubscriptionStatus::Active);
            $subscription->setCurrentPeriodStart($periodStart);
            $subscription->setCurrentPeriodEnd($periodEnd);

            $payment = new SubscriptionPayment();
            $payment->setSubscription($subscription);
            $payment->setAmount(new Money($chargeAmount, $plan->getPrice()->getCurrency()));
            $payment->setStatus(SubscriptionPaymentStatus::Paid);
            $payment->setPeriodStart($periodStart);
            $payment->setPeriodEnd($periodEnd);
            $payment->setPfPaymentId($charge['pf_payment_id'] ?? null);
            $payment->setGatewayResponse('success');

            try {
                $this->subscriptionRepository->save($subscription);
                $this->subscriptionPaymentRepository->save($payment);
                if (!is_null($coupon)) {
                    $this->couponService->redeem($coupon, $user, $discount, null, $subscription);
                }
                $this->entityManager->flush();
                $this->entityManager->commit();
            } catch (Exception $persistException) {
                $this->entityManager->rollback();

                // Charged but couldn't persist: compensate so the user isn't billed
                // for a subscription that was never recorded.
                $pfPaymentId = $charge['pf_payment_id'] ?? null;
                if (!is_null($pfPaymentId)) {
                    $this->payFastService->refund($pfPaymentId, $chargeAmount);
                    $this->logger->critical('Subscription persistence failed after a successful charge; issued a compensating refund.', [
                        'user' => $user->getEmail(),
                        'pf_payment_id' => $pfPaymentId,
                        'error' => $persistException->getMessage(),
                    ]);
                }

                throw $persistException;
            }

            $this->userLogService->log(
                UserLogType::SUBSCRIPTION_CREATED,
                'Subscription created',
                $user->getEmail(),
                context: ['subscription' => (string) $subscription->getPublicId(), 'plan' => $plan->getSlug()],
            );

            return $subscription;
        } catch (Exception $exception) {
            // Unwind the outer transaction (user lock / check / charge) on any
            // failure not already handled by the inner persist block.
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }
    }

    /**
     * @throws SubscriptionException
     */
    public function getUserSubscription(User $user, Ulid $publicId): Subscription
    {
        $subscription = $this->subscriptionRepository->findOneByPublicIdAndUser($publicId, $user);

        if (is_null($subscription)) {
            throw SubscriptionException::subscriptionNotFound();
        }

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->setCancelAtPeriodEnd(true);
        $this->subscriptionRepository->save($subscription, true);

        $this->userLogService->log(
            UserLogType::SUBSCRIPTION_CANCELLED,
            'Subscription cancelled',
            $subscription->getUser()->getEmail(),
            context: ['subscription' => (string) $subscription->getPublicId()],
        );

        return $subscription;
    }

    /**
     * Renews (or expires) all subscriptions whose period has ended. Returns the
     * number processed. Intended to be run on a schedule.
     */
    public function renewDueSubscriptions(): int
    {
        $processed = 0;

        foreach ($this->subscriptionRepository->findDueForRenewal(new DateTime()) as $subscription) {
            try {
                $this->processRenewal($subscription);
                $processed++;
            } catch (Throwable $exception) {
                // Isolate failures so one bad subscription doesn't halt the batch.
                $this->logger->error('Subscription renewal failed', [
                    'subscription' => (string) $subscription->getPublicId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    private function processRenewal(Subscription $subscription): void
    {
        if ($subscription->isCancelAtPeriodEnd()) {
            $subscription->setStatus(SubscriptionStatus::Expired);
            $this->subscriptionRepository->save($subscription, true);

            return;
        }

        $plan = $subscription->getPlan();
        $periodStart = clone $subscription->getCurrentPeriodEnd();
        $periodEnd = (clone $periodStart)->modify($plan->getInterval()->modifier());

        // Idempotency: if a successful payment already covers this period (e.g. the
        // subscription was picked up twice), advance without charging again.
        if ($this->subscriptionPaymentRepository->existsPaidForPeriod($subscription, $periodStart)) {
            $subscription->setStatus(SubscriptionStatus::Active);
            $subscription->setCurrentPeriodStart($periodStart);
            $subscription->setCurrentPeriodEnd($periodEnd);
            $subscription->setFailedAttempts(0);
            $this->subscriptionRepository->save($subscription, true);

            return;
        }

        $payment = new SubscriptionPayment();
        $payment->setSubscription($subscription);
        $payment->setAmount(new Money($plan->getPrice()->getAmountCents(), $plan->getPrice()->getCurrency()));
        $payment->setPeriodStart($periodStart);
        $payment->setPeriodEnd($periodEnd);

        $token = $this->tokenCipher->decrypt($subscription->getPaymentMethod()->getToken());
        $succeeded = false;
        $pfPaymentId = null;

        if (!is_null($token)) {
            try {
                $charge = $this->payFastService->chargeToken($token, $plan->getPrice()->getAmountCents(), $plan->getName());
                $succeeded = $charge['status'] === 'success';
                $pfPaymentId = $charge['pf_payment_id'] ?? null;
            } catch (Throwable) {
                $succeeded = false;
            }
        }

        if ($succeeded) {
            $subscription->setStatus(SubscriptionStatus::Active);
            $subscription->setCurrentPeriodStart($periodStart);
            $subscription->setCurrentPeriodEnd($periodEnd);
            $subscription->setFailedAttempts(0);
            $payment->setStatus(SubscriptionPaymentStatus::Paid);
            $payment->setPfPaymentId($pfPaymentId);
            $payment->setGatewayResponse('success');
        } else {
            // Leave the period unchanged so the subscription stays "due" and is
            // retried on the next run; expire it once the retry budget is spent.
            $attempts = $subscription->getFailedAttempts() + 1;
            $subscription->setFailedAttempts($attempts);
            $subscription->setStatus($attempts >= self::MAX_FAILED_ATTEMPTS ? SubscriptionStatus::Expired : SubscriptionStatus::PastDue);
            $payment->setStatus(SubscriptionPaymentStatus::Failed);
            $payment->setGatewayResponse('failed');
        }

        $this->entityManager->beginTransaction();
        try {
            $this->subscriptionRepository->save($subscription);
            $this->subscriptionPaymentRepository->save($payment);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();

            // Charged but couldn't persist: refund so the customer isn't billed for
            // a period that was never recorded (and won't be re-charged next run).
            if ($succeeded && !is_null($pfPaymentId)) {
                $this->payFastService->refund((string) $pfPaymentId, $plan->getPrice()->getAmountCents());
                $this->logger->critical('Subscription renewal persistence failed after a successful charge; issued a compensating refund.', [
                    'subscription' => (string) $subscription->getPublicId(),
                    'pf_payment_id' => $pfPaymentId,
                    'error' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }

        $this->userLogService->log(
            $succeeded ? UserLogType::SUBSCRIPTION_RENEWED : UserLogType::SUBSCRIPTION_PAYMENT_FAILED,
            $succeeded ? 'Subscription renewed' : 'Subscription payment failed',
            $subscription->getUser()->getEmail(),
            context: ['subscription' => (string) $subscription->getPublicId()],
        );
    }
}
