<?php
require_once __DIR__ . '/../php/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$userId = (int) ($_SESSION['UserID'] ?? 0);
$role = trim((string) ($_SESSION['role'] ?? ''));

if ($userId <= 0) {
    respond(['success' => false, 'message' => 'Admin access required.'], 403);
}

if (strcasecmp($role, 'Admin') !== 0) {
    $roleStmt = $conn->prepare('SELECT role FROM users WHERE UserID = ? LIMIT 1');
    $roleStmt->bind_param('i', $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow || strcasecmp(trim((string) $roleRow['role']), 'Admin') !== 0) {
        respond(['success' => false, 'message' => 'Admin access required.'], 403);
    }
}

function scalar(mysqli $conn, string $query): int
{
    $result = $conn->query($query);
    if (!$result) {
        respond(['success' => false, 'message' => 'Unable to load admin statistics.'], 500);
    }
    return (int) ($result->fetch_row()[0] ?? 0);
}

$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$monthly = array_fill(0, 12, ['planted' => 0, 'tasks' => 0, 'notes' => 0]);
$monthlyQuery = $conn->prepare(
    "SELECT month_number, planted, tasks, notes
     FROM (
         SELECT MONTH(DateOfPlant) AS month_number, COUNT(*) AS planted,
                0 AS tasks, 0 AS notes
         FROM planted_crop
         WHERE YEAR(DateOfPlant) = ?
         GROUP BY MONTH(DateOfPlant)
         UNION ALL
         SELECT MONTH(StartDate), 0, COUNT(*), 0
         FROM calendar
         WHERE YEAR(StartDate) = ?
         GROUP BY MONTH(StartDate)
         UNION ALL
         SELECT MONTH(EntryDate), 0, 0, COUNT(*)
         FROM notes
         WHERE YEAR(EntryDate) = ?
         GROUP BY MONTH(EntryDate)
     ) activity
     ORDER BY month_number"
);
$monthlyQuery->bind_param('iii', $year, $year, $year);
$monthlyQuery->execute();
$monthlyResult = $monthlyQuery->get_result();
while ($row = $monthlyResult->fetch_assoc()) {
    $index = (int) $row['month_number'] - 1;
    if ($index >= 0 && $index < 12) {
        $monthly[$index]['planted'] += (int) $row['planted'];
        $monthly[$index]['tasks'] += (int) $row['tasks'];
        $monthly[$index]['notes'] += (int) $row['notes'];
    }
}
$monthlyQuery->close();

$cropResult = $conn->query(
    "SELECT c.CropName, COUNT(pc.PlantedCropID) AS planted_count
     FROM crops c
     LEFT JOIN planted_crop pc ON pc.CropID = c.CropID
     GROUP BY c.CropID, c.CropName
     ORDER BY planted_count DESC, c.CropName ASC
     LIMIT 8"
);
if (!$cropResult) {
    respond(['success' => false, 'message' => 'Unable to load crop statistics.'], 500);
}
$cropLabels = [];
$cropCounts = [];
while ($row = $cropResult->fetch_assoc()) {
    $cropLabels[] = $row['CropName'];
    $cropCounts[] = (int) $row['planted_count'];
}

$statusResult = $conn->query(
    "SELECT accStatus, COUNT(*) AS total
     FROM users
     WHERE role = 'User'
     GROUP BY accStatus"
);
if (!$statusResult) {
    respond(['success' => false, 'message' => 'Unable to load user statistics.'], 500);
}
$userStatus = ['Active' => 0, 'Inactive' => 0];
while ($row = $statusResult->fetch_assoc()) {
    $status = (string) $row['accStatus'];
    if (array_key_exists($status, $userStatus)) {
        $userStatus[$status] = (int) $row['total'];
    }
}

respond([
    'success' => true,
    'year' => $year,
    'summary' => [
        'users' => scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'User'"),
        'activeUsers' => scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'User' AND accStatus = 'Active'"),
        'crops' => scalar($conn, 'SELECT COUNT(*) FROM crops'),
        'plantedCrops' => scalar($conn, 'SELECT COUNT(*) FROM planted_crop'),
        'readyToHarvest' => scalar($conn, "SELECT COUNT(*) FROM planted_crop WHERE Status = 'Ready to Harvest'"),
        'pests' => scalar($conn, 'SELECT COUNT(*) FROM pest_diseases'),
    ],
    'monthly' => $monthly,
    'cropDistribution' => ['labels' => $cropLabels, 'data' => $cropCounts],
    'userStatus' => $userStatus,
]);