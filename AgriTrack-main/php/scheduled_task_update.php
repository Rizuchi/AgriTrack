<?php
require_once __DIR__ . '/require_user_session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$taskId = (int) ($input['taskId'] ?? 0);
$userId = (int) ($_SESSION['UserID'] ?? 0);
if ($taskId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid task is required.']);
    exit;
}

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $taskStmt = $conn->prepare(
        "SELECT t.type, t.status, t.plant_id
         FROM tasks t
         JOIN planted_crop pc ON pc.PlantedCropID = t.plant_id AND pc.UserID = t.user_id
         WHERE t.id = ? AND t.user_id = ? AND pc.Status <> 'Archived'
         FOR UPDATE"
    );
    $taskStmt->bind_param('ii', $taskId, $userId);
    $taskStmt->execute();
    $task = $taskStmt->get_result()->fetch_assoc();
    $taskStmt->close();

    if (!$task || $task['status'] === 'Done') {
        $conn->rollback();
        $conn->close();
        http_response_code($task ? 409 : 404);
        echo json_encode(['success' => false, 'message' => $task ? 'Task is already completed.' : 'Task not found.']);
        exit;
    }

    $updateStmt = $conn->prepare("UPDATE tasks SET status = 'Done' WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param('ii', $taskId, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    $message = 'Gawain: Natapos ang gawain - ' . $task['type'];
    $noteStmt = $conn->prepare(
        'INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message)
         VALUES (?, ?, CURDATE(), ?)'
    );
    $plantId = (int) $task['plant_id'];
    $noteStmt->bind_param('iis', $userId, $plantId, $message);
    $noteStmt->execute();
    $noteId = $noteStmt->insert_id;
    $noteStmt->close();

    $conn->commit();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Task marked done and activity note saved.', 'notesId' => $noteId]);
} catch (Throwable $exception) {
    $conn->rollback();
    $conn->close();
    error_log('Scheduled task completion failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Task completion and activity note could not be saved.']);
}
