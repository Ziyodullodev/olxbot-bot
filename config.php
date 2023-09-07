<?php

require_once __DIR__ . '/vendor/autoload.php'; // Load the library


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Load the .env.example file in the current directory
$dotenv->load();

// Access environment variables
$dbHost = $_ENV['DB_HOST'];
$dbUser = $_ENV['DB_USER'];
$dbPassword = $_ENV['DB_PASSWORD'];
$dbName = $_ENV['DB_NAME'];
$token = $_ENV['TOKEN'];

return [
    'token' => $token,
    'dbHost' => $dbHost,
    'dbUser' => $dbUser,
    'dbPassword' => $dbPassword,
    'dbName' => $dbName,
    'admin_id' => '848796050',
    'channel_id' => '848796050',
];


