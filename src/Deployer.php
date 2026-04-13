<?php
namespace Deployer;

use Exception;

class Deployer {
    public static function deployNewProject(string $projectName, string $domain, ?array $filesArray = null, ?string $gitRepo = null, string $rootDir = '/', ?array $folderFiles = null): int {
        $cleanName = Uploader::sanitizeProjectName($projectName);
        if (empty($cleanName)) {
            throw new Exception("Invalid project name.");
        }

        $folderPath = SITES_DIR . '/' . $cleanName;
        if (is_dir($folderPath)) {
            throw new Exception("A project with this name already exists.");
        }

        $isGit = !empty($gitRepo);

        try {
            if ($isGit) {
                // Let git create the directory natively
                $cmd = "git clone " . escapeshellarg($gitRepo) . " " . escapeshellarg($folderPath) . " 2>&1";
                $output = shell_exec($cmd);
                if (!is_dir($folderPath . '/.git') && strpos($output, 'fatal') !== false) {
                    throw new Exception("Git clone failed: " . $output);
                }
            } else if ($filesArray && isset($filesArray['tmp_name']) && !empty($filesArray['tmp_name'])) {
                if (!mkdir($folderPath, 0755, true)) throw new Exception("Failed to create project directory.");
                Uploader::extractZip($filesArray['tmp_name'], $folderPath);
            } else if ($folderFiles && isset($folderFiles['name']) && !empty($folderFiles['name'][0])) {
                if (!mkdir($folderPath, 0755, true)) throw new Exception("Failed to create project directory.");
                $relativePaths = [];
                if (isset($folderFiles['full_path'])) {
                    $relativePaths = $folderFiles['full_path'];
                }
                FileManager::uploadFiles($cleanName, $folderFiles, $relativePaths);
            } else {
                if (!mkdir($folderPath, 0755, true)) throw new Exception("Failed to create project directory.");
            }

            Nginx::generateConfig($cleanName, $domain, $folderPath, $rootDir);
            Traefik::generateConfig($cleanName, $domain);
            Nginx::reload();

            return Project::create($cleanName, $domain, $folderPath, $gitRepo, $rootDir);
        } catch (\Exception $e) {
            // Cleanup on failure
            self::recursiveRemoveDirectory($folderPath);
            throw $e;
        }
    }

    public static function enableSSL(int $id): void {
        $project = Project::getById($id);
        if (!$project) throw new Exception("Project not found.");

        $success = Certbot::run($project['domain']);
        if ($success) {
            Project::setSslEnabled($id, true);
        } else {
            throw new Exception("Certbot failed to execute successfully.");
        }
    }

    public static function updateProject(int $id, string $newName, string $newDomain, bool $sslEnabled, ?string $gitRepo = null, string $rootDir = '/'): void {
        $project = Project::getById($id);
        if (!$project) {
            throw new Exception("Project not found.");
        }

        $cleanName = Uploader::sanitizeProjectName($newName);
        if (empty($cleanName)) {
            throw new Exception("Invalid project name.");
        }

        $oldFolderPath = $project['folder_path'];
        $newFolderPath = SITES_DIR . '/' . $cleanName;

        if ($cleanName !== $project['name']) {
            if (is_dir($newFolderPath)) {
                throw new Exception("Another project already uses this name.");
            }
            if (is_dir($oldFolderPath)) {
                rename($oldFolderPath, $newFolderPath);
            } else {
                mkdir($newFolderPath, 0755, true);
            }
            Nginx::removeConfig($project['name']);
        } else {
            $newFolderPath = $oldFolderPath;
        }

        // Regenerate config for domain changes or new name
        Nginx::generateConfig($cleanName, $newDomain, $newFolderPath, $rootDir);
        Traefik::generateConfig($cleanName, $newDomain);
        Nginx::reload();

        Project::update($id, $cleanName, $newDomain, $newFolderPath, $sslEnabled, $gitRepo, $rootDir);

        // Run Certbot if freshly activated (mock)
        if ($sslEnabled && !$project['ssl_enabled']) {
            Certbot::run($newDomain);
        }
    }

    public static function syncGit(int $id): string {
        $project = Project::getById($id);
        if (!$project || empty($project['git_repo'])) {
            throw new Exception("Project does not have a configured Git repository.");
        }
        
        $folderPath = $project['folder_path'];
        if (!is_dir($folderPath . '/.git')) {
            throw new Exception("The specific folder is not a valid git repository. Please upload normally.");
        }

        $cmd = "cd " . escapeshellarg($folderPath) . " && git pull 2>&1";
        $output = shell_exec($cmd);
        return $output ?: "Git pull executed with no output.";
    }

    public static function deleteProject(int $id): void {
        $project = Project::getById($id);
        if (!$project) return; 

        // Remove Configs
        Nginx::removeConfig($project['name']);
        Traefik::removeConfig($project['name']);
        
        // Reload Nginx
        Nginx::reload();

        // Delete Files
        self::recursiveRemoveDirectory($project['folder_path']);

        // Delete DB record
        Project::delete($id);
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
