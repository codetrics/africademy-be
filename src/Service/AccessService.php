<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\Entitlement;
use App\Entity\User;
use App\Enum\EntitlementSource;
use App\Enum\EntitlementStatus;
use App\Repository\EntitlementRepository;
use App\Repository\SubscriptionRepository;
use DateTime;

/**
 * Single access gate: a user can access a course if it is free, they hold a
 * valid entitlement, or it is included in their active subscription.
 */
class AccessService
{
    public function __construct(
        private readonly EntitlementRepository $entitlementRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function hasAccess(User $user, Course $course): bool
    {
        if ($course->isFree()) {
            return true;
        }

        $entitlement = $this->entitlementRepository->findOneByUserAndCourse($user, $course);
        if ($entitlement instanceof Entitlement && $entitlement->isActive() && !$entitlement->isExpired()) {
            return true;
        }

        return $course->isIncludedInSubscription()
            && !is_null($this->subscriptionRepository->findActiveByUserWithinPeriod($user));
    }

    public function grant(User $user, Course $course, EntitlementSource $source, ?DateTime $expiresAt = null): Entitlement
    {
        $entitlement = $this->entitlementRepository->findOneByUserAndCourse($user, $course);

        if (!$entitlement instanceof Entitlement) {
            $entitlement = new Entitlement();
            $entitlement->setUser($user);
            $entitlement->setCourse($course);
        }

        $entitlement->setSource($source);
        $entitlement->setStatus(EntitlementStatus::Active);
        $entitlement->setExpiresAt($expiresAt);

        $this->entitlementRepository->save($entitlement, true);

        return $entitlement;
    }

    public function revoke(Entitlement $entitlement): void
    {
        $entitlement->setStatus(EntitlementStatus::Revoked);
        $this->entitlementRepository->save($entitlement, true);
    }
}
