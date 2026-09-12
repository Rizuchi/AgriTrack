<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

// CROPS STATS
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM planted_crop WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalCrops = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// HINDI ISASAMA YUNG HARVESTED NA 
$stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM planted_crop
         WHERE UserID = ? AND (Status IS NULL OR Status <> 'Harvested')"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$activeMonitoring = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// PESTS STATS
$stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM notes n
         WHERE n.UserID = ?
             AND LOWER(n.Message) LIKE '%kalagayan:%'
             AND LOWER(n.Message) LIKE '%peste%'
             AND NOT EXISTS (
                     SELECT 1
                     FROM notes newer
                     WHERE newer.UserID = n.UserID
                         AND (newer.PlantedCropID = n.PlantedCropID
                                    OR (newer.PlantedCropID IS NULL AND n.PlantedCropID IS NULL))
                         AND newer.TimeCreated > n.TimeCreated
             )"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$pestAlerts = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pending tasks
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM calendar WHERE UserID = ? AND Status = 'Pending'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$scheduledTasks = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'totalCrops' => $totalCrops,
    'activeMonitoring' => $activeMonitoring,
    'pestAlerts' => $pestAlerts,
    'scheduledTasks' => $scheduledTasks,
]);
