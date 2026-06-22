<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\RefundRequest;
use App\Entity\User;
use App\Entity\UserLogType;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\RefundRequestRepository;
use DateTime;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

/**
 * Read-and-manage admin views over orders: filtered listing, status counts,
 * single-order lookup, and cancelling abandoned pending orders.
 */
class AdminOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly RefundRequestRepository $refundRequestRepository,
        private readonly UserLogService $userLogService,
    ) {
    }

    public function ordersQueryBuilder(
        ?OrderStatus $status,
        ?string $search,
        ?string $type,
        ?DateTime $from,
        ?DateTime $to,
    ): QueryBuilder {
        return $this->orderRepository->createAdminOrdersQueryBuilder($status, $search, $type, $from, $to);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        return $this->orderRepository->countByStatus();
    }

    public function getOrder(Ulid $publicId): ?Order
    {
        return $this->orderRepository->findOneByPublicId($publicId);
    }

    public function refundFor(Order $order): ?RefundRequest
    {
        return $this->refundRequestRepository->findOneByOrder($order);
    }

    /**
     * Slim buyer summary attached to admin order payloads.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string}
     */
    public function buyerSummary(User $buyer): array
    {
        return [
            'id' => (string) $buyer->getPublicId(),
            'email' => $buyer->getEmail(),
            'first_name' => $buyer->getProfile()->getFirstName(),
            'last_name' => $buyer->getProfile()->getLastName(),
        ];
    }

    /**
     * Cancels an abandoned pending order. The caller is responsible for
     * rejecting non-pending orders before calling this.
     */
    public function cancelPending(Order $order): void
    {
        $order->setStatus(OrderStatus::Cancelled);
        $this->orderRepository->save($order, true);

        $this->userLogService->log(
            UserLogType::ORDER_CANCELLED,
            sprintf('Admin cancelled pending order %s', (string) $order->getPublicId()),
            $order->getUser()->getEmail(),
        );
    }
}
