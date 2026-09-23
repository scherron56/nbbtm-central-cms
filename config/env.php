<?php
// Load environment variables from the project root .env file with vlucas/phpdotenv.
// Include this the same way you include config/db.php:
//     require_once __DIR__ . '/../config/env.php';
// Safe to require from multiple entry points; phpdotenv will not overwrite
// variables that are already loaded into $_ENV/$_SERVER.

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
