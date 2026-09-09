<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid crop ID.');
}

$conn = getDbConnection();

$stmt = $conn->prepare("SELECT Image FROM crops WHERE CropID = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($imageBlob);

if ($stmt->fetch() && $imageBlob !== null && $imageBlob !== '') {
    $stmt->close();

    $finfo = finfo_open();
    $mime  = finfo_buffer($finfo, $imageBlob, FILEINFO_MIME_TYPE);
    finfo_close($finfo);

    header('Content-Type: ' . ($mime ?: 'image/jpeg'));
    header('Content-Length: ' . strlen($imageBlob));
    header('Cache-Control: public, max-age=86400');
    echo $imageBlob;
    exit;
}

$stmt->close();


http_response_code(404);
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
   . '<rect width="100%" height="100%" fill="#e5e5e5"/>'
   . '<text x="50%" y="50%" text-anchor="middle" fill="#999" '
   . 'font-family="sans-serif" font-size="14">No Image</text></svg>';
