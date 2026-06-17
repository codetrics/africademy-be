<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class FileException extends Exception
{
    public static function invalidUploadedFile(): FileException
    {
        return new static('Invalid uploaded file.');
    }

    public static function fileExceedsMaxSize(int $maxFileSize): FileException
    {
        return new static(sprintf('File exceeds max size of %d bytes.', $maxFileSize));
    }

    public static function invalidFileMimeType(string $mimeType): FileException
    {
        return new static('Invalid file mime type: ' . $mimeType);
    }

    public static function fileNotFound(string $relativePath): FileException
    {
        return new static('File not found: ' . $relativePath);
    }
}
