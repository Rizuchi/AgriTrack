<?php
require_once __DIR__ . '/db.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
       . '<rect width="100%" height="100%" fill="#e5e5e5"/>'
       . '<text x="50%" y="50%" text-anchor="middle" fill="#999" '
       . 'font-family="sans-serif" font-size="14">No Image</text></svg>';
    exit;
}

$conn = getDbConnection();

$stmt = $conn->prepare("SELECT Image FROM pest_diseases WHERE PestDiseaseID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row || empty($row['Image'])) {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
       . '<rect width="100%" height="100%" fill="#e5e5e5"/>'
       . '<text x="50%" y="50%" text-anchor="middle" fill="#999" '
       . 'font-family="sans-serif" font-size="14">No Image</text></svg>';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_buffer($finfo, $row['Image']);
finfo_close($finfo);

header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Content-Length: ' . strlen($row['Image']));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $row['Image'];