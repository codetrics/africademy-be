<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\FileException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Shared upload handling: validates an uploaded file against a size cap and a
 * content-based mime allow-list, stores it under a project-relative directory
 * with a random filename, and serves it back. Concrete services only declare
 * the directory, size limit, and allowed mime types.
 */
abstract class AbstractUploadService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] protected readonly string $projectDir,
        protected readonly Filesystem $filesystem,
    ) {
    }

    /** Project-relative storage directory, e.g. var/storage/avatars. */
    abstract protected function directory(): string;

    abstract protected function maxFileSize(): int;

    /**
     * @return array<string, string> allowed mime types mapped to their canonical extension
     */
    abstract protected function allowedMimeTypes(): array;

    /**
     * Stores the uploaded file and returns its project-relative path. Deletes the
     * previously stored file, if any.
     */
    public function store(UploadedFile $file, ?string $previousPath = null): string
    {
        if (!$file->isValid()) {
            throw FileException::invalidUploadedFile();
        }

        if ($file->getSize() > $this->maxFileSize()) {
            throw FileException::fileExceedsMaxSize($this->maxFileSize());
        }

        $mimeType = (string) $file->getMimeType();
        $allowed = $this->allowedMimeTypes();

        if (!array_key_exists($mimeType, $allowed)) {
            throw FileException::invalidFileMimeType($mimeType);
        }

        $fileName = sprintf('%s.%s', bin2hex(random_bytes(16)), $allowed[$mimeType]);
        $absoluteDirectory = sprintf('%s/%s', $this->projectDir, $this->directory());

        $this->filesystem->mkdir($absoluteDirectory);
        $file->move($absoluteDirectory, $fileName);

        if (!is_null($previousPath)) {
            $this->delete($previousPath);
        }

        return sprintf('%s/%s', $this->directory(), $fileName);
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
