<?php
namespace Deployer;

require_once __DIR__ . '/Uploader.php';

use Exception;

class FileManager {
    public static function getProjectFiles(string $projectName, string $subDir = ''): array {
        $cleanName = Uploader::sanitizeProjectName($projectName);
        
        if (strpos($subDir, '..') !== false) {
            throw new Exception("Invalid directory path.");
        }

        $basePath = SITES_DIR . '/' . $cleanName;
        $targetPath = $basePath . ($subDir ? '/' . trim($subDir, '/') : '');
        
        if (!is_dir($targetPath)) {
            throw new Exception("Directory not found.");
        }

        $files = [];
        $iterator = new \DirectoryIterator($targetPath);

        foreach ($iterator as $file) {
            if ($file->isDot()) continue;
            
            $relPath = ltrim(($subDir ? trim($subDir, '/') . '/' : '') . $file->getFilename(), '/');
            
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $relPath,
                'is_dir' => $file->isDir(),
                'size' => $file->isDir() ? 0 : $file->getSize(),
                'modified' => $file->getMTime()
            ];
        }

        // Return sorted, directories first, then alphabetical
        usort($files, function($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });

        return $files;
    }

    public static function deletePath(string $projectName, string $relativePath): void {
        $cleanName = Uploader::sanitizeProjectName($projectName);
        $folderPath = SITES_DIR . '/' . $cleanName;
        
        if (strpos($relativePath, '..') !== false) {
            throw new Exception("Invalid path traversal.");
        }

        $targetObj = $folderPath . '/' . ltrim($relativePath, '/');
        if (!file_exists($targetObj)) {
            throw new Exception("File or folder not found.");
        }

        if (is_dir($targetObj)) {
            self::recursiveRemoveDirectory($targetObj);
        } else {
            unlink($targetObj);
        }
    }

    public static function uploadFiles(string $projectName, array $filesArray, array $relativePaths = [], string $currentDir = ''): void {
        $cleanName = Uploader::sanitizeProjectName($projectName);
        $folderPath = SITES_DIR . '/' . $cleanName;
        
        $allowedExtStr = 'html,css,js,png,jpg,jpeg,gif,svg,webp,woff,woff2,ttf,eot,json,txt';
        $allowed_ext = explode(',', $allowedExtStr);

        if (!is_dir($folderPath)) {
            throw new Exception("Project directory not found.");
        }

        if (strpos($currentDir, '..') !== false) {
            throw new Exception("Invalid directory path.");
        }

        $count = count($filesArray['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($filesArray['error'][$i] !== UPLOAD_ERR_OK) continue;

            $filename = basename($filesArray['name'][$i]);
            // Use relative path from webkit directory if passed, otherwise base name
            $relPath = isset($relativePaths[$i]) && !empty($relativePaths[$i]) ? $relativePaths[$i] : $filename;
            
            // Clean paths
            $relPath = str_replace('\\', '/', $relPath);

            if (!empty($currentDir)) {
                $relPath = trim($currentDir, '/') . '/' . ltrim($relPath, '/');
            }

            if (strpos($relPath, '..') !== false) {
                 continue; // skip path traversals securely
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            // Only validate files with extensions, allow extensionless files like CNAME if needed, but safe to restrict to allowed.
            if (!empty($ext) && !in_array($ext, $allowed_ext)) {
                continue; // skip unallowed types
            }

            $targetObj = $folderPath . '/' . ltrim($relPath, '/');
            $dir = dirname($targetObj);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            move_uploaded_file($filesArray['tmp_name'][$i], $targetObj);
        }
    }

    private static function recursiveRemoveDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.','..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recursiveRemoveDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
