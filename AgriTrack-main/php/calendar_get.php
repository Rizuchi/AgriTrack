<?php
require_once 'require_user_session.php';
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

$userId = $_SESSION['UserID'];
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$conn = getDbConnection();

// Tasks this month
$stmt = $conn->prepare(
    "SELECT CalendarID, TaskType, StartDate, EndDate, Status
     FROM calendar
     WHERE UserID = ? AND StartDate BETWEEN ? AND ?
     ORDER BY StartDate ASC"
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
       AND pc.Status != 'Harvested'
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

echo json_encode([
    'success' => true,
    'year' => $year,
    'month' => $month,
    'tasks' => $tasks,
    'notes' => $notes,
    'harvests' => $harvests,
]);