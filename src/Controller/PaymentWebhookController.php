<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
use App\Service\PaymentMethodService;
use App\Service\PayFastService;
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
        PayFastService $payFastService,
        LoggerInterface $payfastLogger,
    ): Response {
        $data = $request->request->all();

        $payfastLogger->info('PayFast ITN endpoint hit', [
            'ip' => $request->getClientIp(),
            'm_payment_id' => (string) ($data['m_payment_id'] ?? ''),
            'payment_status' => (string) ($data['payment_status'] ?? ''),
            'has_signature' => isset($data['signature']),
        ]);

        // Authenticity gate (defence-in-depth on top of the per-handler signature
        // check): the request must come from a PayFast host AND PayFast must
        // confirm the ITN via the server post-back. Always 200 so PayFast stops
        // retrying a payload we will not process.
        if (!$payFastService->isValidSourceIp($request->getClientIp())) {
            $payfastLogger->warning('PayFast ITN rejected: source IP not allowlisted', ['ip' => $request->getClientIp()]);

            return new Response('', Response::HTTP_OK);
        }

        if (!$payFastService->serverValidateItn($data)) {
            $payfastLogger->warning('PayFast ITN rejected: server validation not VALID', [
                'm_payment_id' => (string) ($data['m_payment_id'] ?? ''),
            ]);

            return new Response('', Response::HTTP_OK);
        }

        // Tokenization (card setup) ITNs are routed to payment methods; everything else to orders.
        if (!$paymentMethodService->handleTokenizationItn($data)) {
            $orderService->handlePayFastItn($data);
        }

        return new Response('', Response::HTTP_OK);
    }
}
