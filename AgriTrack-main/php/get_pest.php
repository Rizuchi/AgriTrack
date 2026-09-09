<?php
require '../php/db.php';

header('Content-Type: application/json');

$conn = getDbConnection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($id) {

    $stmt = $conn->prepare("
        SELECT p.PestDiseaseID, p.Name, p.Type, p.Info, p.ManagementTips, p.Symptoms,
               s.SeasonType
        FROM pest_diseases p
        LEFT JOIN seasons s ON p.SeasonID = s.SeasonID
        WHERE p.PestDiseaseID = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pest) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pest/disease not found.']);
        $conn->close();
        exit;
    }

    // Crops affected by this pest/disease
    $cropStmt = $conn->prepare("
        SELECT c.CropID, c.CropName, c.EnglishName
        FROM pest_affected_crop pac
        JOIN crops c ON pac.CropID = c.CropID
        WHERE pac.PestDiseaseID = ?
        ORDER BY c.CropName
    ");
    $cropStmt->bind_param("i", $id);
    $cropStmt->execute();
    $pest['AffectedCrops'] = $cropStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cropStmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'data' => $pest]);
    exit;
}


$sql = "
    SELECT p.PestDiseaseID, p.Name, p.Type, p.Info, p.ManagementTips, p.Symptoms,
           s.SeasonType
    FROM pest_diseases p
    LEFT JOIN seasons s ON p.SeasonID = s.SeasonID
    ORDER BY p.PestDiseaseID
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query failed.']);
    $conn->close();
    exit;
}

$pests = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo json_encode(['success' => true, 'data' => $pests]);