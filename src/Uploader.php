<?php
namespace Deployer;

use ZipArchive;
use Exception;

class Uploader {
    public static function extractZip(string $zipPath, string $targetDir): void {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            
            // Validation step: Check extensions inside zip
            $allowed_ext = ['html', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'eot', 'json', 'txt'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];
                
                // Skip directories
                if (substr($filename, -1) === '/') continue;

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $zip->close();
                    throw new Exception("Invalid file type inside ZIP: " . htmlspecialchars($ext) . " in file {$filename}");
                }
            }

            // Path traversal prevention inside Zip
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (strpos($stat['name'], '..') !== false) {
                    $zip->close();
                    throw new Exception("Invalid file path in ZIP containing double dots.");
                }
            }

            $zip->extractTo($targetDir);
            $zip->close();
            
        } else {
            throw new Exception("Failed to open ZIP file. Ensure it is a valid Zip archive.");
        }
    }

    public static function sanitizeProjectName(string $name): string {
        return preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
    }
}
