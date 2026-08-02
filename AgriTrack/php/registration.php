<?php
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbname = "agritrack";

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
$password = $input['password'] ?? '';
$confirmPassword = $input['confirmPassword'] ?? '';

if ($fname === '' || $lname === '' || $userName === '' || $password === '' || $confirmPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$email = $email === '' ? null : $email;

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$stmt = $conn->prepare("SELECT UserID FROM users WHERE userName = ? OR email = ?");
$stmt->bind_param('ss', $userName, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
    exit;
}

$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$insertStmt = $conn->prepare("INSERT INTO users (lname, fname, userName, password, email, role, accStatus, sessionStatus, isActive) VALUES (?, ?, ?, ?, ?, 'User', 'Active', TRUE, TRUE)");
$insertStmt->bind_param('sssss', $lname, $fname, $userName, $hashedPassword, $email);

if ($insertStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Registration successful.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed.']);
}

$insertStmt->close();
$conn->close();