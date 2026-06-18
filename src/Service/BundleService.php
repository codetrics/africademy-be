<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bundle;
use App\Entity\Course;
use App\Enum\BundleStatus;
use App\Enum\CourseStatus;
use App\Exceptions\BundleException;
use App\Repository\BundleRepository;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Ulid;

class BundleService
{
    public function __construct(
        private readonly BundleRepository $bundleRepository,
        private readonly CourseRepository $courseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function create(Bundle $bundle): Bundle
    {
        $bundle->setSlug($this->generateUniqueSlug($bundle->getTitle()));

        $this->entityManager->beginTransaction();
        try {
            $this->bundleRepository->save($bundle, true);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $bundle;
    }

    public function update(Bundle $bundle): Bundle
    {
        $this->bundleRepository->save($bundle, true);

        return $bundle;
    }

    public function publish(Bundle $bundle): Bundle
    {
        $bundle->setStatus(BundleStatus::Published);
        $this->bundleRepository->save($bundle, true);

        return $bundle;
    }

    public function delete(Bundle $bundle): void
    {
        $this->bundleRepository->remove($bundle, true);
    }

    /**
     * Replaces the bundle's courses. Every course must be owned by the bundle
     * owner and be published.
     *
     * @param Ulid[] $coursePublicIds
     *
     * @throws BundleException
     */
    public function setCourses(Bundle $bundle, array $coursePublicIds): Bundle
    {
        $bundle->clearCourses();

        foreach ($coursePublicIds as $coursePublicId) {
            $course = $this->courseRepository->findOneByPublicId($coursePublicId);

            if (
                !$course instanceof Course
                || $course->getOwner()->getId() !== $bundle->getOwner()->getId()
                || $course->getStatus() !== CourseStatus::Published
            ) {
                throw BundleException::courseNotEligible();
            }

            $bundle->addCourse($course);
        }

        $this->bundleRepository->save($bundle, true);

        return $bundle;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();

        if ($base === '') {
            $base = 'bundle';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->bundleRepository->slugExists($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
