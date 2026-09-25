<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'User') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This feature is only available to Users.']);
    exit;
}
