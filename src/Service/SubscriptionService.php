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
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Uid\Ulid;
use Throwable;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPaymentRepository $subscriptionPaymentRepository,
        private readonly PayFastService $payFastService,
        private readonly TokenCipher $tokenCipher,
        private readonly UserLogService $userLogService,
        private readonly EntityManagerInterface $entityManager,
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
    public function subscribe(User $user, Ulid $planPublicId, Ulid $paymentMethodPublicId): Subscription
    {
        $plan = $this->subscriptionPlanRepository->findOneByPublicId($planPublicId);
        if (is_null($plan) || !$plan->isActive()) {
            throw SubscriptionException::planNotFound();
        }

        $paymentMethod = $this->paymentMethodRepository->findOneByPublicIdAndUser($paymentMethodPublicId, $user);
        if (is_null($paymentMethod)) {
            throw SubscriptionException::paymentMethodNotFound();
        }

        if (!is_null($this->subscriptionRepository->findActiveByUser($user))) {
            throw SubscriptionException::alreadySubscribed();
        }

        $token = $this->tokenCipher->decrypt($paymentMethod->getToken());
        if (is_null($token)) {
            throw SubscriptionException::chargeFailed();
        }

        $charge = $this->payFastService->chargeToken($token, $plan->getPrice()->getAmountCents(), $plan->getName());
        if (($charge['status'] ?? '') !== 'success') {
            throw SubscriptionException::chargeFailed();
        }

        $periodStart = new DateTime();
        $periodEnd = (clone $periodStart)->modify($plan->getInterval()->modifier());

        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setPaymentMethod($paymentMethod);
        $subscription->setStatus(SubscriptionStatus::Active);
        $subscription->setCurrentPeriodStart($periodStart);
        $subscription->setCurrentPeriodEnd($periodEnd);

        $payment = new SubscriptionPayment();
        $payment->setSubscription($subscription);
        $payment->setAmount(new Money($plan->getPrice()->getAmountCents(), $plan->getPrice()->getCurrency()));
        $payment->setStatus(SubscriptionPaymentStatus::Paid);
        $payment->setPeriodStart($periodStart);
        $payment->setPeriodEnd($periodEnd);
        $payment->setPfPaymentId($charge['pf_payment_id'] ?? null);
        $payment->setGatewayResponse('success');

        $this->entityManager->beginTransaction();
        try {
            $this->subscriptionRepository->save($subscription);
            $this->subscriptionPaymentRepository->save($payment);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        $this->userLogService->log(
            UserLogType::SUBSCRIPTION_CREATED,
            'Subscription created',
            $user->getEmail(),
            context: ['subscription' => (string) $subscription->getPublicId(), 'plan' => $plan->getSlug()],
        );

        return $subscription;
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
            $this->processRenewal($subscription);
            $processed++;
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
                $succeeded = ($charge['status'] ?? '') === 'success';
                $pfPaymentId = $charge['pf_payment_id'] ?? null;
            } catch (Throwable) {
                $succeeded = false;
            }
        }

        if ($succeeded) {
            $subscription->setStatus(SubscriptionStatus::Active);
            $subscription->setCurrentPeriodStart($periodStart);
            $subscription->setCurrentPeriodEnd($periodEnd);
            $payment->setStatus(SubscriptionPaymentStatus::Paid);
            $payment->setPfPaymentId($pfPaymentId);
            $payment->setGatewayResponse('success');
        } else {
            $subscription->setStatus(SubscriptionStatus::PastDue);
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
