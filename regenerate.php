<?php
// regenerate.php
// Rebuilds all Nginx and Traefik configs from the database on container boot.
// Called by entrypoint.sh after setup.php.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Nginx.php';
require_once __DIR__ . '/src/Traefik.php';

use Deployer\Database;
use Deployer\Nginx;
use Deployer\Traefik;

$db = Database::getConnection();
$projects = $db->query("SELECT * FROM projects")->fetchAll(PDO::FETCH_ASSOC);

if (empty($projects)) {
    echo "No projects found, skipping config regeneration.\n";
    exit(0);
}

foreach ($projects as $project) {
    $name       = $project['name'];
    $domain     = $project['domain'];
    $folderPath = $project['folder_path'];
    $rootDir    = $project['root_dir'] ?? '/';
    $apiProxy   = $project['api_proxy_url'] ?? null;

    echo "Regenerating config for: {$name} ({$domain})\n";
    Nginx::generateConfig($name, $domain, $folderPath, $rootDir, $apiProxy);
    Traefik::generateConfig($name, $domain);
}

echo "Config regeneration complete.\n";
