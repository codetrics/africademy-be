<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\FileException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AvatarUploadService
{
    private const string AVATAR_DIRECTORY = 'var/storage/avatars';
    private const int MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * @var array<string, string> Allowed mime types mapped to their canonical extension.
     */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Stores the uploaded avatar under var/storage/avatars/ and returns its
     * project-relative path. Deletes the previously stored avatar, if any.
     */
    public function store(UploadedFile $file, ?string $previousPath = null): string
    {
        if (!$file->isValid()) {
            throw FileException::invalidUploadedFile();
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw FileException::fileExceedsMaxSize(self::MAX_FILE_SIZE);
        }

        $mimeType = (string) $file->getMimeType();

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw FileException::invalidFileMimeType($mimeType);
        }

        $fileName = sprintf('%s.%s', bin2hex(random_bytes(16)), self::ALLOWED_MIME_TYPES[$mimeType]);
        $absoluteDirectory = sprintf('%s/%s', $this->projectDir, self::AVATAR_DIRECTORY);

        $this->filesystem->mkdir($absoluteDirectory);
        $file->move($absoluteDirectory, $fileName);

        if (!is_null($previousPath)) {
            $this->delete($previousPath);
        }

        return sprintf('%s/%s', self::AVATAR_DIRECTORY, $fileName);
    }

    public function delete(string $relativePath): void
    {
        $absolutePath = sprintf('%s/%s', $this->projectDir, $relativePath);

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    public function getAbsolutePath(string $relativePath): string
    {
        $absolutePath = sprintf('%s/%s', $this->projectDir, $relativePath);

        if (!$this->filesystem->exists($absolutePath)) {
            throw FileException::fileNotFound($relativePath);
        }

        return $absolutePath;
    }
}
