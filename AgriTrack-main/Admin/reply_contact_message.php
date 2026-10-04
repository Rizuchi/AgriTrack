<?php
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/smtp_mailer.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDbConnection();
$adminUserId = (int) ($_SESSION['UserID'] ?? 0);
$role = trim((string) ($_SESSION['role'] ?? ''));
if ($adminUserId <= 0) {
    $conn->close();
    respond(['success' => false, 'message' => 'Admin access required.'], 403);
}

if (strcasecmp($role, 'Admin') !== 0) {
    $roleStmt = $conn->prepare('SELECT role FROM users WHERE UserID = ? LIMIT 1');
    $roleStmt->bind_param('i', $adminUserId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow || strcasecmp(trim((string) $roleRow['role']), 'Admin') !== 0) {
        $conn->close();
        respond(['success' => false, 'message' => 'Admin access required.'], 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    respond(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $conn->close();
    respond(['success' => false, 'message' => 'Invalid request.'], 400);
}

$replyId = filter_var($input['replyId'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$message = null;

if ($replyId > 0) {
    $stmt = $conn->prepare(
        'SELECT r.ReplyID, r.ContactID, r.reply_text, r.email_sent,
                c.first_name, c.last_name, c.email, c.message
         FROM contact_message_replies r
         JOIN contact_messages c ON c.ContactID = r.ContactID
         WHERE r.ReplyID = ? LIMIT 1'
    );
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$message) {
        $conn->close();
        respond(['success' => false, 'message' => 'Reply not found.'], 404);
    }
    if ((bool) $message['email_sent']) {
        $conn->close();
        respond(['success' => false, 'message' => 'This reply has already been emailed.'], 409);
    }
    $replyText = (string) $message['reply_text'];
} else {
    $contactId = filter_var($input['contactId'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $replyText = trim((string) ($input['reply'] ?? ''));
    if ($contactId <= 0 || $replyText === '' || strlen($replyText) > 10000) {
        $conn->close();
        respond(['success' => false, 'message' => 'Provide a message and a valid contact record.'], 422);
    }

    $stmt = $conn->prepare(
        'SELECT ContactID, first_name, last_name, email, message
         FROM contact_messages WHERE ContactID = ? LIMIT 1'
    );
    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$message) {
        $conn->close();
        respond(['success' => false, 'message' => 'Contact message not found.'], 404);
    }
    if (!filter_var($message['email'], FILTER_VALIDATE_EMAIL)) {
        $conn->close();
        respond(['success' => false, 'message' => 'The sender email address is invalid.'], 422);
    }

    $replyStmt = $conn->prepare(
        'INSERT INTO contact_message_replies (ContactID, AdminUserID, reply_text) VALUES (?, ?, ?)'
    );
    $messageContactId = (int) $message['ContactID'];
    $replyStmt->bind_param('iis', $messageContactId, $adminUserId, $replyText);
    if (!$replyStmt->execute()) {
        $replyStmt->close();
        $conn->close();
        respond(['success' => false, 'message' => 'Unable to save the reply. Run the contact replies migration and try again.'], 500);
    }
    $replyId = (int) $conn->insert_id;
    $replyStmt->close();
}

$config = require __DIR__ . '/../php/smtp_config.php';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$emailHtml = '<h2>AgriTrack Support</h2>'
    . '<p>Kamusta, ' . $escape((string) $message['first_name']) . '!</p>'
    . '<p>' . nl2br($escape($replyText)) . '</p>'
    . '<hr><p><strong>Orihinal na mensahe:</strong></p>'
    . '<p>' . nl2br($escape((string) $message['message'])) . '</p>';
$replyTo = filter_var($config['contact_recipient'], FILTER_VALIDATE_EMAIL)
    ? $config['contact_recipient']
    : (filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) ? $config['from_email'] : null);
$emailSent = (new SmtpMailer())->send(
    (string) $message['email'],
    'Re: AgriTrack contact message',
    $emailHtml,
    $replyTo
);

if ($emailSent) {
    $updateStmt = $conn->prepare('UPDATE contact_message_replies SET email_sent = 1 WHERE ReplyID = ?');
    $updateStmt->bind_param('i', $replyId);
    $updateStmt->execute();
    $updateStmt->close();
}

$conn->close();
respond([
    'success' => true,
    'replyId' => $replyId,
    'emailSent' => $emailSent,
    'message' => $emailSent
        ? 'Naipadala ang sagot sa email ng nagpadala.'
        : 'Nai-save ang sagot, ngunit hindi ito naipadala. Maaari mo itong subukang ipadala muli.',
]);