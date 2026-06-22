<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PayfastWebhookEvent;
use App\Enum\PayfastWebhookOutcome;
use App\Repository\PayfastWebhookEventRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Persists an audit record for a signature-valid PayFast ITN. Callers must only
 * invoke this after the ITN signature has been validated — invalid ITNs are not
 * stored. The recurring `token` is redacted before the payload is persisted.
 */
class PayfastWebhookRecorder
{
    private const string REDACTED = '[redacted]';

    public function __construct(
        private readonly PayfastWebhookEventRepository $payfastWebhookEventRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function record(array $data, PayfastWebhookOutcome $outcome): PayfastWebhookEvent
    {
        $payload = $data;
        if (array_key_exists('token', $payload)) {
            $payload['token'] = self::REDACTED;
        }

        $merchantPaymentId = (string) ($data['m_payment_id'] ?? '');

        $event = new PayfastWebhookEvent();
        $event->setIpAddress($this->requestStack->getCurrentRequest()?->getClientIp());
        $event->setMPaymentId($merchantPaymentId === '' ? null : $merchantPaymentId);
        $event->setPfPaymentId(isset($data['pf_payment_id']) ? (string) $data['pf_payment_id'] : null);
        $event->setPaymentStatus(isset($data['payment_status']) ? (string) $data['payment_status'] : null);
        $event->setAmountGrossCents(isset($data['amount_gross']) ? (int) round(((float) $data['amount_gross']) * 100) : null);
        $event->setOutcome($outcome);
        $event->setPayload($payload);

        $this->payfastWebhookEventRepository->save($event, true);

        return $event;
    }
}
