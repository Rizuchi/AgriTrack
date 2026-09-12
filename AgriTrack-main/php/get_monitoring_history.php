<?php
require_once 'require_user_session.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$plantedCropId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userId = (int) ($_SESSION['UserID'] ?? 0);

if ($plantedCropId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
    exit;
}

$conn = getDbConnection();


$ownStmt = $conn->prepare(
    'SELECT PlantedCropID FROM planted_crop WHERE PlantedCropID = ? AND UserID = ?'
);
$ownStmt->bind_param('ii', $plantedCropId, $userId);
$ownStmt->execute();
$owns = $ownStmt->get_result()->fetch_assoc();
$ownStmt->close();

if (!$owns) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Crop not found.']);
    $conn->close();
    exit;
}

$stmt = $conn->prepare(
    "SELECT Message, EntryDate, TimeCreated
     FROM notes
     WHERE UserID = ? AND PlantedCropID = ?
     ORDER BY TimeCreated DESC"
);
$stmt->bind_param('ii', $userId, $plantedCropId);
$stmt->execute();
$result = $stmt->get_result();

function extractSegment($message, $label) {
    if (preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(\s*\||$)/u', $message, $m)) {
        return trim($m[1]);
    }
    return null;
}

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'entryDate' => $row['EntryDate'],
        'timeCreated' => $row['TimeCreated'],
        'condition' => extractSegment($row['Message'], 'Kalagayan'),
        'pest' => extractSegment($row['Message'], 'Peste/Sakit'),
        'note' => extractSegment($row['Message'], 'Tala'),
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'history' => $history]);