<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

use Deployer\Database;

$db = Database::getConnection();
try {
    $db->exec("ALTER TABLE projects ADD COLUMN root_dir TEXT DEFAULT '/'");
    echo "Migration successful: Added root_dir to projects.\n";
} catch (\Exception $e) {
    echo "Migration skipped or failed: " . $e->getMessage() . "\n";
}
