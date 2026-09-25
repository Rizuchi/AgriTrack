<?php
require_once __DIR__ . '/env.php';

function getDbConnection(): mysqli
{
    $servername = env('DB_HOST', 'localhost');
    $dbUsername = env('DB_USERNAME', 'root');
    $dbPassword = env('DB_PASSWORD', '');
    $dbname = env('DB_NAME', 'agritrack');
    $port = (int) env('DB_PORT', 3306);

    $conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname, $port);

    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}