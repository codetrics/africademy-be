<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coupon;
use App\Entity\CouponRedemption;
use App\Entity\Order;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\UserLogType;
use App\Enum\DiscountType;
use App\Exceptions\CouponException;
use App\Repository\CouponRedemptionRepository;
use App\Repository\CouponRepository;
use DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

class CouponService
{
    public function __construct(
        private readonly CouponRepository $couponRepository,
        private readonly CouponRedemptionRepository $couponRedemptionRepository,
        private readonly UserLogService $userLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Validates a coupon for a user against an amount. Returns the coupon or throws.
     *
     * @throws CouponException
     */
    public function validate(string $code, User $user, int $amountCents): Coupon
    {
        $coupon = $this->couponRepository->findOneByCode($code);

        if (is_null($coupon) || !$coupon->isActive()) {
            throw CouponException::invalid();
        }

        if (!is_null($coupon->getExpiresAt()) && $coupon->getExpiresAt() < new DateTime()) {
            throw CouponException::expired();
        }

        if (!is_null($coupon->getMaxRedemptions()) && $coupon->getRedemptionCount() >= $coupon->getMaxRedemptions()) {
            throw CouponException::limitReached();
        }

        if (!is_null($coupon->getMinAmountCents()) && $amountCents < $coupon->getMinAmountCents()) {
            throw CouponException::minimumNotMet();
        }

        if (!is_null($this->couponRedemptionRepository->findOneByCouponAndUser($coupon, $user))) {
            throw CouponException::alreadyRedeemed();
        }

        return $coupon;
    }

    /**
     * Discount (in cents) the coupon applies to an amount, capped at the amount.
     */
    public function computeDiscount(Coupon $coupon, int $amountCents): int
    {
        $discount = match ($coupon->getDiscountType()) {
            DiscountType::Percent => (int) floor($amountCents * $coupon->getDiscountValue() / 100),
            DiscountType::Fixed => $coupon->getDiscountValue(),
        };

        return max(0, min($discount, $amountCents));
    }

    /**
     * Records a redemption and increments the coupon's count. No-op if the user
     * already redeemed this coupon.
     */
    public function redeem(Coupon $coupon, User $user, int $amountDiscountedCents, ?Order $order = null, ?Subscription $subscription = null): void
    {
        if (!is_null($this->couponRedemptionRepository->findOneByCouponAndUser($coupon, $user))) {
            return;
        }

        // Lock the coupon row (callers run redeem() inside a transaction) so the
        // redemption-count increment is serialised — concurrent redemptions
        // cannot lose an increment or both slip past the limit.
        $coupon = $this->entityManager->find(Coupon::class, $coupon->getId(), LockMode::PESSIMISTIC_WRITE);
        if (!$coupon instanceof Coupon) {
            return;
        }

        $redemption = new CouponRedemption();
        $redemption->setCoupon($coupon);
        $redemption->setUser($user);
        $redemption->setOrder($order);
        $redemption->setSubscription($subscription);
        $redemption->setAmountDiscountedCents($amountDiscountedCents);
        $this->couponRedemptionRepository->save($redemption);

        $this->couponRepository->incrementRedemptionCount($coupon);

        $this->userLogService->log(
            UserLogType::COUPON_REDEEMED,
            'Coupon redeemed',
            $user->getEmail(),
            context: ['coupon' => $coupon->getCode(), 'discount_cents' => $amountDiscountedCents],
        );
    }

    public function codeExists(string $code): bool
    {
        return !is_null($this->couponRepository->findOneByCode($code));
    }

    public function save(Coupon $coupon): Coupon
    {
        $this->couponRepository->save($coupon, true);

        return $coupon;
    }

    public function delete(Coupon $coupon): void
    {
        $this->couponRepository->remove($coupon, true);
    }

    public function listQueryBuilder(): QueryBuilder
    {
        return $this->couponRepository->createListQueryBuilder();
    }

    /**
     * @throws CouponException
     */
    public function getByPublicId(Ulid $publicId): Coupon
    {
        $coupon = $this->couponRepository->findOneByPublicId($publicId);

        if (is_null($coupon)) {
            throw CouponException::notFound();
        }

        return $coupon;
    }
}
