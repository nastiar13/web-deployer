<?php
// src/Nginx.php
namespace Deployer;

class Nginx
{
    public static function generateConfig(string $projectName, string $domain, string $folderPath, string $rootDir = '/', ?string $apiProxyUrl = null): void
    {
        // Nginx expects forward slashes, even on Windows
        $normalizedPath = str_replace('\\', '/', $folderPath);
        $finalRoot = $normalizedPath . ($rootDir === '/' ? '' : '/' . trim($rootDir, '/'));

        $config = "server {\n"
            . "    listen 80;\n"
            . "    server_name {$domain};\n\n"
            . "    root {$finalRoot};\n"
            . "    index index.html;\n\n"
            . "    location / {\n"
            . "        try_files \$uri \$uri.html \$uri/ /index.html;\n"
            . "    }\n";

        // Proxy /api/ to an external backend — mirrors Vite's server.proxy config.
        // Set api_proxy_url on the project to enable, e.g. http://api.example.com
        if (!empty($apiProxyUrl)) {
            $proxyUrl = rtrim($apiProxyUrl, '/');
            $config .= "\n"
                . "    location /api/ {\n"
                . "        proxy_pass {$proxyUrl}/;\n"
                . "        proxy_set_header Host \$host;\n"
                . "        proxy_set_header X-Real-IP \$remote_addr;\n"
                . "        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n"
                . "        proxy_set_header X-Forwarded-Proto \$scheme;\n"
                . "    }\n";
        }

        $config .= "}\n";

        $availablePath = NGINX_AVAILABLE_DIR . '/' . $projectName;
        if (!is_dir(NGINX_AVAILABLE_DIR)) {
            @mkdir(NGINX_AVAILABLE_DIR, 0755, true);
        }
        file_put_contents($availablePath, $config);

        $enabledPath = NGINX_ENABLED_DIR . '/' . $projectName;
        if (!is_dir(NGINX_ENABLED_DIR)) {
            @mkdir(NGINX_ENABLED_DIR, 0755, true);
        }
        if (IS_LOCAL_DEV) {
            // Windows mock: direct copy
            copy($availablePath, $enabledPath);
        }
        elseif (IS_DOCKER) {
            // Docker: write directly to sites-enabled (no symlink needed)
            file_put_contents($enabledPath, $config);
        }
        else {
            // Linux native: symlink, remove stale first
            if (is_link($enabledPath) || file_exists($enabledPath)) {
                unlink($enabledPath);
            }
            shell_exec(CMD_LN . " -s " . escapeshellarg($availablePath) . " " . escapeshellarg($enabledPath));
        }
    }

    public static function reload(): void
    {
        shell_exec(CMD_NGINX_RELOAD);
    }

    public static function removeConfig(string $projectName): void
    {
        $availablePath = NGINX_AVAILABLE_DIR . '/' . $projectName;
        $enabledPath = NGINX_ENABLED_DIR . '/' . $projectName;
        if (file_exists($enabledPath)) {
            unlink($enabledPath);
        }
        if (file_exists($availablePath)) {
            unlink($availablePath);
        }
    }
}
