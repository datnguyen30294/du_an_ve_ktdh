<?php

namespace Tests\Unit;

use App\Common\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        $this->storageService = new StorageService;
    }

    public function test_upload_stores_file_and_returns_path(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $path = $this->storageService->upload($file, 'avatars');

        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_upload_generates_unique_filenames(): void
    {
        $file1 = UploadedFile::fake()->image('avatar.jpg');
        $file2 = UploadedFile::fake()->image('avatar.jpg');

        $path1 = $this->storageService->upload($file1, 'avatars');
        $path2 = $this->storageService->upload($file2, 'avatars');

        $this->assertNotEquals($path1, $path2);
    }

    public function test_delete_removes_file(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $this->storageService->upload($file, 'avatars');

        Storage::disk('s3')->assertExists($path);

        $result = $this->storageService->delete($path);

        $this->assertTrue($result);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_exists_returns_true_for_existing_file(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $this->storageService->upload($file, 'avatars');

        $this->assertTrue($this->storageService->exists($path));
    }

    public function test_exists_returns_false_for_missing_file(): void
    {
        $this->assertFalse($this->storageService->exists('avatars/nonexistent.jpg'));
    }

    public function test_get_url_returns_url_string(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $this->storageService->upload($file, 'avatars');

        $url = $this->storageService->getUrl($path);

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    public function test_get_url_returns_cdn_url_when_configured(): void
    {
        config(['filesystems.disks.s3.url' => 'https://cdn-rm.nathen.io.vn']);

        $url = $this->storageService->getUrl('avatars/test.jpg');

        $this->assertEquals('https://cdn-rm.nathen.io.vn/avatars/test.jpg', $url);
    }

    public function test_get_url_falls_back_when_no_cdn_configured(): void
    {
        config(['filesystems.disks.s3.url' => null]);

        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $this->storageService->upload($file, 'avatars');

        $url = $this->storageService->getUrl($path);

        $this->assertIsString($url);
        $this->assertStringContainsString($path, $url);
    }
}
