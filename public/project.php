<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Project.php';
require_once __DIR__ . '/../src/FileManager.php';
require_once __DIR__ . '/../src/Nginx.php';
require_once __DIR__ . '/../src/Certbot.php';
require_once __DIR__ . '/../src/Traefik.php';
require_once __DIR__ . '/../src/Deployer.php';

use Deployer\Auth;
use Deployer\Project;
use Deployer\FileManager;

Auth::requireLogin();

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /index.php");
    exit;
}

$project = Project::getById((int)$id);
if (!$project) {
    header("Location: /index.php");
    exit;
}

$folderPath = SITES_DIR . '/' . $project['name'];
if (!is_dir($folderPath)) {
    mkdir($folderPath, 0755, true);
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $path = $_POST['path'] ?? '';
        $redirectDir = $_GET['dir'] ?? '';
        try {
            FileManager::deletePath($project['name'], $path);
            header("Location: /project.php?id=" . $id . "&dir=" . urlencode($redirectDir));
            exit;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $newName = $_POST['name'] ?? '';
        $newDomain = $_POST['domain'] ?? '';
        $newGit = $_POST['git_repo'] ?? '';
        $newRoot = $_POST['root_dir'] ?? '/';
        $newProxy = trim($_POST['api_proxy_url'] ?? '') ?: null;
        $newSsl = isset($_POST['ssl']);
        try {
            \Deployer\Deployer::updateProject($project['id'], $newName, $newDomain, $newSsl, $newGit, $newRoot, $newProxy);
            $project = Project::getById($project['id']);
            $success = "Project settings updated successfully.";
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "CSRF validation failed.";
    }
}

// Handle Git Sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_git') {
    if (Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        try {
            $syncMessage = \Deployer\Deployer::syncGit($project['id']);
            $success = "Git synced successfully: " . $syncMessage;
        } catch (\Exception $e) {
            $error = "Git Sync Error: " . $e->getMessage();
        }
    } else {
        $error = "CSRF validation failed.";
    }
}

$subDir = $_GET['dir'] ?? '';

try {
    $files = FileManager::getProjectFiles($project['name'], $subDir);
} catch (\Exception $e) {
    $files = [];
    $error = $e->getMessage();
}

function getIcon($is_dir, $filename) {
    if ($is_dir) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="var(--primary)"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';
    }
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp'])) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="#a78bfa"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
    }
    if (in_array($ext, ['html', 'css', 'js', 'json'])) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="#fbbf24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>';
    }
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="var(--text-muted)"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?= htmlspecialchars($project['name']) ?> - Web Deployer</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .drag-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            z-index: 50;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            border: 4px dashed var(--primary);
        }
        body.dragover .drag-overlay {
            display: flex;
        }
        .file-icon-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .breadcrumb a:hover {
            color: var(--primary-hover);
        }
        .breadcrumb-separator {
            color: var(--text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/index.php" class="navbar-brand">Web Deployer</a>
        <div class="navbar-nav">
            <span style="font-size: 14px; margin-right: 10px;">Admin Console</span>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="drag-overlay">
        <h2>Drop files to upload</h2>
    </div>

    <main class="container">
        <div class="page-header">
            <div>
                <h1><?= htmlspecialchars($project['name']) ?> <span class="badge badge-success" style="font-size: 14px; position:relative; top:-2px;">Files</span></h1>
                <div class="breadcrumb">
                    <a href="?id=<?= $project['id'] ?>">Root</a>
                    <?php
                        $parts = array_filter(explode('/', $subDir));
                        $currentPath = '';
                        foreach ($parts as $part) {
                            $currentPath .= ($currentPath ? '/' : '') . $part;
                            echo '<span class="breadcrumb-separator">/</span>';
                            echo '<a href="?id=' . $project['id'] . '&dir=' . urlencode($currentPath) . '">' . htmlspecialchars($part) . '</a>';
                        }
                    ?>
                </div>
            </div>
            <div style="display:flex; gap: 8px; height: fit-content;">
                <?php if (!empty($project['git_repo'])): ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="sync_git">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrf()) ?>">
                    <button type="submit" class="btn secondary-btn" style="border-color: #6366f1; color: #818cf8;">Sync Git</button>
                </form>
                <?php endif; ?>
                <a href="http<?= $project['ssl_enabled'] ? 's' : '' ?>://<?= htmlspecialchars($project['domain']) ?>" target="_blank" class="btn primary-btn">Open Site</a>
                <a href="/index.php" class="btn secondary-btn">Back</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Project Settings -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
            <h3 style="margin-bottom: 16px; font-size: 16px; display:flex; justify-content:space-between;">
                Project Settings
                <?php if (isset($success)): ?>
                    <span style="color: #4ade80; font-size:14px; font-weight:normal;"><?= htmlspecialchars($success) ?></span>
                <?php endif; ?>
            </h3>
            <form method="POST" action="/project.php?id=<?= $project['id'] ?>" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrf()) ?>">
                
                <div class="form-group" style="margin-bottom: 0px; flex: 1; min-width: 200px;">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($project['name']) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0px; flex: 1; min-width: 200px;">
                    <label>Domain</label>
                    <input type="text" name="domain" value="<?= htmlspecialchars($project['domain']) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0px; flex: 1; min-width: 200px;">
                    <label>Git Repository (Optional)</label>
                    <input type="url" name="git_repo" value="<?= htmlspecialchars($project['git_repo'] ?? '') ?>" placeholder="https://github.com/...">
                </div>
                <div class="form-group" style="margin-bottom: 0px; flex: 1; min-width: 150px;">
                    <label>Publish Directory</label>
                    <input type="text" name="root_dir" value="<?= htmlspecialchars($project['root_dir'] ?? '/') ?>" placeholder="e.g. /dist">
                </div>
                <div class="form-group" style="margin-bottom: 0px; flex: 1; min-width: 200px;">
                    <label>API Proxy URL</label>
                    <input type="url" name="api_proxy_url" value="<?= htmlspecialchars($project['api_proxy_url'] ?? '') ?>" placeholder="e.g. http://api.example.com">
                </div>
                <div class="form-group" style="margin-bottom: 0px; display:flex; align-items: center; gap: 8px; flex: 0.5; min-width: 120px; padding-bottom: 12px;">
                    <input type="checkbox" name="ssl" value="1" <?= $project['ssl_enabled'] ? 'checked' : '' ?> id="ssl_config" style="width:auto;">
                    <label for="ssl_config" style="margin: 0; font-weight: normal;">Enable SSL</label>
                </div>
                <button type="submit" class="btn primary-btn">Update</button>
            </form>
        </div>

        <!-- Controls -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin-bottom: 4px; font-size: 16px;">File Manager</h3>
                <p style="color: var(--text-muted); font-size: 14px;">Drag & Drop anywhere on the screen, or click to browse</p>
            </div>
            <div style="display:flex; gap: 10px;">
                <input type="file" id="file-upload" multiple style="display:none;" onchange="handleFiles(this.files)">
                <button class="btn secondary-btn" onclick="document.getElementById('file-upload').click()">Upload Files</button>
                
                <input type="file" id="folder-upload" webkitdirectory directory multiple style="display:none;" onchange="handleFiles(this.files)">
                <button class="btn secondary-btn" onclick="document.getElementById('folder-upload').click()">Upload Folder</button>
            </div>
        </div>

        <div id="upload-progress" style="margin-bottom: 20px; display:none; padding: 12px 16px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; color: var(--primary);">
            Uploading items, please wait...
        </div>

        <!-- Files List -->
        <div class="glass-panel table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Back button row -->
                    <?php if (!empty($subDir)): 
                        $parentPath = dirname($subDir);
                        if ($parentPath === '.' || $parentPath === '\\') $parentPath = '';
                    ?>
                    <tr onclick="window.location.href='?id=<?= $project['id'] ?>&dir=<?= urlencode($parentPath) ?>'" style="cursor: pointer;">
                        <td colspan="5" style="color: var(--text-muted); padding-left: 20px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> 
                            ..
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if (empty($files)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">This folder is empty.</td></tr>
                    <?php else: ?>
                        <?php foreach($files as $file): ?>
                        <tr <?= $file['is_dir'] ? 'onclick="if(event.target.closest(\'form\') || event.target.closest(\'a\')) return; window.location.href=\'?id=' . $project['id'] . '&dir=' . urlencode($file['path']) . '\';"' : '' ?> style="<?= $file['is_dir'] ? 'cursor:pointer;' : '' ?>">
                            <td>
                                <div class="file-icon-wrapper">
                                    <?= getIcon($file['is_dir'], $file['name']) ?>
                                    <?php if ($file['is_dir']): ?>
                                        <a href="?id=<?= $project['id'] ?>&dir=<?= urlencode($file['path']) ?>" style="color:var(--text-main); text-decoration:none; font-weight: 500;">
                                            <?= htmlspecialchars($file['name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($file['name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="color: var(--text-muted);"><?= $file['is_dir'] ? 'Folder' : strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) . ' File' ?></td>
                            <td style="color: var(--text-muted);"><?= $file['is_dir'] ? '-' : round($file['size']/1024, 2) . ' KB' ?></td>
                            <td style="color: var(--text-muted);"><?= date('M j, Y H:i', $file['modified']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete \'<?= htmlspecialchars($file['name']) ?>\'?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="path" value="<?= htmlspecialchars($file['path']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrf()) ?>">
                                    <button type="submit" class="btn danger-btn btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    let dragCounter = 0;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    document.body.addEventListener('dragenter', (e) => {
        dragCounter++;
        document.body.classList.add('dragover');
    }, false);

    document.body.addEventListener('dragleave', (e) => {
        dragCounter--;
        if (dragCounter === 0) {
            document.body.classList.remove('dragover');
        }
    }, false);

    document.body.addEventListener('drop', (e) => {
        dragCounter = 0;
        document.body.classList.remove('dragover');
        let dt = e.dataTransfer;
        let files = dt.files;
        handleFiles(files);
    }, false);

    function handleFiles(files) {
        if (!files.length) return;
        
        document.getElementById('upload-progress').style.display = 'block';
        
        const formData = new FormData();
        formData.append('project_id', '<?= $project['id'] ?>');
        formData.append('csrf_token', '<?= htmlspecialchars(Auth::generateCsrf()) ?>');
        formData.append('current_dir', <?= json_encode($subDir) ?>);

        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
            formData.append('paths[]', files[i].webkitRelativePath || files[i].name);
        }

        fetch('/api/upload_files.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Upload failed');
                document.getElementById('upload-progress').style.display = 'none';
            }
        })
        .catch(() => {
            alert('Upload error');
            document.getElementById('upload-progress').style.display = 'none';
        });
    }
    </script>
</body>
</html>
