<?php
// src/Project.php
namespace Deployer;

class Project {
    public static function create(string $name, string $domain, string $folder_path, ?string $git_repo = null, string $root_dir = '/', ?string $api_proxy_url = null): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO projects (name, domain, folder_path, git_repo, root_dir, api_proxy_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $domain, $folder_path, $git_repo, $root_dir, $api_proxy_url]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function update(int $id, string $name, string $domain, string $folder_path, bool $ssl_enabled, ?string $git_repo = null, string $root_dir = '/', ?string $api_proxy_url = null): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE projects SET name = ?, domain = ?, folder_path = ?, ssl_enabled = ?, git_repo = ?, root_dir = ?, api_proxy_url = ? WHERE id = ?");
        return $stmt->execute([$name, $domain, $folder_path, $ssl_enabled ? 1 : 0, $git_repo, $root_dir, $api_proxy_url, $id]);
    }

    public static function setSslEnabled(int $id, bool $enabled): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE projects SET ssl_enabled = ? WHERE id = ?");
        $stmt->execute([$enabled ? 1 : 0, $id]);
    }
}
