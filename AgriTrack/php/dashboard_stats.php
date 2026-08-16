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

// Total Crops = total planted_crop rows for this user
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM planted_crop WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalCrops = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Scheduled Tasks = pending calendar entries for this user
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM calendar WHERE UserID = ? AND Status = 'Pending'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$scheduledTasks = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();

// Active Monitoring and Pest Alerts depend on tables/features (monitoring.html,
// pests, crop_pest_junction) that aren't built yet. Left at 0 for now rather
// than faking numbers -- wire these up once that feature exists.
echo json_encode([
    'success' => true,
    'totalCrops' => $totalCrops,
    'activeMonitoring' => 0,
    'pestAlerts' => 0,
    'scheduledTasks' => $scheduledTasks,
]);
