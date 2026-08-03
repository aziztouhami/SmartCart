<?php

namespace App\Tests\Unit\Service;

use App\Service\FileUploadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadServiceTest extends TestCase
{
    private string $projectDir;
    private FileUploadService $service;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/smartcart_upload_test_'.uniqid();
        mkdir($this->projectDir, 0755, true);

        $this->service = new FileUploadService($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** A real, minimal 1x1 transparent PNG — FileUploadService checks the
     * server-guessed MIME type (real file content sniffing), not just the
     * client-supplied one, so fake text content would always be rejected. */
    private const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function makeUploadedImage(string $originalName = 'photo.png'): UploadedFile
    {
        $tmpPath = sys_get_temp_dir().'/'.uniqid('upload_', true);
        file_put_contents($tmpPath, base64_decode(self::ONE_PIXEL_PNG_BASE64));

        return new UploadedFile($tmpPath, $originalName, 'image/png', null, true);
    }

    public function testUploadsAValidImageAndReturnsPublicUrl(): void
    {
        $file = $this->makeUploadedImage();

        $url = $this->service->upload($file, 'products', ['image/png', 'image/jpeg'], 5 * 1024 * 1024, 'product_');

        $this->assertStringStartsWith('/uploads/products/product_', $url);
        $this->assertStringEndsWith('.png', $url);
        $this->assertFileExists($this->projectDir.'/public'.$url);
    }

    public function testUploadsWithoutSubdirWhenEmpty(): void
    {
        $file = $this->makeUploadedImage();

        $url = $this->service->upload($file, '', ['image/png'], 5 * 1024 * 1024);

        $this->assertStringStartsWith('/uploads/', $url);
        $this->assertStringNotContainsString('/uploads//', $url);
    }

    public function testRejectsDisallowedMimeType(): void
    {
        $file = $this->makeUploadedImage(); // real PNG, but PNG is not in the allow-list below

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported file type');
        $this->expectExceptionCode(400);

        $this->service->upload($file, 'products', ['image/jpeg'], 5 * 1024 * 1024);
    }

    public function testRejectsFileExceedingMaxSize(): void
    {
        $file = $this->makeUploadedImage();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File must not exceed 0 MB');
        $this->expectExceptionCode(400);

        $this->service->upload($file, 'products', ['image/png'], 1); // 1 byte max — the PNG is bigger
    }

    public function testGeneratesUniqueFilenamesForSameOriginalName(): void
    {
        $file1 = $this->makeUploadedImage();
        $file2 = $this->makeUploadedImage();

        $url1 = $this->service->upload($file1, 'products', ['image/png'], 5 * 1024 * 1024);
        $url2 = $this->service->upload($file2, 'products', ['image/png'], 5 * 1024 * 1024);

        $this->assertNotSame($url1, $url2);
    }
}
