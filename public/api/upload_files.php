<?php
// public/api/upload_files.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Project.php';
require_once __DIR__ . '/../../src/FileManager.php';
require_once __DIR__ . '/../../src/Uploader.php';

use Deployer\Auth;
use Deployer\Project;
use Deployer\FileManager;
use Deployer\Uploader;

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Methods not allowed']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!Auth::validateCsrf($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$id = $_POST['project_id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No project ID']);
    exit;
}

$project = Project::getById((int)$id);
if (!$project) {
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}

$filesArray = $_FILES['files'] ?? null;
$pathsArray = $_POST['paths'] ?? [];
$currentDir = $_POST['current_dir'] ?? '';

if (!$filesArray || empty($filesArray['name'][0])) {
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit;
}

try {
    FileManager::uploadFiles($project['name'], $filesArray, $pathsArray, $currentDir);
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
