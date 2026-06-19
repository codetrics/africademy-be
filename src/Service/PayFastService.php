<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use DateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

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
    private const string API_BASE_URL = 'https://api.payfast.co.za';
    private const string API_VERSION = 'v1';
    private const int ITEM_NAME_MAX_LENGTH = 100;
    private const int SETUP_AMOUNT_CENTS = 500;
    public const string TOKENIZATION_MARKER = 'pm_setup';

    public function __construct(
        #[Autowire('%app.payfast.merchant_id%')] private readonly string $merchantId,
        #[Autowire('%app.payfast.merchant_key%')] private readonly string $merchantKey,
        #[Autowire('%app.payfast.passphrase%')] private readonly string $passphrase,
        #[Autowire('%app.payfast.sandbox%')] private readonly bool $sandbox,
        #[Autowire('%app.payfast.return_url%')] private readonly string $returnUrl,
        #[Autowire('%app.payfast.cancel_url%')] private readonly string $cancelUrl,
        #[Autowire('%app.payfast.notify_url%')] private readonly string $notifyUrl,
        private readonly HttpClientInterface $httpClient,
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
            'item_name' => substr($this->orderItemName($order), 0, self::ITEM_NAME_MAX_LENGTH),
        ];

        $fields['signature'] = $this->generateSignature($fields);

        return [
            'url' => $this->sandbox ? self::SANDBOX_PROCESS_URL : self::LIVE_PROCESS_URL,
            'fields' => $fields,
        ];
    }

    /**
     * Tokenization checkout (subscription_type=2): captures a card and returns a
     * reusable token via ITN. Tagged so the webhook routes it to payment methods.
     *
     * @return array{url: string, fields: array<string, string>}
     */
    public function buildTokenizationCheckout(User $user, string $merchantPaymentId): array
    {
        $fields = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'notify_url' => $this->notifyUrl,
            'm_payment_id' => $merchantPaymentId,
            'amount' => $this->formatAmount(self::SETUP_AMOUNT_CENTS),
            'item_name' => 'Card setup',
            'subscription_type' => '2',
            'custom_str1' => self::TOKENIZATION_MARKER,
            'custom_str2' => (string) $user->getPublicId(),
        ];

        $fields['signature'] = $this->generateSignature($fields);

        return [
            'url' => $this->sandbox ? self::SANDBOX_PROCESS_URL : self::LIVE_PROCESS_URL,
            'fields' => $fields,
        ];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    private function orderItemName(Order $order): string
    {
        if (!is_null($order->getBundle())) {
            return $order->getBundle()->getTitle();
        }

        return is_null($order->getCourse()) ? 'Order' : $order->getCourse()->getTitle();
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

    /**
     * Charges a stored token (adhoc recurring charge). In sandbox this returns a
     * simulated success; live calls hit the PayFast API.
     *
     * @return array{status: string, pf_payment_id: ?string}
     */
    public function chargeToken(#[\SensitiveParameter] string $token, int $amountCents, string $itemName): array
    {
        if ($this->sandbox) {
            return ['status' => 'success', 'pf_payment_id' => 'SIM-' . bin2hex(random_bytes(6))];
        }

        $body = [
            'amount' => (string) $amountCents,
            'item_name' => substr($itemName, 0, self::ITEM_NAME_MAX_LENGTH),
        ];

        $response = $this->apiRequest(sprintf('/subscriptions/%s/adhoc', urlencode($token)), $body);

        return [
            'status' => $this->apiStatus($response),
            'pf_payment_id' => isset($response['data']['pf_payment_id']) ? (string) $response['data']['pf_payment_id'] : null,
        ];
    }

    /**
     * Refunds a settled payment. Simulated in sandbox; live calls hit the
     * PayFast API.
     *
     * @return array{status: string}
     */
    public function refund(string $pfPaymentId, int $amountCents): array
    {
        if ($this->sandbox) {
            return ['status' => 'success'];
        }

        $response = $this->apiRequest(sprintf('/refunds/%s', urlencode($pfPaymentId)), [
            'amount' => (string) $amountCents,
            'reason' => 'Order refund',
        ]);

        return ['status' => $this->apiStatus($response)];
    }

    /**
     * Performs a signed POST to the PayFast API. Transport or decoding failures
     * resolve to an empty payload so the caller can treat the call as failed
     * rather than raising.
     *
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function apiRequest(string $path, array $body): array
    {
        $headers = [
            'merchant-id' => $this->merchantId,
            'version' => self::API_VERSION,
            'timestamp' => new DateTime()->format('c'),
        ];
        $headers['signature'] = $this->apiSignature(array_merge($headers, $body));

        try {
            return $this->httpClient->request('POST', self::API_BASE_URL . $path, [
                'headers' => $headers,
                'body' => $body,
            ])->toArray(false);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function apiStatus(array $response): string
    {
        $succeeded = ($response['status'] ?? null) === 'success' || ($response['code'] ?? null) === 200;

        return $succeeded ? 'success' : 'failed';
    }

    /**
     * PayFast API signature: the passphrase (when set) is merged into the
     * header + body parameters, the set is sorted alphabetically by key, and
     * the resulting "key=urlencoded-value" string is md5-hashed.
     *
     * @param array<string, string> $params
     */
    private function apiSignature(array $params): string
    {
        if ($this->passphrase !== '') {
            $params['passphrase'] = $this->passphrase;
        }

        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $pairs[] = $key . '=' . urlencode(trim($value));
        }

        return md5(implode('&', $pairs));
    }
}
