<?php
/**
 * Inserts a row into `planted_crop` for the logged-in user.
 *
 * require_user_session.php already: starts the session, sets the JSON
 * header, and exits with a 401/403 if the user isn't logged in or isn't
 * role "User" — so by the time we get past it, $_SESSION['UserID'] is safe
 * to use.
 */

require_once 'require_user_session.php';
require_once 'db.php';

$conn = getDbConnection();

$response = ['success' => false, 'message' => ''];

$userId      = $_SESSION['UserID'];
$plantLabel  = trim($_POST['plantLabel'] ?? '');
$cropId      = filter_input(INPUT_POST, 'cropId', FILTER_VALIDATE_INT);
$dateOfPlant = trim($_POST['dateOfPlant'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if ($plantLabel === '' || !$cropId || $dateOfPlant === '') {
    http_response_code(400);
    $response['message'] = 'Punan ang lahat ng kinakailangang detalye.';
    echo json_encode($response);
    exit;
}

// Validate the date format (expects YYYY-MM-DD from the <input type="date">)
$d = DateTime::createFromFormat('Y-m-d', $dateOfPlant);
if (!$d || $d->format('Y-m-d') !== $dateOfPlant) {
    http_response_code(400);
    $response['message'] = 'Hindi wastong petsa ng pagtatanim.';
    echo json_encode($response);
    exit;
}

// Confirm the selected crop actually exists, and pull its harvest window so
// we can compute ExpectedHarvestDate ourselves (the user never types this).
$stmt = $conn->prepare('SELECT MinDaysToHarvest, MaxDaysToHarvest FROM crops WHERE CropID = ?');
$stmt->bind_param('i', $cropId);
$stmt->execute();
$crop = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$crop) {
    http_response_code(400);
    $response['message'] = 'Hindi mahanap ang napiling pananim sa listahan.';
    echo json_encode($response);
    exit;
}

$expectedHarvestDate = null;
if ($crop['MinDaysToHarvest'] !== null && $crop['MaxDaysToHarvest'] !== null) {
    $avgDays = (int) round(((int) $crop['MinDaysToHarvest'] + (int) $crop['MaxDaysToHarvest']) / 2);
    $harvest = clone $d;
    $harvest->modify("+{$avgDays} days");
    $expectedHarvestDate = $harvest->format('Y-m-d');
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        'INSERT INTO planted_crop (UserID, CropID, PlantLabel, DateOfPlant, ExpectedHarvestDate, Status)
         VALUES (?, ?, ?, ?, ?, "Growing")'
    );
    $stmt->bind_param('iisss', $userId, $cropId, $plantLabel, $dateOfPlant, $expectedHarvestDate);
    $stmt->execute();
    $plantedCropId = $stmt->insert_id;
    $stmt->close();

    // Optional: if the user left an initial note, log it against this planting.
    if ($notes !== '') {
        $stmt = $conn->prepare(
            'INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message) VALUES (?, ?, CURDATE(), ?)'
        );
        $stmt->bind_param('iis', $userId, $plantedCropId, $notes);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    $response['success']              = true;
    $response['plantedCropId']        = $plantedCropId;
    $response['expectedHarvestDate']  = $expectedHarvestDate;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    $response['message'] = 'May naganap na error sa pag-save ng pananim.';
}

echo json_encode($response);