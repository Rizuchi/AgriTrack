<?php
function getDbConnection(): mysqli
{
    $servername = "localhost";
    $dbUsername = "root";
    $dbPassword = "";
    $dbname = "agritrack";

    $conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    return $conn;
}
