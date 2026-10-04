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

function selectedIds(string $key): ?array
{
	$values = $_POST[$key] ?? [];
	if (!is_array($values)) {
		return null;
	}

	$ids = [];
	foreach ($values as $value) {
		$id = filter_var($value, FILTER_VALIDATE_INT);
		if ($id === false || $id <= 0) {
			return null;
		}
		$ids[] = $id;
	}

	return array_values(array_unique($ids));
}

function uploadImage(): ?string
{
	if (!isset($_FILES['cropImage']) || $_FILES['cropImage']['error'] === UPLOAD_ERR_NO_FILE) {
		return null;
	}

	if ($_FILES['cropImage']['error'] !== UPLOAD_ERR_OK) {
		respond(['success' => false, 'message' => 'Image upload failed.'], 400);
	}

	if ($_FILES['cropImage']['size'] > 5 * 1024 * 1024) {
		respond(['success' => false, 'message' => 'Image must be 5 MB or smaller.'], 400);
	}

	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cropImage']['tmp_name']);
	$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	if (!in_array($mime, $allowed, true)) {
		respond(['success' => false, 'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed.'], 400);
	}

	$image = file_get_contents($_FILES['cropImage']['tmp_name']);
	if ($image === false) {
		respond(['success' => false, 'message' => 'Unable to read the uploaded image.'], 400);
	}

	return $image;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$result = $conn->query(
		"SELECT c.CropID, c.CropName, c.EnglishName, c.CropType, c.Reason,
				c.MinDaysToHarvest, c.MaxDaysToHarvest, c.SeasonID,
				s.SeasonType
		 FROM crops c
		 LEFT JOIN seasons s ON s.SeasonID = c.SeasonID
		 ORDER BY c.CropName ASC"
	);

	if (!$result) {
		respond(['success' => false, 'message' => 'Unable to load crops.'], 500);
	}

	$crops = [];
	while ($row = $result->fetch_assoc()) {
		$row['CropID'] = (int) $row['CropID'];
		$row['SeasonID'] = $row['SeasonID'] === null ? null : (int) $row['SeasonID'];
		$row['MinDaysToHarvest'] = $row['MinDaysToHarvest'] === null ? null : (int) $row['MinDaysToHarvest'];
		$row['MaxDaysToHarvest'] = $row['MaxDaysToHarvest'] === null ? null : (int) $row['MaxDaysToHarvest'];
		$row['imageUrl'] = '../php/crop_image.php?id=' . $row['CropID'];
		$crops[] = $row;
	}
	$result->free();

	$pestResult = $conn->query('SELECT PestDiseaseID, Name, Type FROM pest_diseases ORDER BY Name ASC');
	if (!$pestResult) {
		respond(['success' => false, 'message' => 'Unable to load pests and diseases.'], 500);
	}
	$pests = [];
	while ($row = $pestResult->fetch_assoc()) {
		$pests[] = [
			'id' => (int) $row['PestDiseaseID'],
			'name' => $row['Name'],
			'type' => $row['Type'],
		];
	}
	$pestResult->free();

	$linkResult = $conn->query(
		"SELECT pac.CropID, p.PestDiseaseID, p.Name, p.Type
		 FROM pest_affected_crop pac
		 JOIN pest_diseases p ON p.PestDiseaseID = pac.PestDiseaseID
		 ORDER BY p.Name ASC"
	);
	if (!$linkResult) {
		respond(['success' => false, 'message' => 'Unable to load crop relationships.'], 500);
	}
	$cropLinks = [];
	while ($row = $linkResult->fetch_assoc()) {
		$cropId = (int) $row['CropID'];
		$cropLinks[$cropId][] = [
			'id' => (int) $row['PestDiseaseID'],
			'name' => $row['Name'],
			'type' => $row['Type'],
		];
	}
	$linkResult->free();
	foreach ($crops as &$crop) {
		$crop['pests'] = $cropLinks[$crop['CropID']] ?? [];
	}
	unset($crop);

	respond(['success' => true, 'crops' => $crops, 'pests' => $pests]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$cropId = filter_input(INPUT_POST, 'cropId', FILTER_VALIDATE_INT) ?: 0;
$cropName = postText('cropName');
$englishName = postText('englishName');
$cropType = postText('cropType');
$reason = postText('reason');
$seasonId = filter_input(INPUT_POST, 'seasonId', FILTER_VALIDATE_INT);
$minDays = filter_input(INPUT_POST, 'minDaysToHarvest', FILTER_VALIDATE_INT);
$maxDays = filter_input(INPUT_POST, 'maxDaysToHarvest', FILTER_VALIDATE_INT);
$pestIds = selectedIds('pestIds');
$image = uploadImage();

if ($pestIds === null) {
	respond(['success' => false, 'message' => 'Invalid pest or disease selection.'], 422);
}

if ($cropName === '') {
	respond(['success' => false, 'message' => 'Crop name is required.'], 422);
}

if (($minDays !== null && $minDays < 0) || ($maxDays !== null && $maxDays < 0)) {
	respond(['success' => false, 'message' => 'Harvest days cannot be negative.'], 422);
}

if ($minDays !== null && $maxDays !== null && $minDays > $maxDays) {
	respond(['success' => false, 'message' => 'Minimum harvest days cannot exceed maximum days.'], 422);
}

	if ($pestIds) {
		$selectedPestIds = implode(',', $pestIds);
		$validResult = $conn->query('SELECT PestDiseaseID FROM pest_diseases WHERE PestDiseaseID IN (' . $selectedPestIds . ')');
		if (!$validResult) {
			respond(['success' => false, 'message' => 'Unable to validate pest and disease selection.'], 500);
		}
		$validIds = array_map('intval', array_column($validResult->fetch_all(MYSQLI_ASSOC), 'PestDiseaseID'));
		$validResult->free();
		if (count($validIds) !== count($pestIds)) {
			respond(['success' => false, 'message' => 'One or more selected pests or diseases no longer exist.'], 422);
		}
	}

$conn->begin_transaction();
try {
	if ($cropId > 0) {
		if ($image !== null) {
			$stmt = $conn->prepare(
				"UPDATE crops
				 SET CropName = ?, EnglishName = NULLIF(?, ''), CropType = NULLIF(?, ''),
					 SeasonID = NULLIF(?, 0), Reason = NULLIF(?, ''),
					 MinDaysToHarvest = ?, MaxDaysToHarvest = ?, Image = ?
				 WHERE CropID = ?"
			);
			$stmt->bind_param('sssisiisi', $cropName, $englishName, $cropType, $seasonId, $reason, $minDays, $maxDays, $image, $cropId);
		} else {
			$stmt = $conn->prepare(
				"UPDATE crops
				 SET CropName = ?, EnglishName = NULLIF(?, ''), CropType = NULLIF(?, ''),
					 SeasonID = NULLIF(?, 0), Reason = NULLIF(?, ''),
					 MinDaysToHarvest = ?, MaxDaysToHarvest = ?
				 WHERE CropID = ?"
			);
			$stmt->bind_param('sssisiii', $cropName, $englishName, $cropType, $seasonId, $reason, $minDays, $maxDays, $cropId);
		}
		$message = 'Crop updated successfully.';
	} else {
		$stmt = $conn->prepare(
			"INSERT INTO crops
			 (SeasonID, CropName, EnglishName, CropType, Image, Reason, MinDaysToHarvest, MaxDaysToHarvest)
			 VALUES (NULLIF(?, 0), ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?)"
		);
		$stmt->bind_param('isssssii', $seasonId, $cropName, $englishName, $cropType, $image, $reason, $minDays, $maxDays);
		$message = 'Crop added successfully.';
	}

	if (!$stmt || !$stmt->execute()) {
		throw new RuntimeException('Unable to save crop.');
	}
	$savedCropId = $cropId ?: $conn->insert_id;
	$stmt->close();

	$deleteLinks = $conn->prepare('DELETE FROM pest_affected_crop WHERE CropID = ?');
	$deleteLinks->bind_param('i', $savedCropId);
	if (!$deleteLinks->execute()) {
		throw new RuntimeException('Unable to update crop relationships.');
	}
	$deleteLinks->close();

	if ($pestIds) {
		$link = $conn->prepare('INSERT INTO pest_affected_crop (PestDiseaseID, CropID) VALUES (?, ?)');
		foreach ($pestIds as $pestId) {
			$link->bind_param('ii', $pestId, $savedCropId);
			if (!$link->execute()) {
				throw new RuntimeException('Unable to save crop relationships.');
			}
		}
		$link->close();
	}
	$conn->commit();
	respond(['success' => true, 'message' => $message, 'cropId' => $savedCropId]);
} catch (Throwable $error) {
	$conn->rollback();
	respond(['success' => false, 'message' => 'Unable to save crop and its pest relationships.'], 500);
}
