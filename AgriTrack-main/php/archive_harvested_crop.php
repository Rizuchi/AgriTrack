<?php
require_once __DIR__ . '/require_user_session.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$plantedCropId = (int) ($input['plantedCropId'] ?? 0);
$userId = (int) ($_SESSION['UserID'] ?? 0);

if ($plantedCropId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
    exit;
}

$conn = getDbConnection();
$findStmt = $conn->prepare(
    'SELECT Status FROM planted_crop WHERE PlantedCropID = ? AND UserID = ?'
);
$findStmt->bind_param('ii', $plantedCropId, $userId);
$findStmt->execute();
$crop = $findStmt->get_result()->fetch_assoc();
$findStmt->close();

if (!$crop) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Crop not found.']);
    $conn->close();
    exit;
}

if ($crop['Status'] !== 'Archived') {
    $archiveStmt = $conn->prepare(
        "UPDATE planted_crop SET Status = 'Archived'
         WHERE PlantedCropID = ? AND UserID = ? AND Status <> 'Archived'"
    );
    $archiveStmt->bind_param('ii', $plantedCropId, $userId);
    if (!$archiveStmt->execute() || $archiveStmt->affected_rows !== 1) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to archive this crop.']);
        $archiveStmt->close();
        $conn->close();
        exit;
    }
    $archiveStmt->close();
}

$conn->close();
echo json_encode(['success' => true]);