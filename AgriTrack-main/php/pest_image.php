<?php
require '../php/db.php';

$conn = getDbConnection();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT Image FROM pest_diseases WHERE PestDiseaseID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row || !$row['Image']) {
    // fallback placeholder for NO ERRORS NYAHAHA
    header('Content-Type: image/png');
    readfile(__DIR__ . '/placeholder.png');
    exit;
}

$mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $row['Image']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($row['Image']));
header('Cache-Control: public, max-age=86400'); // cache for 1 day
echo $row['Image'];