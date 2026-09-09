<?php
// php/get_crop_info.php?id=CROP_ID
//
// Reference-crop detail endpoint (for recommendation.html's "Tingnan ang
// Detalye" button). This is separate from get_crop.php, which is for a
// user's own planted_crop log entries — this one reads from the master
// `crops` table (Singkamas, Palay, etc.), not a specific planting.

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$conn = getDbConnection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid crop ID.']);
    exit;
}

$monthsPH = [
    1 => 'Enero', 2 => 'Pebrero', 3 => 'Marso', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Hunyo', 7 => 'Hulyo', 8 => 'Agosto',
    9 => 'Setyembre', 10 => 'Oktubre', 11 => 'Nobyembre', 12 => 'Disyembre',
];

function formatSeasonRange(string $start, string $end, array $monthsPH): string
{
    $startMonth = $monthsPH[(int) date('n', strtotime($start))];
    $endMonth   = $monthsPH[(int) date('n', strtotime($end))];
    return $startMonth === $endMonth ? $startMonth : "{$startMonth}–{$endMonth}";
}

$stmt = $conn->prepare(
    "SELECT c.CropID, c.SeasonID, c.CropName, c.EnglishName, c.CropType,
            c.Reason, c.MinDaysToHarvest, c.MaxDaysToHarvest,
            s.SeasonType, s.StartDate AS SeasonStart, s.EndDate AS SeasonEnd
     FROM crops c
     LEFT JOIN seasons s ON c.SeasonID = s.SeasonID
     WHERE c.CropID = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Crop not found.']);
    exit;
}

$plantingPeriod = ($row['SeasonStart'] && $row['SeasonEnd'])
    ? formatSeasonRange($row['SeasonStart'], $row['SeasonEnd'], $monthsPH)
    : 'Taon-taon';

// Pests/diseases known to affect this crop (crops <-> pest_diseases,
// linked through pest_affected_crop)
$pestStmt = $conn->prepare(
    "SELECT pd.PestDiseaseID, pd.Name, pd.Type, pd.Info, pd.Symptoms, pd.ManagementTips
     FROM pest_affected_crop pac
     JOIN pest_diseases pd ON pac.PestDiseaseID = pd.PestDiseaseID
     WHERE pac.CropID = ?
     ORDER BY pd.Type, pd.Name"
);
$pestStmt->bind_param('i', $id);
$pestStmt->execute();
$pestResult = $pestStmt->get_result();

$pests = [];
while ($p = $pestResult->fetch_assoc()) {
    $pests[] = [
        'id'             => (int) $p['PestDiseaseID'],
        'name'           => $p['Name'],
        'type'           => $p['Type'], // 'Pest' or 'Disease'
        'info'           => $p['Info'],
        'symptoms'       => $p['Symptoms'],
        'managementTips' => $p['ManagementTips'],
    ];
}
$pestStmt->close();

echo json_encode([
    'success' => true,
    'crop' => [
        'cropId'           => (int) $row['CropID'],
        'cropName'         => $row['CropName'],
        'englishName'      => $row['EnglishName'],
        'cropType'         => $row['CropType'],
        'reason'           => $row['Reason'],
        'minDaysToHarvest' => (int) $row['MinDaysToHarvest'],
        'maxDaysToHarvest' => (int) $row['MaxDaysToHarvest'],
        'plantingPeriod'   => $plantingPeriod,
        'imageUrl'         => '../php/crop_image.php?id=' . (int) $row['CropID'],
        'pestsAndDiseases' => $pests,
    ],
]);