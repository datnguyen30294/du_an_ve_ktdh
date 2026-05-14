<?php

namespace App\Common\Contracts;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    /**
     * Upload a file and return the stored path.
     */
    public function upload(UploadedFile $file, string $directory): string;

    /**
     * Delete a file by its stored path.
     */
    public function delete(string $path): bool;

    /**
     * Get a URL for the given path.
     * Returns a temporary signed URL for S3 or a public URL for local disk.
     */
    public function getUrl(string $path, int $expirationMinutes = 60): string;

    /**
     * Check if a file exists at the given path.
     */
    public function exists(string $path): bool;
}
