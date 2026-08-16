<?php
// Shared database connection. Include this instead of repeating the
// mysqli boilerplate in every endpoint (same credentials your
// login.php / register.php already use).

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
