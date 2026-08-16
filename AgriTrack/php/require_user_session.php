<?php
// Include at the top of any endpoint that should only be reachable by
// a logged-in User. Relies on $_SESSION['UserID'] / $_SESSION['role']
// set in login.php.

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// This feature is for Users only (per the requirement), not Admin/SuperAdmin.
if (($_SESSION['role'] ?? '') !== 'User') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This feature is only available to Users.']);
    exit;
}
