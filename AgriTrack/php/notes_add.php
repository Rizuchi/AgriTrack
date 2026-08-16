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

$entryDate = trim($input['entryDate'] ?? '');
$activityTags = is_array($input['activityTags'] ?? null) ? $input['activityTags'] : [];
$conditionTags = is_array($input['conditionTags'] ?? null) ? $input['conditionTags'] : [];
$weatherTags = is_array($input['weatherTags'] ?? null) ? $input['weatherTags'] : [];
$freeText = trim($input['message'] ?? '');
$plantedCropId = isset($input['plantedCropId']) && $input['plantedCropId'] !== ''
    ? (int) $input['plantedCropId']
    : null;

if ($entryDate === '' || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
    exit;
}

if (empty($activityTags) && empty($conditionTags) && empty($weatherTags) && $freeText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select at least one tag or write a note.']);
    exit;
}

// Combine everything into the single `Message` field the notes table stores.
$parts = [];
if (!empty($activityTags)) {
    $parts[] = 'Gawain: ' . implode(', ', $activityTags);
}
if (!empty($conditionTags)) {
    $parts[] = 'Kalagayan: ' . implode(', ', $conditionTags);
}
if (!empty($weatherTags)) {
    $parts[] = 'Panahon: ' . implode(', ', $weatherTags);
}
if ($freeText !== '') {
    $parts[] = $freeText;
}
$message = implode(' | ', $parts);

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

$stmt = $conn->prepare(
    "INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iiss', $userId, $plantedCropId, $entryDate, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Note saved.', 'notesId' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save note.']);
}

$stmt->close();
$conn->close();
