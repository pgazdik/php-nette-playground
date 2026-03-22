<?php
namespace App\Utils;

use App\Common\Maybe;
use DateTime;

class MediaHandler
{

    public function __construct(
        private string $rootDir
    ) {
    }

    public function resolvePath(string $path): string
    {
        return $this->rootDir . '/' . $path;
    }

    /** @return string[] */
    public function listDirNames(): array
    {
        $dirNames = [];
        foreach (scandir($this->rootDir) as $file) {
            if (is_dir($this->rootDir . '/' . $file) && $file !== '.' && $file !== '..') {
                $dirNames[] = $file;
            }
        }
        return $dirNames;
    }

    /**
     * Returns array of file paths relative to the root directory from the main directory 
     * and subdirectories where the date falls within the range.
     * $pattern is a regular expression used to filter files (e.g., "/\.(jpg|jpeg|png)$/i").
     */
    public function listFilesDateAware(string $dirName, DateTime $date, string $pattern = '//'): Maybe
    {
        $dir = $this->rootDir . '/' . $dirName;
        if (!is_dir($dir)) {
            return Maybe::error("Not a directory: " . $dirName);
        }

        $files = [];
        $targetDate = $date->format('Ymd');

        // First, add files from the main directory
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $file;

            // If it's a file, add it if it matches the pattern
            if (is_file($fullPath)) {
                if (preg_match($pattern, $file)) {
                    $files[] = $dirName . '/' . $file;
                }
            }
            // If it's a directory with date range pattern, check if date falls within range
            elseif (is_dir($fullPath) && $this->isDateRangeDir($file)) {
                if ($this->isDateInRange($targetDate, $file)) {
                    // Add files from this subdirectory that match the pattern
                    foreach (scandir($fullPath) as $subFile) {
                        if ($subFile === '.' || $subFile === '..') {
                            continue;
                        }
                        $subPath = $fullPath . '/' . $subFile;
                        if (is_file($subPath) && preg_match($pattern, $subFile)) {
                            $files[] = $dirName . '/' . $file . '/' . $subFile;
                        }
                    }
                }
            }
        }

        return Maybe::success($files);
    }

    /**
     * Check if directory name matches date range pattern (yyyymmdd-yyyymmdd)
     */
    private function isDateRangeDir(string $dirName): bool
    {
        return preg_match('/^\d{8}-\d{8}$/', $dirName) === 1;
    }

    /**
     * Check if target date falls within the date range directory name
     */
    private function isDateInRange(string $targetDate, string $rangeDir): bool
    {
        $parts = explode('-', $rangeDir);
        if (count($parts) !== 2) {
            return false;
        }

        $startDate = $parts[0];
        $endDate = $parts[1];

        return $targetDate >= $startDate && $targetDate <= $endDate;
    }


}
