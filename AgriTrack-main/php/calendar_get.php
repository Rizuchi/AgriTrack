<?php
require_once 'require_user_session.php';
require_once __DIR__ . '/session_data_cache.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month.']);
    exit;
}

$userId = (int) $_SESSION['UserID'];
$cacheKey = "calendar:{$userId}:{$year}:{$month}";
$cachedResponse = getSessionDataCache($cacheKey);
if ($cachedResponse !== null) {
    echo json_encode($cachedResponse);
    exit;
}

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$conn = getDbConnection();
$stmt = $conn->prepare(
    "SELECT cal.CalendarID, cal.TaskType, cal.StartDate, cal.EndDate, cal.Status,
            pc.PlantLabel, c.CropName
     FROM calendar cal
     LEFT JOIN planted_crop pc ON pc.PlantedCropID = cal.PlantedCropID AND pc.UserID = cal.UserID
     LEFT JOIN crops c ON c.CropID = pc.CropID
     WHERE cal.UserID = ? AND cal.StartDate BETWEEN ? AND ?
     ORDER BY cal.StartDate ASC"
);
$stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
$stmt->execute();
$tasksResult = $stmt->get_result();
$tasks = [];
while ($row = $tasksResult->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();


$stmt = $conn->prepare(
    "SELECT NotesID, EntryDate, Message, TimeCreated
     FROM notes
        WHERE UserID = ? AND EntryDate BETWEEN ? AND ?
     ORDER BY EntryDate ASC"
);
$stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
$stmt->execute();
$notesResult = $stmt->get_result();
$notes = [];
while ($row = $notesResult->fetch_assoc()) {
    $notes[] = $row;
}
$stmt->close();


$stmt = $conn->prepare(
    "SELECT pc.PlantedCropID, pc.PlantLabel, pc.ExpectedHarvestDate,
            c.CropName, c.EnglishName
     FROM planted_crop pc
     JOIN crops c ON c.CropID = pc.CropID
     WHERE pc.UserID = ? AND pc.ExpectedHarvestDate BETWEEN ? AND ?
    AND pc.Status NOT IN ('Harvested', 'Archived')
     ORDER BY pc.ExpectedHarvestDate ASC"
);
$stmt->bind_param('iss', $userId, $monthStart, $monthEnd);
$stmt->execute();
$harvestResult = $stmt->get_result();
$harvests = [];
while ($row = $harvestResult->fetch_assoc()) {
    $harvests[] = $row;
}
$stmt->close();

$conn->close();

$response = [
    'success' => true,
    'year' => $year,
    'month' => $month,
    'tasks' => $tasks,
    'notes' => $notes,
    'harvests' => $harvests,
];
setSessionDataCache($cacheKey, $response);

echo json_encode($response);