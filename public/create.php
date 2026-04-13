<?php
// public/create.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Project.php';
require_once __DIR__ . '/../src/Uploader.php';
require_once __DIR__ . '/../src/FileManager.php';
require_once __DIR__ . '/../src/Nginx.php';
require_once __DIR__ . '/../src/Certbot.php';
require_once __DIR__ . '/../src/Traefik.php';
require_once __DIR__ . '/../src/Deployer.php';

use Deployer\Auth;
use Deployer\Deployer;

Auth::requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $domain = $_POST['domain'] ?? '';
    $gitRepo = $_POST['git_repo'] ?? '';
    $rootDir = $_POST['root_dir'] ?? '/';
    $enableSsl = isset($_POST['ssl']);
    $csrf = $_POST['csrf_token'] ?? '';
    
    $zipFile = null;
    if (isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK) {
        $zipFile = $_FILES['zipfile'];
    }
    
    $folderFiles = null;
    if (isset($_FILES['folder_upload']) && !empty($_FILES['folder_upload']['name'][0])) {
        $folderFiles = $_FILES['folder_upload'];
    }

    if (!Auth::validateCsrf($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($name) || empty($domain)) {
        $error = 'Project Name and Domain are required.';
    } else {
        try {
            \Deployer\Deployer::deployNewProject($name, $domain, $zipFile, $gitRepo, $rootDir, $folderFiles);
            $success = 'Project deployed successfully!';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project - Web Deployer</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="/index.php" class="navbar-brand">Web Deployer</a>
        <div class="navbar-nav">
            <span style="font-size: 14px; margin-right: 10px;">Admin Console</span>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>
    <main class="container">
        <div class="page-header">
            <h1>New Project</h1>
            <a href="/index.php" class="btn secondary-btn">Back to Dashboard</a>
        </div>
        
        <div class="glass-panel" style="padding: 30px;">
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <script>
                    setTimeout(() => { window.location.href = '/index.php' }, 2000);
                </script>
            <?php endif; ?>

            <form method="POST" action="/create.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrf()) ?>">
                <div class="form-group">
                    <label for="name">Project Name (Letters, numbers, dashes only)</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. my-portfolio">
                </div>
                <div class="form-group">
                    <label for="domain">Domain Route</label>
                    <input type="text" id="domain" name="domain" required placeholder="e.g. portfolio.example.com">
                </div>
                <div class="form-group">
                    <label>Deployment Method (Optional)</label>
                    <select id="deploy_method" onchange="toggleDeployMethod()" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(15, 23, 42, 0.4); color: white; display: block;">
                        <option value="none">Empty Workspace (Configure later)</option>
                        <option value="folder">Upload Local Folder</option>
                        <option value="zip">Upload ZIP Archive</option>
                        <option value="git">Git Clone URL</option>
                    </select>

                    <div id="method_folder" style="display:none; margin-top:16px;">
                        <input type="file" name="folder_upload[]" webkitdirectory directory multiple>
                        <small style="color:var(--text-muted); display:block; margin-top:4px;">Select an entire structured folder from your computer. Files filter applies automatically.</small>
                    </div>

                    <div id="method_zip" style="display:none; margin-top:16px;">
                        <input type="file" id="zipfile" name="zipfile" accept=".zip">
                        <small style="color: var(--text-muted); display: block; margin-top: 4px;">Max size 20MB. Zip format only.</small>
                    </div>
                    
                    <div id="method_git" style="display:none; margin-top:16px;">
                        <input type="url" id="git_repo" name="git_repo" placeholder="https://github.com/user/repo.git">
                        <small style="color:var(--text-muted); display:block; margin-top:4px;">The system will automatically run git clone into your workspace natively.</small>
                    </div>
                </div>
                
                <script>
                function toggleDeployMethod() {
                    const method = document.getElementById('deploy_method').value;
                    document.getElementById('method_zip').style.display = method === 'zip' ? 'block' : 'none';
                    document.getElementById('method_folder').style.display = method === 'folder' ? 'block' : 'none';
                    document.getElementById('method_git').style.display = method === 'git' ? 'block' : 'none';
                }
                </script>

                <div class="form-group" style="padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <label for="root_dir">Publish Directory</label>
                    <input type="text" id="root_dir" name="root_dir" placeholder="e.g. /dist" value="/">
                    <small style="color:var(--text-muted); display:block; margin-top:4px;">If your index.html is located in a subfolder like /build or /dist.</small>
                </div>
                <!-- Not implementing real async SSL for this static test but here's the UI option -->
                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="ssl" name="ssl" value="1" style="width: auto;">
                    <label for="ssl" style="margin-bottom: 0;">Enable SSL via Certbot (Mock)</label>
                </div>
                <button type="submit" class="btn primary-btn btn-block" style="margin-top: 20px;">Deploy Project</button>
            </form>
        </div>
    </main>
</body>
</html>
