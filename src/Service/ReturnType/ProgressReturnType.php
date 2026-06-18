<?php

declare(strict_types=1);

namespace App\Service\ReturnType;

class ProgressReturnType
{
    public function __construct(
        private readonly int $progressPercent,
        private readonly int $completedCount,
        private readonly int $totalCount,
        private readonly ?string $currentLessonId,
        private readonly string $status,
    ) {
    }

    public function getProgressPercent(): int
    {
        return $this->progressPercent;
    }

    public function getCompletedCount(): int
    {
        return $this->completedCount;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getCurrentLessonId(): ?string
    {
        return $this->currentLessonId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
