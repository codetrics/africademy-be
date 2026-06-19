<?php

declare(strict_types=1);

namespace App\Service;

class LessonVideoUploadService extends AbstractUploadService
{
    protected function directory(): string
    {
        return 'var/storage/lessons';
    }

    protected function maxFileSize(): int
    {
        return 10 * 1024 * 1024 * 1024;
    }

    /**
     * @return array<string, string>
     */
    protected function allowedMimeTypes(): array
    {
        return [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];
    }
}
