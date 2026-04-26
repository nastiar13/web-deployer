<?php
// setup.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

use Deployer\Database;

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(SITES_DIR)) {
    mkdir(SITES_DIR, 0755, true);
}
if (!is_dir(NGINX_AVAILABLE_DIR)) {
    mkdir(NGINX_AVAILABLE_DIR, 0755, true);
}
if (!is_dir(NGINX_ENABLED_DIR)) {
    mkdir(NGINX_ENABLED_DIR, 0755, true);
}
if (!is_dir(__DIR__ . '/public')) {
    mkdir(__DIR__ . '/public', 0755, true);
}

$db = Database::getConnection();

// Create projects table
$db->exec("
    CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        domain TEXT UNIQUE NOT NULL,
        folder_path TEXT NOT NULL,
        ssl_enabled INTEGER DEFAULT 0,
        git_repo TEXT DEFAULT NULL,
        root_dir TEXT DEFAULT '/',
        api_proxy_url TEXT DEFAULT NULL,
        project_type TEXT DEFAULT 'static',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Create admin table
$db->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL
    )
");

// Insert default admin if none exists
$stmt = $db->query("SELECT COUNT(*) FROM admins");
$count = $stmt->fetchColumn();

if ($count == 0) {
    // default: admin / admin123
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->execute(['admin', $hash]);
    echo "Default admin created: admin / admin123\n";
} else {
    echo "Admin user already exists.\n";
}

echo "Setup completed successfully.\n";

// Migrate existing databases: add api_proxy_url if missing
try {
    $db->exec("ALTER TABLE projects ADD COLUMN api_proxy_url TEXT DEFAULT NULL");
} catch (\Exception $e) {
    // Column already exists, safe to ignore
}

// Migrate existing databases: add project_type if missing
try {
    $db->exec("ALTER TABLE projects ADD COLUMN project_type TEXT DEFAULT 'static'");
} catch (\Exception $e) {
    // Column already exists, safe to ignore
}
