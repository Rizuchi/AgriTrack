<?php
// Crop data endpoint

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$conn = getDbConnection();

$today = date('Y-m-d');

$monthsPH = [
    1 => 'Enero', 2 => 'Pebrero', 3 => 'Marso', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Hunyo', 7 => 'Hulyo', 8 => 'Agosto',
    9 => 'Setyembre', 10 => 'Oktubre', 11 => 'Nobyembre', 12 => 'Disyembre',
];

// Season comparison helper
function isDateWithinSeason(string $today, string $start, string $end): bool
{
    $todayMD = date('md', strtotime($today));
    $startMD = date('md', strtotime($start));
    $endMD   = date('md', strtotime($end));

    if ($startMD <= $endMD) {
        return $todayMD >= $startMD && $todayMD <= $endMD;
    }
    // Wrap-around season
    return $todayMD >= $startMD || $todayMD <= $endMD;
}

function formatSeasonRange(string $start, string $end, array $monthsPH): string
{
    $startMonth = $monthsPH[(int) date('n', strtotime($start))];
    $endMonth   = $monthsPH[(int) date('n', strtotime($end))];
    return $startMonth === $endMonth ? $startMonth : "{$startMonth}–{$endMonth}";
}

// Current season label
$currentSeasonLabel = 'Hindi Matukoy';
$currentSeasonType  = null;

$seasonRes = $conn->query(
    "SELECT SeasonID, SeasonType, StartDate, EndDate
     FROM seasons
     WHERE SeasonType IN ('Wet','Dry')"
);
if ($seasonRes) {
    while ($s = $seasonRes->fetch_assoc()) {
        if (isDateWithinSeason($today, $s['StartDate'], $s['EndDate'])) {
            $currentSeasonType  = $s['SeasonType'];
            $currentSeasonLabel = $s['SeasonType'] === 'Dry'
                ? 'Panahon ng Tag-init/Tag-uyot'
                : 'Panahon ng Tag-ulan';
            break;
        }
    }
}

// Crop query
$onlyCurrent = isset($_GET['current']) && $_GET['current'] == '1';

$sql = "SELECT c.CropID, c.SeasonID, c.CropName, c.EnglishName, c.CropType,
               c.Reason, c.MinDaysToHarvest, c.MaxDaysToHarvest,
               s.SeasonType, s.StartDate AS SeasonStart, s.EndDate AS SeasonEnd
        FROM crops c
        LEFT JOIN seasons s ON c.SeasonID = s.SeasonID
        ORDER BY c.CropName ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$crops = [];

while ($row = $result->fetch_assoc()) {
    $seasonStart = $row['SeasonStart'];
    $seasonEnd   = $row['SeasonEnd'];
    $seasonType  = $row['SeasonType'];

    $isCurrent = false;
    if ($seasonType === 'Both') {
        $isCurrent = true; // Year-round crop
    } elseif ($seasonStart && $seasonEnd) {
        $isCurrent = isDateWithinSeason($today, $seasonStart, $seasonEnd);
    }

    if ($onlyCurrent && !$isCurrent) {
        continue;
    }

    $plantingPeriod = ($seasonStart && $seasonEnd)
        ? formatSeasonRange($seasonStart, $seasonEnd, $monthsPH)
        : 'Taon-taon';

    $crops[] = [
        'cropId'           => (int) $row['CropID'],
        'cropName'         => $row['CropName'],
        'englishName'      => $row['EnglishName'],
        'cropType'         => $row['CropType'],
        'reason'           => $row['Reason'],
        'minDaysToHarvest' => (int) $row['MinDaysToHarvest'],
        'maxDaysToHarvest' => (int) $row['MaxDaysToHarvest'],
        'seasonType'       => $seasonType,
        'plantingPeriod'   => $plantingPeriod,
        'isCurrentSeason'  => $isCurrent,
        'imageUrl'         => '../php/crop_image.php?id=' . (int) $row['CropID'],
    ];
}

echo json_encode([
    'success'             => true,
    'currentDate'         => $today,
    'currentSeasonType'   => $currentSeasonType,
    'currentSeasonLabel'  => $currentSeasonLabel,
    'crops'               => $crops,
]);