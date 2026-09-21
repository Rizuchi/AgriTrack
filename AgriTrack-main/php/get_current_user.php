<?php
require_once 'require_user_session.php';
echo json_encode([
    'success' => true,
    'fname' => $_SESSION['fname'] ?? '',
    'lname' => $_SESSION['lname'] ?? '',
]);
