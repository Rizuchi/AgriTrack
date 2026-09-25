<?php
require_once __DIR__ . '/db.php';

$token = trim((string) ($_GET['token'] ?? ''));
$hash = $token === '' ? '' : hash('sha256', $token);
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT UserID FROM users WHERE email_verification_token_hash = ? AND email_verification_expires_at > UTC_TIMESTAMP() LIMIT 1');
$stmt->bind_param('s', $hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(400);
    echo '<h1>Verification link is invalid or expired.</h1><p>Please register again or contact support.</p>';
    exit;
}

$update = $conn->prepare("UPDATE users SET accStatus = 'Active', isActive = TRUE, email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE UserID = ?");
$update->bind_param('i', $user['UserID']);
$update->execute();
$update->close();
$conn->close();

echo '<h1>Email verified successfully.</h1><p>Your AgriTrack account is ready. <a href="../html/login.html">Log in</a></p>';
