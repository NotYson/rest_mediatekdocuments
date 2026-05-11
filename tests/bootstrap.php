<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../src');
$dotenv->load();

// Sur macOS, "localhost" résout vers le socket Unix de MAMP ; on force TCP pour les tests CLI.
if (($_ENV['BDD_SERVER'] ?? '') === 'localhost') {
    $_ENV['BDD_SERVER'] = '127.0.0.1';
}

// Makes relative include_once paths within src/ files resolve correctly
set_include_path(__DIR__ . '/../src' . PATH_SEPARATOR . get_include_path());

require_once __DIR__ . '/../src/Connexion.php';
require_once __DIR__ . '/../src/AccessBDD.php';
require_once __DIR__ . '/../src/MyAccessBDD.php';
require_once __DIR__ . '/../src/Controle.php';
