<?php
require_once __DIR__ . '/../php/db.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getDbConnection();

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function postText(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function uploadImage(): ?string
{
    if (!isset($_FILES['pestImage']) || $_FILES['pestImage']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['pestImage']['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'Image upload failed.'], 400);
    }

    if ($_FILES['pestImage']['size'] > 5 * 1024 * 1024) {
        respond(['success' => false, 'message' => 'Image must be 5 MB or smaller.'], 400);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['pestImage']['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        respond(['success' => false, 'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed.'], 400);
    }

    $image = file_get_contents($_FILES['pestImage']['tmp_name']);
    if ($image === false) {
        respond(['success' => false, 'message' => 'Unable to read the uploaded image.'], 400);
    }

    return $image;
}

function seasonIdFromText(mysqli $conn, string $season): int
{
    $season = strtolower($season);
    $seasonType = 'Dry';
    if (str_contains($season, 'buong') || str_contains($season, 'both') || str_contains($season, 'taon')) {
        $seasonType = 'Both';
    } elseif (str_contains($season, 'ulan') || str_contains($season, 'wet')) {
        $seasonType = 'Wet';
    }

    $stmt = $conn->prepare('SELECT SeasonID FROM seasons WHERE SeasonType = ? LIMIT 1');
    $stmt->bind_param('s', $seasonType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['SeasonID'] : 0;
}

function findCropId(mysqli $conn, string $cropName): int
{
    if ($cropName === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT CropID FROM crops WHERE CropName = ? OR EnglishName = ? LIMIT 1');
    $stmt->bind_param('ss', $cropName, $cropName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['CropID'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query(
        "SELECT p.PestDiseaseID, p.Name, p.Type, p.Info, p.ManagementTips, p.Symptoms,
                p.SeasonID, s.SeasonType, MD5(p.Image) AS ImageVersion,
                GROUP_CONCAT(DISTINCT c.CropName ORDER BY c.CropName SEPARATOR ', ') AS AffectedCrop
         FROM pest_diseases p
         LEFT JOIN seasons s ON s.SeasonID = p.SeasonID
         LEFT JOIN pest_affected_crop pac ON pac.PestDiseaseID = p.PestDiseaseID
         LEFT JOIN crops c ON c.CropID = pac.CropID
         GROUP BY p.PestDiseaseID
         ORDER BY p.Name ASC"
    );

    if (!$result) {
        respond(['success' => false, 'message' => 'Unable to load pests and diseases.'], 500);
    }

    $pests = [];
    while ($row = $result->fetch_assoc()) {
        $row['PestDiseaseID'] = (int) $row['PestDiseaseID'];
        $row['SeasonID'] = (int) $row['SeasonID'];
        $row['imageUrl'] = '../php/pest_image.php?id=' . $row['PestDiseaseID']
            . '&v=' . rawurlencode((string) $row['ImageVersion']);
        $pests[] = $row;
    }

    respond(['success' => true, 'pests' => $pests]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pestId = filter_input(INPUT_POST, 'pestId', FILTER_VALIDATE_INT) ?: 0;
$name = postText('pestName');
$type = postText('pestType');
$affectedCrop = postText('affectedCrop');
$season = postText('season');
$symptoms = postText('symptoms');
$info = postText('conditions');
$managementTips = postText('recommendedAction');
$seasonId = seasonIdFromText($conn, $season);
$image = uploadImage();

if ($name === '' || !in_array($type, ['Pest', 'Disease'], true)) {
    respond(['success' => false, 'message' => 'Name and type (Pest or Disease) are required.'], 422);
}

if ($symptoms === '' || $info === '' || $managementTips === '') {
    respond(['success' => false, 'message' => 'Symptoms, conditions, and recommended action are required.'], 422);
}

$conn->begin_transaction();
try {
    if ($pestId > 0) {
        if ($image !== null) {
            $stmt = $conn->prepare(
                "UPDATE pest_diseases
                 SET SeasonID = ?, Name = ?, Type = ?, Info = ?, ManagementTips = ?, Symptoms = ?, Image = ?
                 WHERE PestDiseaseID = ?"
            );
            $stmt->bind_param('issssssi', $seasonId, $name, $type, $info, $managementTips, $symptoms, $image, $pestId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE pest_diseases
                 SET SeasonID = ?, Name = ?, Type = ?, Info = ?, ManagementTips = ?, Symptoms = ?
                 WHERE PestDiseaseID = ?"
            );
            $stmt->bind_param('isssssi', $seasonId, $name, $type, $info, $managementTips, $symptoms, $pestId);
        }
        $message = 'Pest or disease updated successfully.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO pest_diseases (SeasonID, Name, Type, Info, ManagementTips, Symptoms, Image)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssss', $seasonId, $name, $type, $info, $managementTips, $symptoms, $image);
        $message = 'Pest or disease added successfully.';
    }

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }

    $savedId = $pestId ?: $conn->insert_id;
    $stmt->close();

    $cropId = findCropId($conn, $affectedCrop);
    $deleteLinks = $conn->prepare('DELETE FROM pest_affected_crop WHERE PestDiseaseID = ?');
    $deleteLinks->bind_param('i', $savedId);
    $deleteLinks->execute();
    $deleteLinks->close();

    if ($cropId > 0) {
        $link = $conn->prepare('INSERT INTO pest_affected_crop (PestDiseaseID, CropID) VALUES (?, ?)');
        $link->bind_param('ii', $savedId, $cropId);
        $link->execute();
        $link->close();
    }

    $conn->commit();
    respond(['success' => true, 'message' => $message, 'pestId' => $savedId]);
} catch (Throwable $error) {
    $conn->rollback();
    respond(['success' => false, 'message' => 'Unable to save pest or disease.'], 500);
}
