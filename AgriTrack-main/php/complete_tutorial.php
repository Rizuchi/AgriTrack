<?php
require_once __DIR__ . '/require_user_session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$conn = getDbConnection();
$userId = (int) $_SESSION['UserID'];
$stmt = $conn->prepare('UPDATE users SET sessionStatus = 0 WHERE UserID = ?');
$stmt->bind_param('i', $userId);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tutorial status could not be saved.']);
    exit;
}

$_SESSION['sessionStatus'] = 0;
echo json_encode(['success' => true]);