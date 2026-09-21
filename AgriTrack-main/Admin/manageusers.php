<?php
require_once __DIR__ . '/../php/encryption.php';
require_once __DIR__ . '/../php/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

$sessionUserId = (int) ($_SESSION['UserID'] ?? 0);
$sessionRole = trim((string) ($_SESSION['role'] ?? ''));

if ($sessionUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$conn = getDbConnection();

if (strcasecmp($sessionRole, 'Admin') !== 0) {
    $roleStmt = $conn->prepare('SELECT role FROM users WHERE UserID = ? LIMIT 1');
    $roleStmt->bind_param('i', $sessionUserId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow || strcasecmp(trim((string) $roleRow['role']), 'Admin') !== 0) {
        $conn->close();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT UserID, fname, lname, email, contact, accStatus, updatedAt
     FROM users
     WHERE role = 'User'
     ORDER BY UserID DESC"
);

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load users.']);
    exit;
}

$result = $stmt->get_result();
$users = [];

while ($user = $result->fetch_assoc()) {
    $users[] = [
        'userId' => (int) $user['UserID'],
        'firstName' => decryptData($user['fname']) ?? '',
        'lastName' => decryptData($user['lname']) ?? '',
        'email' => decryptData($user['email']) ?? '',
        'contact' => decryptData($user['contact']) ?? '',
        'status' => $user['accStatus'],
        'updatedAt' => $user['updatedAt'],
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
