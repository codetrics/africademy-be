<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Entity\UserLogType;
use App\Enum\PaymentMethodStatus;
use App\Enum\PayfastWebhookOutcome;
use App\Exceptions\PaymentMethodException;
use App\Repository\PaymentMethodRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Uid\Ulid;

class PaymentMethodService
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly UserRepository $userRepository,
        private readonly PayFastService $payFastService,
        private readonly TokenCipher $tokenCipher,
        private readonly UserLogService $userLogService,
        private readonly PayfastWebhookRecorder $payfastWebhookRecorder,
        #[Target('payfast')]
        private readonly LoggerInterface $payfastLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{url: string, fields: array<string, string>}
     */
    public function createSetupCheckout(User $user): array
    {
        return $this->payFastService->buildTokenizationCheckout($user, (string) new Ulid());
    }

    /**
     * Handles a tokenization ITN. Returns true when the ITN is a payment-method
     * setup (handled or rejected), false when it is not one (try other handlers).
     *
     * @param array<string, mixed> $data
     */
    public function handleTokenizationItn(array $data): bool
    {
        // Validate the signature BEFORE claiming the ITN: an invalid signature
        // must not be swallowed as "handled" (which would drop a real order ITN).
        if (!$this->payFastService->validateItn($data)) {
            return false;
        }

        // Valid signature but not a tokenization ITN — defer to the order handler.
        if ((string) ($data['custom_str1'] ?? '') !== PayFastService::TOKENIZATION_MARKER) {
            return false;
        }

        $this->payfastWebhookRecorder->record($data, PayfastWebhookOutcome::Tokenization);
        $this->payfastLogger->info('PayFast ITN processed', [
            'm_payment_id' => (string) ($data['m_payment_id'] ?? ''),
            'pf_payment_id' => (string) ($data['pf_payment_id'] ?? ''),
            'outcome' => PayfastWebhookOutcome::Tokenization->value,
        ]);

        $token = (string) ($data['token'] ?? '');
        $userPublicId = (string) ($data['custom_str2'] ?? '');

        if ($token === '' || !Ulid::isValid($userPublicId)) {
            return true;
        }

        $user = $this->userRepository->findOneByPublicId(Ulid::fromString($userPublicId));
        if ($user instanceof User) {
            $this->store(
                $user,
                $token,
                (string) ($data['brand'] ?? 'card'),
                (string) ($data['card_last_four'] ?? '0000'),
                isset($data['exp_month']) ? (string) $data['exp_month'] : null,
                isset($data['exp_year']) ? (string) $data['exp_year'] : null,
            );
        }

        return true;
    }

    public function store(User $user, string $token, string $brand, string $last4, ?string $expMonth, ?string $expYear): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setUser($user);
        $paymentMethod->setToken($this->tokenCipher->encrypt($token));
        $paymentMethod->setBrand($brand);
        $paymentMethod->setLast4($last4);
        $paymentMethod->setExpMonth($expMonth);
        $paymentMethod->setExpYear($expYear);
        $paymentMethod->setIsDefault($this->paymentMethodRepository->countActiveByUser($user) === 0);

        $this->paymentMethodRepository->save($paymentMethod, true);

        $this->userLogService->log(
            UserLogType::PAYMENT_METHOD_ADDED,
            'Payment method added',
            $user->getEmail(),
            context: ['payment_method' => (string) $paymentMethod->getPublicId()],
        );

        return $paymentMethod;
    }

    /**
     * @return PaymentMethod[]
     */
    public function list(User $user): array
    {
        return $this->paymentMethodRepository->findActiveByUser($user);
    }

    /**
     * @throws PaymentMethodException
     */
    public function setDefault(User $user, Ulid $publicId): PaymentMethod
    {
        $paymentMethod = $this->resolve($user, $publicId);

        // Clear the old default and set the new one atomically so a failure can't
        // leave the user with zero or two default cards.
        $this->entityManager->beginTransaction();
        try {
            foreach ($this->paymentMethodRepository->findActiveByUser($user) as $existing) {
                if ($existing->isDefault() && $existing->getId() !== $paymentMethod->getId()) {
                    $existing->setIsDefault(false);
                    $this->paymentMethodRepository->save($existing);
                }
            }

            $paymentMethod->setIsDefault(true);
            $this->paymentMethodRepository->save($paymentMethod);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->userLogService->log(
            UserLogType::PAYMENT_METHOD_DEFAULT,
            'Default payment method set',
            $user->getEmail(),
            context: ['payment_method' => (string) $paymentMethod->getPublicId()],
        );

        return $paymentMethod;
    }

    /**
     * @throws PaymentMethodException
     */
    public function delete(User $user, Ulid $publicId): void
    {
        $paymentMethod = $this->resolve($user, $publicId);
        $wasDefault = $paymentMethod->isDefault();

        // Snapshot the other active cards before deactivating this one (the query
        // is status-based, so it must run before the card is flushed as deleted).
        $others = array_values(array_filter(
            $this->paymentMethodRepository->findActiveByUser($user),
            static fn (PaymentMethod $candidate): bool => $candidate->getId() !== $paymentMethod->getId(),
        ));

        // Deactivate the card and promote a replacement default atomically.
        $this->entityManager->beginTransaction();
        try {
            $paymentMethod->setStatus(PaymentMethodStatus::Deleted);
            $paymentMethod->setIsDefault(false);
            $this->paymentMethodRepository->save($paymentMethod);

            // Promote another card to default when the default was removed.
            if ($wasDefault && $others !== []) {
                $others[0]->setIsDefault(true);
                $this->paymentMethodRepository->save($others[0]);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->userLogService->log(
            UserLogType::PAYMENT_METHOD_REMOVED,
            'Payment method removed',
            $user->getEmail(),
            context: ['payment_method' => (string) $paymentMethod->getPublicId()],
        );
    }

    /**
     * @throws PaymentMethodException
     */
    private function resolve(User $user, Ulid $publicId): PaymentMethod
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByPublicIdAndUser($publicId, $user);

        if (!$paymentMethod instanceof PaymentMethod) {
            throw PaymentMethodException::notFound();
        }

        return $paymentMethod;
    }
}
