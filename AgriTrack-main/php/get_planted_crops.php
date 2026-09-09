<?php
require_once 'require_user_session.php';
require_once 'db.php';

$conn = getDbConnection();

$userId = $_SESSION['UserID'];

$stmt = $conn->prepare(
    'SELECT pc.PlantedCropID, pc.PlantLabel, pc.CropID, pc.DateOfPlant,
            pc.ExpectedHarvestDate, pc.Status,
            c.CropName, c.EnglishName, c.CropType
     FROM planted_crop pc
     JOIN crops c ON c.CropID = pc.CropID
     WHERE pc.UserID = ?
     ORDER BY pc.DateOfPlant DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$crops = [];
while ($row = $result->fetch_assoc()) {
    $crops[] = $row;
}
$stmt->close();

echo json_encode($crops);