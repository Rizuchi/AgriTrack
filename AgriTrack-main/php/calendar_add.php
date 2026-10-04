<?php
require_once 'require_user_session.php';
require_once 'session_data_cache.php';
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

if ($taskType === '' || preg_match('/^[^\r\n]{1,150}$/u', $taskType) !== 1 || $startDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task type and start date are required.']);
    exit;
}

function isValidCalendarDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();
    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

if (!isValidCalendarDate($startDate) || ($endDate !== null && (!isValidCalendarDate($endDate) || $endDate < $startDate))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid task date. Use YYYY-MM-DD and make sure the end date is not before the start date.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();
if ($plantedCropId !== null) {
    $cropStmt = $conn->prepare("SELECT PlantedCropID FROM planted_crop WHERE PlantedCropID = ? AND UserID = ? AND Status NOT IN ('Harvested', 'Archived')");
    $cropStmt->bind_param('ii', $plantedCropId, $userId);
    $cropStmt->execute();
    $cropExists = $cropStmt->get_result()->num_rows > 0;
    $cropStmt->close();
    if (!$cropExists) {
        $conn->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Select an active crop belonging to your account.']);
        exit;
    }
}

$stmt = $conn->prepare(
    "INSERT INTO calendar (UserID, PlantedCropID, TaskType, StartDate, EndDate, Status)
     VALUES (?, ?, ?, ?, ?, 'Pending')"
);
$stmt->bind_param('iisss', $userId, $plantedCropId, $taskType, $startDate, $endDate);

if ($stmt->execute()) {
    clearSessionDataCachePrefix("calendar:{$userId}:");
    echo json_encode(['success' => true, 'message' => 'Task added.', 'calendarId' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add task.']);
}

$stmt->close();
$conn->close();
