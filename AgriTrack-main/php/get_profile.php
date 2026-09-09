<?php
require_once 'require_user_session.php'; 
require_once 'db.php';
require_once 'encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Hindi ka naka-log in.']);
    exit;
}

$userId = (int) $_SESSION['UserID'];

try {
    $conn = getDbConnection();

    $stmt = $conn->prepare(
        "SELECT UserID, fname, lname, userName, email, contact, role, updatedAt
         FROM users WHERE UserID = ? LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Hindi nahanap ang user.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'firstName' => decryptData($user['fname']) ?? '',
            'lastName'  => decryptData($user['lname']) ?? '',
            'userName'  => decryptData($user['userName']) ?? '',
            'email'     => decryptData($user['email']) ?? '',
            'contact'   => decryptData($user['contact']) ?? '',
            'role'      => $user['role'],
            'updatedAt' => $user['updatedAt'], // e.g. 2026-08-17 10:22:00 or null
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'May error sa server.']);
}