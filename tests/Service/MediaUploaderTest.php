<?php

namespace App\Tests\Service;

use App\Service\MediaUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploaderTest extends TestCase
{
    private string $uploadsDir;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir().'/media_uploader_test_'.uniqid('', true);
        mkdir($this->uploadsDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadsDir)) {
            foreach (glob($this->uploadsDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->uploadsDir);
        }
    }

    public function testUploadMovesFileAndReturnsPublicPath(): void
    {
        $source = $this->createTempFile('hello');
        $file = new UploadedFile($source, 'photo.txt', 'text/plain', null, true);

        $uploader = new MediaUploader($this->uploadsDir);
        $publicPath = $uploader->upload($file);

        self::assertStringStartsWith('uploads/', $publicPath);
        self::assertFileExists($this->uploadsDir.'/'.basename($publicPath));
        // The original temporary file has been moved away.
        self::assertFileDoesNotExist($source);
    }

    public function testUploadHonoursCustomPublicPrefix(): void
    {
        $file = new UploadedFile($this->createTempFile('data'), 'photo.txt', 'text/plain', null, true);

        $uploader = new MediaUploader($this->uploadsDir, 'media');
        $publicPath = $uploader->upload($file);

        self::assertStringStartsWith('media/', $publicPath);
    }

    public function testRemoveDeletesStoredFile(): void
    {
        $file = new UploadedFile($this->createTempFile('bye'), 'photo.txt', 'text/plain', null, true);
        $uploader = new MediaUploader($this->uploadsDir);
        $publicPath = $uploader->upload($file);

        self::assertFileExists($this->uploadsDir.'/'.basename($publicPath));

        $uploader->remove($publicPath);

        self::assertFileDoesNotExist($this->uploadsDir.'/'.basename($publicPath));
    }

    public function testRemoveIgnoresNullOrEmptyPath(): void
    {
        $uploader = new MediaUploader($this->uploadsDir);

        // Should not throw.
        $uploader->remove(null);
        $uploader->remove('');

        $this->expectNotToPerformAssertions();
    }

    public function testRemoveIgnoresMissingFile(): void
    {
        $uploader = new MediaUploader($this->uploadsDir);

        // Should silently do nothing for an unknown file.
        $uploader->remove('uploads/does-not-exist.jpg');

        $this->expectNotToPerformAssertions();
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($path, $content);

        return $path;
    }
}

