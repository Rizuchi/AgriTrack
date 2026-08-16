<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$calendarId = isset($input['calendarId']) ? (int) $input['calendarId'] : 0;

if ($calendarId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid calendarId.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

$stmt = $conn->prepare("DELETE FROM calendar WHERE CalendarID = ? AND UserID = ?");
$stmt->bind_param('ii', $calendarId, $userId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Task deleted.']);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Task not found.']);
}

$stmt->close();
$conn->close();
