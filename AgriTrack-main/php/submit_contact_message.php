<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp_mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$firstName = trim((string) ($input['firstName'] ?? ''));
$lastName = trim((string) ($input['lastName'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

if ($firstName === '' || $lastName === '' || $email === '' || $message === ''
    || strlen($firstName) > 100 || strlen($lastName) > 100
    || strlen($email) > 254 || strlen($message) > 10000
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide valid contact details and a message.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['UserID']) ? (int) $_SESSION['UserID'] : null;

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare(
        'INSERT INTO contact_messages (UserID, first_name, last_name, email, message) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $firstName, $lastName, $email, $message);
    $saved = $stmt->execute();
    $stmt->close();
    $conn->close();
} catch (Throwable $exception) {
    error_log('AgriTrack contact message save failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save your message right now.']);
    exit;
}

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save your message right now.']);
    exit;
}

$config = require __DIR__ . '/smtp_config.php';
$recipient = trim((string) $config['contact_recipient']);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$emailHtml = '<h2>New AgriTrack contact message</h2>'
    . '<p><strong>From:</strong> ' . $escape($firstName . ' ' . $lastName) . '</p>'
    . '<p><strong>Email:</strong> ' . $escape($email) . '</p>'
    . '<p><strong>Message:</strong><br>' . nl2br($escape($message)) . '</p>';
$emailSent = $recipient !== ''
    && (new SmtpMailer())->send($recipient, 'AgriTrack contact message', $emailHtml, $email);

echo json_encode([
    'success' => true,
    'emailSent' => $emailSent,
    'message' => $emailSent ? 'Message sent.' : 'Message saved; email notification failed.',
]);