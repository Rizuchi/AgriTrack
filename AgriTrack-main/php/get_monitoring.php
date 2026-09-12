<?php
require '../php/db.php';
require '../php/require_user_session.php'; 

header('Content-Type: application/json');

$conn = getDbConnection();

$userId = (int) ($_SESSION['UserID'] ?? 0);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    $conn->close();
    exit;
}


$stmt = $conn->prepare("
    SELECT pc.PlantedCropID, pc.CropID, pc.DateOfPlant, pc.ExpectedHarvestDate, pc.Status,
           c.CropName, c.EnglishName
    FROM planted_crop pc
    JOIN crops c ON pc.CropID = c.CropID
    WHERE pc.UserID = ?
    ORDER BY pc.DateOfPlant DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$plantedCrops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$noteStmt = $conn->prepare("
    SELECT Message
    FROM notes
    WHERE PlantedCropID = ?
    ORDER BY TimeCreated DESC
    LIMIT 1
");

function extractSegment($message, $label) {
    if (preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(\||$)/u', $message, $m)) {
        return trim($m[1]);
    }
    return null;
}

function growthStage($percent) {
    if ($percent >= 100) return 'Handa nang Anihin';
    if ($percent >= 75)  return 'Namumunga';
    if ($percent >= 50)  return 'Namumulaklak';
    if ($percent >= 25)  return 'Paglaki';
    return 'Bagong Tanim';
}

$today = new DateTime();

foreach ($plantedCrops as &$crop) {
    $planted = new DateTime($crop['DateOfPlant']);
    $expected = $crop['ExpectedHarvestDate'] ? new DateTime($crop['ExpectedHarvestDate']) : null;

    // Progress % based on elapsed time vs total expected duration
    if ($expected && $expected > $planted) {
        $totalDays = $planted->diff($expected)->days;
        $elapsedDays = $planted->diff($today)->days;
        $percent = $totalDays > 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;
    } else {
        $percent = 0;
    }

    if ($crop['Status'] === 'Harvested') {
        $percent = 100;
    }

    $crop['Progress'] = (int) $percent;
    $crop['GrowthStage'] = growthStage($percent);

    // Latest note lookup
    $noteStmt->bind_param("i", $crop['PlantedCropID']);
    $noteStmt->execute();
    $noteRow = $noteStmt->get_result()->fetch_assoc();

    $condition = null;
    $pest = 'Wala';

    if ($noteRow) {
        $condition = extractSegment($noteRow['Message'], 'Kalagayan');
        $pestSegment = extractSegment($noteRow['Message'], 'Peste/Sakit');
        if ($pestSegment) {
            $pest = $pestSegment;
        }
    }

    $crop['Condition'] = $condition ?? 'Walang Tala';
    $crop['PestOrDisease'] = $pest;
}
unset($crop);

$noteStmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $plantedCrops]);