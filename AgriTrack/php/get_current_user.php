<?php
require_once 'require_user_session.php';

// fname was decrypted into the session at login time (see login.php),
// so no DB call or decryption needed here.
echo json_encode([
    'success' => true,
    'fname' => $_SESSION['fname'] ?? '',
    'lname' => $_SESSION['lname'] ?? '',
]);
