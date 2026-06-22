<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
use App\Service\PaymentMethodService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PayFast ITN (Instant Transaction Notification) webhook. Server-to-server,
 * unauthenticated and outside the API firewall. Always returns 200 so PayFast
 * stops retrying; the payload is validated and processed by OrderService.
 */
final class PaymentWebhookController extends AbstractController
{
    #[Route('/webhooks/payfast/notify', name: 'payfast_itn', methods: [Request::METHOD_POST])]
    public function payfastNotify(
        Request $request,
        PaymentMethodService $paymentMethodService,
        OrderService $orderService,
        LoggerInterface $payfastLogger,
    ): Response {
        $data = $request->request->all();

        $payfastLogger->info('PayFast ITN endpoint hit', [
            'ip' => $request->getClientIp(),
            'm_payment_id' => (string) ($data['m_payment_id'] ?? ''),
            'payment_status' => (string) ($data['payment_status'] ?? ''),
            'has_signature' => isset($data['signature']),
        ]);

        // Tokenization (card setup) ITNs are routed to payment methods; everything else to orders.
        if (!$paymentMethodService->handleTokenizationItn($data)) {
            $orderService->handlePayFastItn($data);
        }

        return new Response('', Response::HTTP_OK);
    }
}
