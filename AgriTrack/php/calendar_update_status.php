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
$status = trim($input['status'] ?? '');

if ($calendarId <= 0 || !in_array($status, ['Pending', 'Completed'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid calendarId or status.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

// Scope the update to the logged-in user so no one can edit another user's task.
$stmt = $conn->prepare("UPDATE calendar SET Status = ? WHERE CalendarID = ? AND UserID = ?");
$stmt->bind_param('sii', $status, $calendarId, $userId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Task updated.']);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Task not found.']);
}

$stmt->close();
$conn->close();
