<?php

namespace App\Common\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StorageService implements StorageServiceInterface
{
    public function upload(UploadedFile $file, string $directory): string
    {
        try {
            $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

            $path = $file->storeAs($directory, $filename);

            if ($path === false) {
                throw new \RuntimeException('storeAs returned false');
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('File upload failed', [
                'directory' => $directory,
                'message' => $e->getMessage(),
            ]);

            throw new BusinessException(
                message: 'Không thể tải lên tệp. Vui lòng thử lại.',
                errorCode: 'FILE_UPLOAD_FAILED',
                httpStatusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function delete(string $path): bool
    {
        try {
            return Storage::delete($path);
        } catch (\Throwable $e) {
            Log::error('File deletion failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getUrl(string $path, int $expirationMinutes = 60): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cdnUrl = config('filesystems.disks.s3.url');

        if ($cdnUrl) {
            return rtrim($cdnUrl, '/').'/'.ltrim($path, '/');
        }

        try {
            return Storage::temporaryUrl($path, now()->addMinutes($expirationMinutes));
        } catch (\RuntimeException) {
            return Storage::url($path);
        }
    }

    public function exists(string $path): bool
    {
        return Storage::exists($path);
    }
}
