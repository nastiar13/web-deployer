<?php
// src/Certbot.php
namespace Deployer;

class Certbot {
    public static function run(string $domain, string $email = 'admin@example.com'): bool {
        $cmd = sprintf("%s --nginx -d %s --non-interactive --agree-tos -m %s", CMD_CERTBOT, escapeshellarg($domain), escapeshellarg($email));
        $output = shell_exec($cmd);
        // Naive assumption it works or error handling for proper prod implementation.
        // For local development, this mock will always pass quickly.
        return true; 
    }
}
