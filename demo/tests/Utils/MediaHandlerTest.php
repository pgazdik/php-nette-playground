<?php

namespace Tests\Utils;

use App\Utils\MediaHandler;
use DateTime;
use PHPUnit\Framework\TestCase;

class MediaHandlerTest extends TestCase
{
    private string $tempDir;
    private MediaHandler $handler;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/media_handler_test_' . uniqid();
        if (!mkdir($this->tempDir) && !is_dir($this->tempDir)) {
             $this->fail(sprintf('Temp directory "%s" was not created', $this->tempDir));
        }
        $this->handler = new MediaHandler($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    //
    // Tests
    //

    public function testListDirNames_ReturnsOnlyDirectories()
    {
        mkdir($this->tempDir . '/dir1');
        mkdir($this->tempDir . '/dir2');
        touch($this->tempDir . '/file1.txt');

        $dirs = $this->handler->listDirNames();

        $this->assertCount(2, $dirs);
        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
        $this->assertNotContains('file1.txt', $dirs);
    }

    public function testListFilesDateAware_ReturnsErrorForNonExistentDir()
    {
        $result = $this->handler->listFilesDateAware('non_existent', new DateTime());
        
        $this->assertFalse($result->isSuccess);
        $this->assertStringContainsString('Not a directory', $result->error);
    }

    public function testListFilesDateAware_ReturnsRelativeFilesInMainDir()
    {
        $category = 'images';
        $categoryDir = $this->tempDir . '/' . $category;
        mkdir($categoryDir);
        
        touch($categoryDir . '/file1.jpg');
        touch($categoryDir . '/file2.png');
        
        $result = $this->handler->listFilesDateAware($category, new DateTime('2023-01-01'));
        
        $this->assertTrue($result->isSuccess);
        $files = $result->value;

        $this->assertCount(2, $files);
        $this->assertContains('images/file1.jpg', $files);
        $this->assertContains('images/file2.png', $files);
    }

    public function testListFilesDateAware_IncludesRelativeFilesFromMatchingDateRangeSubdir()
    {
        $category = 'docs';
        $categoryDir = $this->tempDir . '/' . $category;
        mkdir($categoryDir);
        
        // Create range dir covering 20230101 to 20230131
        $rangeDirName = '20230101-20230131';
        $rangeDir = $categoryDir . '/' . $rangeDirName;
        mkdir($rangeDir);
        touch($rangeDir . '/doc1.pdf');
        
        // Search for date inside range
        $targetDate = new DateTime('2023-01-15');
        $result = $this->handler->listFilesDateAware($category, $targetDate);
        
        $this->assertTrue($result->isSuccess);
        $files = $result->value;
        
        $this->assertCount(1, $files);
        $this->assertContains('docs/20230101-20230131/doc1.pdf', $files);
    }

    public function testListFilesDateAware_ExcludesFilesFromNonMatchingDateRangeSubdir()
    {
        $category = 'docs';
        $categoryDir = $this->tempDir . '/' . $category;
        mkdir($categoryDir);
        
        // Create range dir covering 20230101 to 20230131
        $rangeDirName = '20230101-20230131';
        $rangeDir = $categoryDir . '/' . $rangeDirName;
        mkdir($rangeDir);
        touch($rangeDir . '/doc1.pdf');
        
        // Search for date OUTSIDE range
        $targetDate = new DateTime('2023-02-01');
        $result = $this->handler->listFilesDateAware($category, $targetDate);
        
        $this->assertTrue($result->isSuccess);
        $files = $result->value;
        $this->assertCount(0, $files);
    }
    
    public function testListFilesDateAware_IgnoresNonDateRangeSubdirs()
    {
        $category = 'misc';
        $categoryDir = $this->tempDir . '/' . $category;
        mkdir($categoryDir);
        
        // Create normal subdir
        $subDirName = 'some_folder';
        $subDir = $categoryDir . '/' . $subDirName;
        mkdir($subDir);
        touch($subDir . '/ignored.txt');
        
        $result = $this->handler->listFilesDateAware($category, new DateTime('2023-01-01'));
        
        $this->assertTrue($result->isSuccess);
        $files = $result->value;
        $this->assertCount(0, $files);
    }

    public function testListFilesDateAware_FiltersFilesByPattern()
    {
        $category = 'media';
        $categoryDir = $this->tempDir . '/' . $category;
        mkdir($categoryDir);
        
        touch($categoryDir . '/image1.jpg');
        touch($categoryDir . '/image2.jpeg');
        touch($categoryDir . '/image3.png');
        touch($categoryDir . '/doc1.pdf');

        // Test filtering by regex for JPG
        $result = $this->handler->listFilesDateAware($category, new DateTime('2023-01-01'), '/\.jpg$/i');
        $this->assertTrue($result->isSuccess);
        $files = $result->value;
        $this->assertCount(1, $files);
        $this->assertContains('media/image1.jpg', $files);

        // Test filtering by regex for multiple extensions (jpg, jpeg, png)
        $result = $this->handler->listFilesDateAware($category, new DateTime('2023-01-01'), '/\.(jpg|jpeg|png)$/i');
        $this->assertTrue($result->isSuccess);
        $files = $result->value;
        $this->assertCount(3, $files);
        $this->assertContains('media/image1.jpg', $files);
        $this->assertContains('media/image2.jpeg', $files);
        $this->assertContains('media/image3.png', $files);
        $this->assertNotContains('media/doc1.pdf', $files);

        // Test filtering by regex for filename prefix
        $result = $this->handler->listFilesDateAware($category, new DateTime('2023-01-01'), '/^image/');
        $this->assertTrue($result->isSuccess);
        $this->assertCount(3, $result->value);
    }
}
