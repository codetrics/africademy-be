<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PayFast gateway helper: builds the signed checkout payload for a redirect/form
 * submission and validates inbound ITN (Instant Transaction Notification) data.
 *
 * The live server-confirmation post-back to PayFast is intentionally not made
 * here; signature + amount validation is performed by callers.
 */
class PayFastService
{
    private const string SANDBOX_PROCESS_URL = 'https://sandbox.payfast.co.za/eng/process';
    private const string LIVE_PROCESS_URL = 'https://www.payfast.co.za/eng/process';
    private const int ITEM_NAME_MAX_LENGTH = 100;

    public function __construct(
        #[Autowire('%app.payfast.merchant_id%')] private readonly string $merchantId,
        #[Autowire('%app.payfast.merchant_key%')] private readonly string $merchantKey,
        #[Autowire('%app.payfast.passphrase%')] private readonly string $passphrase,
        #[Autowire('%app.payfast.sandbox%')] private readonly bool $sandbox,
        #[Autowire('%app.payfast.return_url%')] private readonly string $returnUrl,
        #[Autowire('%app.payfast.cancel_url%')] private readonly string $cancelUrl,
        #[Autowire('%app.payfast.notify_url%')] private readonly string $notifyUrl,
    ) {
    }

    /**
     * Signed checkout payload the client posts to PayFast.
     *
     * @return array{url: string, fields: array<string, string>}
     */
    public function buildCheckout(Order $order): array
    {
        $fields = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'notify_url' => $this->notifyUrl,
            'm_payment_id' => (string) $order->getPublicId(),
            'amount' => $this->formatAmount($order->getAmount()->getAmountCents()),
            'item_name' => substr($order->getCourse()->getTitle(), 0, self::ITEM_NAME_MAX_LENGTH),
        ];

        $fields['signature'] = $this->generateSignature($fields);

        return [
            'url' => $this->sandbox ? self::SANDBOX_PROCESS_URL : self::LIVE_PROCESS_URL,
            'fields' => $fields,
        ];
    }

    /**
     * Validates the ITN signature against the posted data.
     *
     * @param array<string, mixed> $data
     */
    public function validateItn(array $data): bool
    {
        $signature = (string) ($data['signature'] ?? '');

        if ($signature === '') {
            return false;
        }

        unset($data['signature']);

        return hash_equals($this->generateSignature($data), $signature);
    }

    /**
     * PayFast signature: md5 of the urlencoded "key=value" pairs in order,
     * with the passphrase appended when configured.
     *
     * @param array<string, mixed> $data
     */
    public function generateSignature(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $pairs[] = $key . '=' . urlencode(trim($value));
        }

        $payload = implode('&', $pairs);

        if ($this->passphrase !== '') {
            $payload .= '&passphrase=' . urlencode(trim($this->passphrase));
        }

        return md5($payload);
    }

    public function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
