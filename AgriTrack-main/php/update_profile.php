<?php

require_once 'require_user_session.php';
require_once 'db.php';
require_once 'encryption.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Hindi ka naka-log in.']);
    exit;
}

$userId = (int) $_SESSION['UserID'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$firstName = trim($input['firstName'] ?? '');
$lastName  = trim($input['lastName'] ?? '');
$email     = trim($input['email'] ?? '');
$contact   = trim($input['contact'] ?? '');
$userName  = trim($input['username'] ?? ($input['userName'] ?? ''));

if ($firstName === '' || $lastName === '' || $userName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kailangang punan ang Pangalan, Apelyido, at Username.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hindi wastong email address.']);
    exit;
}

try {
    $conn = getDbConnection();

    $encUserNameDet = encryptDeterministic($userName);

    $check = $conn->prepare("SELECT UserID FROM users WHERE userName = ? AND UserID != ? LIMIT 1");
    $check->bind_param("si", $encUserNameDet, $userId);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ginagamit na ng ibang user ang username na ito.']);
        $check->close();
        exit;
    }
    $check->close();

    $encFirst   = encryptData($firstName);
    $encLast    = encryptData($lastName);
    $encEmail   = $email !== '' ? encryptData($email) : null;
    $encContact = $contact !== '' ? encryptData($contact) : null;

    $update = $conn->prepare(
        "UPDATE users
         SET fname = ?, lname = ?, userName = ?, email = ?, contact = ?, updatedAt = NOW()
         WHERE UserID = ?"
    );
    $update->bind_param("sssssi", $encFirst, $encLast, $encUserNameDet, $encEmail, $encContact, $userId);
    $update->execute();
    $update->close();


    $_SESSION['fname']    = $firstName;
    $_SESSION['lname']    = $lastName;
    $_SESSION['userName'] = $userName;

    $stmt = $conn->prepare("SELECT updatedAt FROM users WHERE UserID = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'message'   => 'Matagumpay na nai-save ang iyong mga pagbabago.',
        'updatedAt' => $row['updatedAt'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'May error sa server.']);
}