<?php
// public/logout.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Auth.php';

use Deployer\Auth;

Auth::logout();
header("Location: /login.php");
exit;
