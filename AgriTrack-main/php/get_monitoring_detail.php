<?php
require_once 'require_user_session.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

$plantedCropId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userId = (int) ($_SESSION['UserID'] ?? 0);

if ($plantedCropId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare(
    "SELECT pc.PlantedCropID, pc.CropID, pc.PlantLabel, pc.DateOfPlant,
            pc.ExpectedHarvestDate, pc.Status, c.CropName, c.EnglishName,
            c.CropType
     FROM planted_crop pc
     JOIN crops c ON c.CropID = pc.CropID
     WHERE pc.PlantedCropID = ? AND pc.UserID = ?"
);
$stmt->bind_param('ii', $plantedCropId, $userId);
$stmt->execute();
$crop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$crop) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Crop not found.']);
    $conn->close();
    exit;
}


$noteStmt = $conn->prepare(
    "SELECT Message, EntryDate, TimeCreated
     FROM notes
     WHERE UserID = ? AND PlantedCropID = ?
     ORDER BY TimeCreated DESC
     LIMIT 1"
);
$noteStmt->bind_param('ii', $userId, $plantedCropId);
$noteStmt->execute();
$note = $noteStmt->get_result()->fetch_assoc();
$noteStmt->close();

$condition = 'Walang Tala';
$pest = 'Wala';

if ($note) {
    if (preg_match('/Kalagayan:\s*(.*?)(\s*\||$)/iu', $note['Message'], $condMatch)) {
        $conditionText = trim($condMatch[1]);
        if ($conditionText !== '') {
            $condition = $conditionText;
        }
    }
    if (preg_match('/Peste\/Sakit:\s*(.*?)(\s*\||$)/iu', $note['Message'], $pestMatch)) {
        $pestText = trim($pestMatch[1]);
        if ($pestText !== '') {
            $pest = $pestText;
        }
    }
}


$listStmt = $conn->prepare(
    "SELECT pd.PestDiseaseID, pd.Name, pd.Type
     FROM pest_affected_crop pac
     JOIN pest_diseases pd ON pd.PestDiseaseID = pac.PestDiseaseID
     WHERE pac.CropID = ?
     ORDER BY pd.Type, pd.Name"
);
$listStmt->bind_param('i', $crop['CropID']);
$listStmt->execute();
$listResult = $listStmt->get_result();
$availablePests = [];
while ($row = $listResult->fetch_assoc()) {
    $availablePests[] = [
        'name' => $row['Name'],
        'type' => $row['Type'],
    ];
}
$listStmt->close();


$selectedPest = null;
if ($pest !== 'Wala') {
    $tipStmt = $conn->prepare(
        "SELECT pd.Name, pd.Type, pd.Info, pd.Symptoms, pd.ManagementTips
         FROM pest_diseases pd
         JOIN pest_affected_crop pac ON pac.PestDiseaseID = pd.PestDiseaseID
         WHERE pac.CropID = ? AND pd.Name = ?
         LIMIT 1"
    );
    $tipStmt->bind_param('is', $crop['CropID'], $pest);
    $tipStmt->execute();
    $tipRow = $tipStmt->get_result()->fetch_assoc();
    $tipStmt->close();

    if ($tipRow) {
        $selectedPest = [
            'name' => $tipRow['Name'],
            'type' => $tipRow['Type'],
            'info' => $tipRow['Info'],
            'symptoms' => $tipRow['Symptoms'],
            'managementTips' => $tipRow['ManagementTips'],
        ];
    }
}

$conn->close();

$crop['ImageURL'] = '../php/crop_image.php?id=' . (int) $crop['CropID'];
$crop['Condition'] = $condition;
$crop['PestOrDisease'] = $pest;

echo json_encode([
    'success' => true,
    'crop' => $crop,
    'availablePests' => $availablePests,
    'selectedPest' => $selectedPest,
]);