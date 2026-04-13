<?php
// config.php
define('IS_LOCAL_DEV', getenv('DOCKER_ENV') !== 'true' && PHP_OS_FAMILY === 'Windows');
define('IS_DOCKER', getenv('DOCKER_ENV') === 'true');

define('APP_URL', 'http://localhost:8000'); // Dashboard URL
define('DATA_DIR', IS_DOCKER ? '/var/www/app/data' : __DIR__ . '/data');
define('DB_FILE', DATA_DIR . '/database.sqlite');

// Where static sites go
define('SITES_DIR', IS_DOCKER ? '/var/www/sites' : (IS_LOCAL_DEV ? __DIR__ . '/sites' : '/var/www/sites'));

// Config directories
define('NGINX_AVAILABLE_DIR', IS_DOCKER ? '/etc/nginx/sites-available' : (IS_LOCAL_DEV ? __DIR__ . '/mock_nginx/sites-available' : '/etc/nginx/sites-available'));
define('NGINX_ENABLED_DIR', IS_DOCKER ? '/etc/nginx/sites-enabled' : (IS_LOCAL_DEV ? __DIR__ . '/mock_nginx/sites-enabled' : '/etc/nginx/sites-enabled'));
define('TRAEFIK_DYNAMIC_DIR', IS_DOCKER ? '/etc/traefik/dynamic' : __DIR__ . '/mock_traefik');

// Execution Hooks
define('CMD_NGINX_RELOAD', IS_LOCAL_DEV ? 'echo "mock systemctl reload nginx"' : 'sudo /usr/sbin/nginx -s reload');
define('CMD_CERTBOT', IS_LOCAL_DEV ? 'echo "mock certbot"' : 'sudo /usr/bin/certbot');
define('CMD_LN', IS_LOCAL_DEV ? 'copy' : '/bin/ln'); // Use copy on windows to simulate symlink for our test
define('CMD_RM', IS_LOCAL_DEV ? 'del' : '/bin/rm');

define('SESSION_NAME', 'deployer_sess');
