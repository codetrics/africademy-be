<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
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
    public function payfastNotify(Request $request, OrderService $orderService): Response
    {
        $orderService->handlePayFastItn($request->request->all());

        return new Response('', Response::HTTP_OK);
    }
}
