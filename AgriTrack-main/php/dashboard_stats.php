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

// Total crops
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM planted_crop WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalCrops = (int) $stmt->get_result()->fetch_assoc()['total'];
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
    'activeMonitoring' => 0,
    'pestAlerts' => 0,
    'scheduledTasks' => $scheduledTasks,
]);
