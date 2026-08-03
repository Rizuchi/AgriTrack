<?php
require_once 'encryption.php';

session_start();

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

$userName = trim($input['userName'] ?? '');
$password = $input['password'] ?? '';

if ($userName === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$encryptedUserName = encryptDeterministic($userName);

$stmt = $conn->prepare("SELECT UserID, fname, lname, userName, password, role, accStatus, isActive FROM users WHERE userName = ?");
$stmt->bind_param('s', $encryptedUserName);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password'])) {
    $conn->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

if ($user['accStatus'] !== 'Active' || !$user['isActive']) {
    $conn->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact support.']);
    exit;
}

$updateStmt = $conn->prepare("UPDATE users SET sessionStatus = FALSE WHERE UserID = ?");
$updateStmt->bind_param('i', $user['UserID']);
$updateStmt->execute();
$updateStmt->close();

$conn->close();

$_SESSION['UserID'] = $user['UserID'];
$_SESSION['userName'] = decryptData($user['userName']);
$_SESSION['fname'] = decryptData($user['fname']);
$_SESSION['lname'] = decryptData($user['lname']);
$_SESSION['role'] = $user['role'];

$redirectMap = [
    'SuperAdmin' => 'superadmin.html',
    'Admin'      => 'admin.html',
    'User'       => 'userdashboard.html',
];

$redirectTo = $redirectMap[$user['role']] ?? 'userdashboard.html'; 

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful.',
    'redirect' => $redirectTo,
    'debug_role' => $user['role']
]);