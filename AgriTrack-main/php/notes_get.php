<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$entryDate = trim($_GET['date'] ?? '');
$plantedCropId = isset($_GET['plantedCropId']) && $_GET['plantedCropId'] !== ''
    ? (int) $_GET['plantedCropId']
    : null;

if ($entryDate === '' || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

if ($plantedCropId !== null) {
    $cropStmt = $conn->prepare('SELECT PlantedCropID FROM planted_crop WHERE PlantedCropID = ? AND UserID = ?');
    $cropStmt->bind_param('ii', $plantedCropId, $userId);
    $cropStmt->execute();
    $ownsCrop = $cropStmt->get_result()->fetch_assoc();
    $cropStmt->close();
    if (!$ownsCrop) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'That crop does not belong to you.']);
        $conn->close();
        exit;
    }
}

if ($plantedCropId === null) {
    $stmt = $conn->prepare(
        "SELECT NotesID, Message, TimeCreated
         FROM notes
         WHERE UserID = ? AND EntryDate = ? AND PlantedCropID IS NULL
         ORDER BY TimeCreated ASC"
    );
    $stmt->bind_param('is', $userId, $entryDate);
} else {
    $stmt = $conn->prepare(
        "SELECT NotesID, Message, TimeCreated
         FROM notes
         WHERE UserID = ? AND EntryDate = ? AND PlantedCropID = ?
         ORDER BY TimeCreated ASC"
    );
    $stmt->bind_param('isi', $userId, $entryDate, $plantedCropId);
}
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'date' => $entryDate, 'notes' => $notes]);