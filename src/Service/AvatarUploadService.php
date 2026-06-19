<?php

declare(strict_types=1);

namespace App\Service;

class AvatarUploadService extends AbstractUploadService
{
    protected function directory(): string
    {
        return 'var/storage/avatars';
    }

    protected function maxFileSize(): int
    {
        return 2 * 1024 * 1024;
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
