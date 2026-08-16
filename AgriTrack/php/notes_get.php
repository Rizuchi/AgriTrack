<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$entryDate = trim($_GET['date'] ?? '');

if ($entryDate === '' || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

$stmt = $conn->prepare(
    "SELECT NotesID, Message, TimeCreated
     FROM notes
     WHERE UserID = ? AND EntryDate = ?
     ORDER BY TimeCreated ASC"
);
$stmt->bind_param('is', $userId, $entryDate);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'date' => $entryDate, 'notes' => $notes]);
