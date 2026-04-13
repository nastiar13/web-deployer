<?php
// src/Auth.php
namespace Deployer;

use Deployer\Database;
use PDO;

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(defined('SESSION_NAME') ? SESSION_NAME : 'deployer_sess');
            session_set_cookie_params([
                'httponly' => true,
                'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function login(string $username, string $password): bool {
        self::startSession();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $username;
            // prevent session fixation
            session_regenerate_id(true);
            return true;
        }

        return false;
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        self::startSession();
        return isset($_SESSION['admin_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            if (!headers_sent()) {
                header("Location: /login.php");
            } else {
                echo "<script>window.location.href='/login.php';</script>";
            }
            exit;
        }
    }

    public static function generateCsrf(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(string $token): bool {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
