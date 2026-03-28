<?php

declare(strict_types=1);

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$port = getenv('DB_PORT');

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try 
{
    $pdo = new PDO($dsn, $user, $pass, $options);
} 
catch (PDOException $e) 
{
    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);

    exit;
}
?>