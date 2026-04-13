<?php
// public/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use Deployer\Auth;
use Deployer\Database;

Auth::requireLogin();

$db = Database::getConnection();
$projects = $db->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Web Deployer</title>
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
            <h1>Projects</h1>
            <a href="/create.php" class="btn primary-btn">+ New Project</a>
        </div>
        
        <div class="glass-panel table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No projects deployed yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="/project.php?id=<?= $project['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                            <?= htmlspecialchars($project['name']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= htmlspecialchars($project['domain']) ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        <?= $project['ssl_enabled'] ? 'SSL Active' : 'Live' ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($project['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="http<?= $project['ssl_enabled'] ? 's' : '' ?>://<?= htmlspecialchars($project['domain']) ?>" target="_blank" class="btn primary-btn btn-sm">Visit</a>
                                        <form method="POST" action="/delete.php" onsubmit="return confirm('Are you sure you want to delete this project?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrf()) ?>">
                                            <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                            <button type="submit" class="btn danger-btn btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
