<?php
// public/delete.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Project.php';
require_once __DIR__ . '/../src/Nginx.php';
require_once __DIR__ . '/../src/Traefik.php';
require_once __DIR__ . '/../src/Deployer.php';

use Deployer\Auth;
use Deployer\Deployer;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        die("CSRF validation failed");
    }
    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            Deployer::deleteProject((int)$id);
        } catch (Exception $e) {
            // silent catch on delete to prevent breaking UI
        }
    }
}

header("Location: /index.php");
exit;
