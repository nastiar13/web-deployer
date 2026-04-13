<?php
// src/Traefik.php
namespace Deployer;

class Traefik {
    public static function generateConfig(string $projectName, string $domain): void {
        if (!defined('TRAEFIK_DYNAMIC_DIR')) return;
        
        if (!is_dir(TRAEFIK_DYNAMIC_DIR)) {
            @mkdir(TRAEFIK_DYNAMIC_DIR, 0755, true);
        }

        $config = "http:\n"
                . "  routers:\n"
                . "    deployer_{$projectName}:\n"
                . "      rule: \"Host(`{$domain}`)\"\n"
                . "      service: deployer-static\n"
                . "      tls:\n"
                . "        certResolver: myresolver\n"
                . "  services:\n"
                . "    deployer-static:\n"
                . "      loadBalancer:\n"
                . "        servers:\n"
                . "          - url: \"http://web-deployer-app:80\"\n";
        
        $filePath = TRAEFIK_DYNAMIC_DIR . '/' . $projectName . '.yml';
        file_put_contents($filePath, $config);
    }

    public static function removeConfig(string $projectName): void {
        if (!defined('TRAEFIK_DYNAMIC_DIR')) return;
        $filePath = TRAEFIK_DYNAMIC_DIR . '/' . $projectName . '.yml';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
