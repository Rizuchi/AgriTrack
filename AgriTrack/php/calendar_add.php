<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$taskType = trim($input['taskType'] ?? '');
$startDate = trim($input['startDate'] ?? '');
$endDate = trim($input['endDate'] ?? '') ?: null;
$plantedCropId = isset($input['plantedCropId']) && $input['plantedCropId'] !== ''
    ? (int) $input['plantedCropId']
    : null;

if ($taskType === '' || $startDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task type and start date are required.']);
    exit;
}

if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid start date format, expected YYYY-MM-DD.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

$stmt = $conn->prepare(
    "INSERT INTO calendar (UserID, PlantedCropID, TaskType, StartDate, EndDate, Status)
     VALUES (?, ?, ?, ?, ?, 'Pending')"
);
$stmt->bind_param('iisss', $userId, $plantedCropId, $taskType, $startDate, $endDate);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Task added.', 'calendarId' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add task.']);
}

$stmt->close();
$conn->close();
