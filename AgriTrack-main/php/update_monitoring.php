<?php
require_once 'require_user_session.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

const MONITORING_COOLDOWN_SECONDS = 4;

function replaceMonitoringSegments(string $message, string $condition, string $pest, string $additionalNote): string
{
    $updates = [
        'Kalagayan' => $condition,
        'Peste/Sakit' => strcasecmp($pest, 'Wala') === 0 ? '' : $pest,
        'Tala' => $additionalNote,
    ];
    $segments = [];
    $seen = [];

    foreach (explode('|', $message) as $segment) {
        $segment = trim($segment);
        if ($segment === '') continue;

        $colonPos = strpos($segment, ':');
        $label = $colonPos === false ? '' : trim(substr($segment, 0, $colonPos));
        if (!array_key_exists($label, $updates)) {
            $segments[] = $segment;
            continue;
        }

        $seen[$label] = true;
        if ($updates[$label] !== '') {
            $segments[] = $label . ': ' . $updates[$label];
        }
    }

    foreach ($updates as $label => $value) {
        if ($value !== '' && empty($seen[$label])) {
            $segments[] = $label . ': ' . $value;
        }
    }

    return implode(' | ', $segments);
}

$input = json_decode(file_get_contents('php://input'), true);
$plantedCropId = (int) ($input['plantedCropId'] ?? 0);
$condition = trim($input['condition'] ?? '');
$pest = trim($input['pest'] ?? 'Wala');
$additionalNote = trim($input['additionalNote'] ?? '');
$userId = (int) ($_SESSION['UserID'] ?? 0);

if ($plantedCropId <= 0 || $condition === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Condition and crop are required.']);
    exit;
}


if (!isset($_SESSION['lastMonitoringSave']) || !is_array($_SESSION['lastMonitoringSave'])) {
    $_SESSION['lastMonitoringSave'] = [];
}
$lastSave = $_SESSION['lastMonitoringSave'][$plantedCropId] ?? 0;
$elapsed = time() - $lastSave;
if ($elapsed < MONITORING_COOLDOWN_SECONDS) {
    $retryAfter = MONITORING_COOLDOWN_SECONDS - $elapsed;
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Masyadong mabilis. Paki-hintay ng {$retryAfter}s bago mag-save ulit.",
        'retryAfter' => $retryAfter,
    ]);
    exit;
}

$conn = getDbConnection();
$verifyStmt = $conn->prepare(
    'SELECT PlantedCropID FROM planted_crop WHERE PlantedCropID = ? AND UserID = ?'
);
$verifyStmt->bind_param('ii', $plantedCropId, $userId);
$verifyStmt->execute();
$exists = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Crop not found.']);
    $conn->close();
    exit;
}

$parts = ['Kalagayan: ' . $condition];
if ($pest !== '' && strcasecmp($pest, 'Wala') !== 0) {
    $parts[] = 'Peste/Sakit: ' . $pest;
}
if ($additionalNote !== '') {
    $parts[] = 'Tala: ' . $additionalNote;
}
$message = implode(' | ', $parts);

$todayStmt = $conn->prepare(
    'SELECT NotesID, Message FROM notes
     WHERE UserID = ? AND PlantedCropID = ? AND EntryDate = CURDATE()
     ORDER BY TimeCreated DESC LIMIT 1'
);
$todayStmt->bind_param('ii', $userId, $plantedCropId);
$todayStmt->execute();
$todayNote = $todayStmt->get_result()->fetch_assoc();
$todayStmt->close();

if ($todayNote) {
    $message = replaceMonitoringSegments($todayNote['Message'], $condition, $pest, $additionalNote);
    $saveStmt = $conn->prepare('UPDATE notes SET Message = ?, TimeCreated = NOW() WHERE NotesID = ?');
    $saveStmt->bind_param('si', $message, $todayNote['NotesID']);
} else {
    $saveStmt = $conn->prepare(
        'INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message) VALUES (?, ?, CURDATE(), ?)'
    );
    $saveStmt->bind_param('iis', $userId, $plantedCropId, $message);
}

if (!$saveStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save monitoring update.']);
    $saveStmt->close();
    $conn->close();
    exit;
}

$saveStmt->close();
$conn->close();

$_SESSION['lastMonitoringSave'][$plantedCropId] = time();

echo json_encode(['success' => true, 'message' => 'Monitoring update saved.']);