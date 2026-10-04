<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/require_user_session.php';
require_once 'session_data_cache.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['UserID'] ?? 0);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    $conn->close();
    exit;
}

$cacheKey = "monitoring:{$userId}";
$cachedResponse = getSessionDataCache($cacheKey);
if ($cachedResponse !== null) {
    echo json_encode($cachedResponse);
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare("
    SELECT pc.PlantedCropID, pc.CropID, pc.DateOfPlant, pc.ExpectedHarvestDate, pc.Status,
           c.CropName, c.EnglishName,
           (SELECT n.Message
            FROM notes n
            WHERE n.PlantedCropID = pc.PlantedCropID
            ORDER BY n.TimeCreated DESC, n.NotesID DESC
            LIMIT 1) AS LatestNote
    FROM planted_crop pc
    JOIN crops c ON pc.CropID = c.CropID
    WHERE pc.UserID = ? AND pc.Status <> 'Archived'
    ORDER BY pc.DateOfPlant DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$plantedCrops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function extractSegment($message, $label) {
    if (preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(\||$)/u', $message, $m)) {
        return trim($m[1]);
    }
    return null;
}

function growthStage($percent) {
    if ($percent >= 75) return 'Namumunga';
    if ($percent >= 50) return 'Namumulaklak';
    if ($percent >= 25) return 'Paglaki';
    return 'Punla';
}

$stageLabels = [
    'seedling' => 'Punla',
    'growth' => 'Paglaki',
    'flowering' => 'Namumulaklak',
    'fruiting' => 'Namumunga',
    'ready' => 'Handa nang Anihin',
];
$stageProgress = [
    'seedling' => 12,
    'growth' => 36,
    'flowering' => 60,
    'fruiting' => 82,
    'ready' => 100,
];
$today = new DateTimeImmutable('today');

foreach ($plantedCrops as &$crop) {
    $planted = new DateTimeImmutable($crop['DateOfPlant']);
    $expected = $crop['ExpectedHarvestDate'] ? new DateTimeImmutable($crop['ExpectedHarvestDate']) : null;
    if ($expected && $expected > $planted) {
        $totalDays = (int) $planted->diff($expected)->days;
        $elapsedDays = (int) $planted->diff($today)->format('%r%a');
        $timeProgress = $totalDays > 0
            ? min(95, max(0, (int) round(($elapsedDays / $totalDays) * 95)))
            : 0;
    } else {
        $timeProgress = 0;
    }

    $observedStage = extractSegment($crop['LatestNote'] ?? '', 'Yugto');
    $observedStage = isset($stageProgress[$observedStage]) ? $observedStage : null;
    if ($crop['Status'] === 'Harvested' || $crop['Status'] === 'Ready to Harvest' || $observedStage === 'ready') {
        $percent = 100;
        $crop['GrowthStage'] = $stageLabels['ready'];
        $crop['GrowthStageCode'] = 'ready';
        $crop['ProgressMethod'] = 'Yugto ng paglago na iniulat';
    } elseif ($observedStage !== null) {
        $percent = (int) round(($timeProgress * 0.6) + ($stageProgress[$observedStage] * 0.4));
        $crop['GrowthStage'] = $stageLabels[$observedStage];
        $crop['GrowthStageCode'] = $observedStage;
        $crop['ProgressMethod'] = 'Petsa at iniulat na yugto';
    } else {
        $percent = $timeProgress;
        $crop['GrowthStage'] = growthStage($timeProgress);
        $crop['GrowthStageCode'] = $timeProgress >= 75 ? 'fruiting'
            : ($timeProgress >= 50 ? 'flowering' : ($timeProgress >= 25 ? 'growth' : 'seedling'));
        $crop['ProgressMethod'] = 'Tantiyang batay sa petsa';
    }

    $crop['Progress'] = (int) $percent;
    $condition = null;
    $pest = 'Wala';

    if ($crop['LatestNote'] !== null) {
        $condition = extractSegment($crop['LatestNote'], 'Kalagayan');
        $pestSegment = extractSegment($crop['LatestNote'], 'Peste/Sakit');
        if ($pestSegment) {
            $pest = $pestSegment;
        }
    }

    $crop['Condition'] = $condition ?? 'Walang Tala';
    $crop['PestOrDisease'] = $pest;
    unset($crop['LatestNote']);
}
unset($crop);

$conn->close();

$response = ['success' => true, 'data' => $plantedCrops];
setSessionDataCache($cacheKey, $response);
echo json_encode($response);