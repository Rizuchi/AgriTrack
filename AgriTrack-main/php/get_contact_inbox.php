<?php
require_once __DIR__ . '/require_user_session.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$userId = (int) $_SESSION['UserID'];
$conn = getDbConnection();
$stmt = $conn->prepare(
    'SELECT c.ContactID, c.message, c.created_at,
            r.ReplyID, r.reply_text, r.created_at AS reply_created_at
     FROM contact_messages c
     LEFT JOIN contact_message_replies r ON r.ContactID = c.ContactID
     WHERE c.UserID = ?
     ORDER BY c.created_at DESC, c.ContactID DESC, r.created_at ASC, r.ReplyID ASC'
);

if (!$stmt) {
    $databaseError = $conn->error;
    $conn->close();
    error_log('AgriTrack inbox query preparation failed: ' . $databaseError);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load your inbox. Apply the contact messages migration and try again.']);
    exit;
}

$stmt->bind_param('i', $userId);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load your inbox.']);
    exit;
}

$messagesById = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $contactId = (int) $row['ContactID'];
    if (!isset($messagesById[$contactId])) {
        $messagesById[$contactId] = [
            'contactId' => $contactId,
            'message' => $row['message'],
            'createdAt' => $row['created_at'],
            'replies' => [],
        ];
    }
    if ($row['ReplyID'] !== null) {
        $messagesById[$contactId]['replies'][] = [
            'replyId' => (int) $row['ReplyID'],
            'reply' => $row['reply_text'],
            'createdAt' => $row['reply_created_at'],
        ];
    }
}

$stmt->close();
$conn->close();
echo json_encode(['success' => true, 'messages' => array_values($messagesById)], JSON_UNESCAPED_UNICODE);
