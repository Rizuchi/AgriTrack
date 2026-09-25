<?php
require_once 'encryption.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/smtp_mailer.php';

session_start();

$servername = env('DB_HOST', 'localhost');
$dbUsername = env('DB_USERNAME', 'root');
$dbPassword = env('DB_PASSWORD', '');
$dbname = env('DB_NAME', 'agritrack');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$fname = trim($input['fname'] ?? '');
$lname = trim($input['lname'] ?? '');
$userName = trim($input['userName'] ?? '');
$email = trim($input['email'] ?? '');
$contact = trim($input['contact'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirmPassword'] ?? '';
$isAdminRequest = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';

if ($fname === '' || $lname === '' || $userName === '' || (!$isAdminRequest && ($email === '' || $contact === '')) || $password === '' || $confirmPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!$isAdminRequest && !preg_match('/^\+639\d{9}$/', $contact)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Philippine mobile number.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if (!$isAdminRequest && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$encryptedUserName = encryptDeterministic($userName);

$stmt = $conn->prepare("SELECT UserID FROM users WHERE userName = ?");
$stmt->bind_param('s', $encryptedUserName);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Username already exists.']);
    exit;
}

$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$encryptedLname = encryptData($lname);
$encryptedFname = encryptData($fname);
$encryptedEmail = encryptData($email);
$encryptedContact = encryptData($contact);

$verificationToken = $isAdminRequest ? null : bin2hex(random_bytes(32));
$verificationHash = $verificationToken === null ? null : hash('sha256', $verificationToken);
$verificationExpires = $verificationToken === null ? null : gmdate('Y-m-d H:i:s', time() + 86400);

if ($isAdminRequest) {
    $insertStmt = $conn->prepare("INSERT INTO users (lname, fname, userName, password, email, contact, role, accStatus, sessionStatus, isActive) VALUES (?, ?, ?, ?, ?, ?, 'User', 'Active', TRUE, TRUE)");
    $insertStmt->bind_param('ssssss', $encryptedLname, $encryptedFname, $encryptedUserName, $hashedPassword, $encryptedEmail, $encryptedContact);
} else {
    $insertStmt = $conn->prepare("INSERT INTO users (lname, fname, userName, password, email, contact, role, accStatus, sessionStatus, isActive, email_verification_token_hash, email_verification_expires_at) VALUES (?, ?, ?, ?, ?, ?, 'User', 'Inactive', TRUE, FALSE, ?, ?)");
    $insertStmt->bind_param('ssssssss', $encryptedLname, $encryptedFname, $encryptedUserName, $hashedPassword, $encryptedEmail, $encryptedContact, $verificationHash, $verificationExpires);
}

if ($insertStmt->execute()) {
    if ($isAdminRequest) {
        echo json_encode(['success' => true, 'message' => 'Registration successful.']);
    } else {
        $appUrl = rtrim((string) env('APP_URL', 'http://localhost/AgriTrack-main'), '/');
        $verifyUrl = $appUrl . '/php/verify_email.php?token=' . urlencode($verificationToken);
        $mailer = new SmtpMailer();
        $emailSent = $mailer->send(
            $email,
            'Verify your AgriTrack account',
            '<p>Hello ' . htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Click the button below to verify your AgriTrack email address. This link expires in 24 hours.</p>'
                . '<p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 20px;background:#b7db3c;color:#1b2a22;text-decoration:none;border-radius:6px;">Verify Email Address</a></p>'
                . '<p>If you did not create this account, you can ignore this email.</p>'
        );

        if (!$emailSent) {
            $userId = $insertStmt->insert_id;
            $deleteStmt = $conn->prepare('DELETE FROM users WHERE UserID = ?');
            $deleteStmt->bind_param('i', $userId);
            $deleteStmt->execute();
            $deleteStmt->close();
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Unable to send the verification email. Check SMTP settings.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Registration successful. Check your email to verify your account.']);
        }
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed.']);
}

$insertStmt->close();
$conn->close();