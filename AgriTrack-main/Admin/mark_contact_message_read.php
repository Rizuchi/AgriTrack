<?php
require_once __DIR__ . '/../php/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$adminUserId = (int) ($_SESSION['UserID'] ?? 0);
if ($adminUserId <= 0) {
    respond(['success' => false, 'message' => 'Admin access required.'], 403);
}

$conn = getDbConnection();
$role = trim((string) ($_SESSION['role'] ?? ''));
if (strcasecmp($role, 'Admin') !== 0) {
    $roleStmt = $conn->prepare('SELECT role FROM users WHERE UserID = ? LIMIT 1');
    $roleStmt->bind_param('i', $adminUserId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow || strcasecmp(trim((string) $roleRow['role']), 'Admin') !== 0) {
        $conn->close();
        respond(['success' => false, 'message' => 'Admin access required.'], 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    respond(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$contactId = is_array($input) ? (filter_var($input['contactId'] ?? 0, FILTER_VALIDATE_INT) ?: 0) : 0;
if ($contactId <= 0) {
    $conn->close();
    respond(['success' => false, 'message' => 'A valid contact record is required.'], 422);
}

$existsStmt = $conn->prepare('SELECT ContactID FROM contact_messages WHERE ContactID = ? LIMIT 1');
if (!$existsStmt) {
    $conn->close();
    respond(['success' => false, 'message' => 'Unable to verify the contact message.'], 500);
}
$existsStmt->bind_param('i', $contactId);
$existsStmt->execute();
$exists = $existsStmt->get_result()->fetch_assoc();
$existsStmt->close();
if (!$exists) {
    $conn->close();
    respond(['success' => false, 'message' => 'Contact message not found.'], 404);
}

$stmt = $conn->prepare(
    'INSERT INTO contact_message_status (ContactID, is_read)
     SELECT ContactID, 1 FROM contact_messages WHERE ContactID = ?
     ON DUPLICATE KEY UPDATE is_read = 1'
);
if (!$stmt) {
    $conn->close();
    respond(['success' => false, 'message' => 'Unable to update message status. Apply the contact messages migration and try again.'], 500);
}

$stmt->bind_param('i', $contactId);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    respond(['success' => false, 'message' => 'Unable to update message status.'], 500);
}
$stmt->close();
$conn->close();

respond(['success' => true]);
