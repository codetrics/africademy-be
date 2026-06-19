<?php

declare(strict_types=1);

namespace App\Service;

class BlogCoverUploadService extends AbstractUploadService
{
    protected function directory(): string
    {
        return 'var/storage/blog';
    }

    protected function maxFileSize(): int
    {
        return 4 * 1024 * 1024;
    }

    /**
     * @return array<string, string>
     */
    protected function allowedMimeTypes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }
}
