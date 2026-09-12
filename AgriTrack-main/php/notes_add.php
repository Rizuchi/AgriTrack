<?php
require_once 'require_user_session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

const NOTE_COOLDOWN_SECONDS = 4;
const TAG_GROUP_ORDER = ['Gawain', 'Kalagayan', 'Peste/Sakit', 'Panahon'];

/**
 * Parses a stored "Tala: ... | Gawain: a, b | Kalagayan: c" string into
 * [freeText, ['Gawain' => ['a','b'], 'Kalagayan' => ['c']]].
 * Mirrors parseNoteMessage() in userdashboard.js.
 */
function parseNoteMessage(string $message): array {
    $segments = array_filter(array_map('trim', explode('|', $message)), fn($s) => $s !== '');
    $freeText = '';
    $tagGroups = [];

    foreach ($segments as $segment) {
        $colonPos = strpos($segment, ':');
        if ($colonPos === false) {
            $freeText = $freeText !== '' ? $freeText . ' ' . $segment : $segment;
            continue;
        }

        $label = trim(substr($segment, 0, $colonPos));
        $value = trim(substr($segment, $colonPos + 1));

        if ($label === 'Tala') {
            $freeText = $freeText !== '' ? $freeText . ' ' . $value : $value;
            continue;
        }

        if (!isset($tagGroups[$label])) {
            $tagGroups[$label] = [];
        }
        foreach (array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== '') as $v) {
            if (!in_array($v, $tagGroups[$label], true)) {
                $tagGroups[$label][] = $v;
            }
        }
    }

    return [$freeText, $tagGroups];
}

/** Rebuilds a "Tala: ... | Gawain: ..." string from parsed parts. */
function buildNoteMessage(string $freeText, array $tagGroups): string {
    $parts = [];
    if ($freeText !== '') {
        $parts[] = 'Tala: ' . $freeText;
    }
    foreach (TAG_GROUP_ORDER as $label) {
        if (!empty($tagGroups[$label])) {
            $parts[] = $label . ': ' . implode(', ', $tagGroups[$label]);
        }
    }
    return implode(' | ', $parts);
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$entryDate = trim($input['entryDate'] ?? '');
$activityTags = is_array($input['activityTags'] ?? null) ? $input['activityTags'] : [];
$conditionTags = is_array($input['conditionTags'] ?? null) ? $input['conditionTags'] : [];
$weatherTags = is_array($input['weatherTags'] ?? null) ? $input['weatherTags'] : [];
$freeText = trim($input['message'] ?? '');
$plantedCropId = isset($input['plantedCropId']) && $input['plantedCropId'] !== ''
    ? (int) $input['plantedCropId']
    : null;

if ($entryDate === '' || !DateTime::createFromFormat('Y-m-d', $entryDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
    exit;
}

if (empty($activityTags) && empty($conditionTags) && empty($weatherTags) && $freeText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select at least one tag or write a note.']);
    exit;
}

$userId = $_SESSION['UserID'];
$conn = getDbConnection();

if ($plantedCropId !== null) {
    $cropStmt = $conn->prepare('SELECT PlantedCropID FROM planted_crop WHERE PlantedCropID = ? AND UserID = ?');
    $cropStmt->bind_param('ii', $plantedCropId, $userId);
    $cropStmt->execute();
    $ownsCrop = $cropStmt->get_result()->fetch_assoc();
    $cropStmt->close();
    if (!$ownsCrop) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'That crop does not belong to you.']);
        $conn->close();
        exit;
    }
}

// I slow repeated saves for the same date and crop.
if (!isset($_SESSION['lastNoteSave']) || !is_array($_SESSION['lastNoteSave'])) {
    $_SESSION['lastNoteSave'] = [];
}
$saveKey = $entryDate . ':' . ($plantedCropId ?? 'general');
$lastSave = $_SESSION['lastNoteSave'][$saveKey] ?? 0;
$elapsed = time() - $lastSave;
if ($elapsed < NOTE_COOLDOWN_SECONDS) {
    $retryAfter = NOTE_COOLDOWN_SECONDS - $elapsed;
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Masyadong mabilis. Paki-hintay ng {$retryAfter}s bago mag-save ulit.",
        'retryAfter' => $retryAfter,
    ]);
    exit;
}

// This is the message we'd save if there's nothing to merge into (insert case).
$parts = [];
if ($freeText !== '') {
    $parts[] = 'Tala: ' . $freeText;
}
if (!empty($activityTags)) {
    $parts[] = 'Gawain: ' . implode(', ', $activityTags);
}
if (!empty($conditionTags)) {
    $parts[] = 'Kalagayan: ' . implode(', ', $conditionTags);
}
if (!empty($weatherTags)) {
    $parts[] = 'Panahon: ' . implode(', ', $weatherTags);
}
$message = implode(' | ', $parts);

$conn->set_charset('utf8mb4');


if ($plantedCropId === null) {
    $findStmt = $conn->prepare(
        "SELECT NotesID, Message FROM notes
         WHERE UserID = ? AND EntryDate = ? AND PlantedCropID IS NULL
         ORDER BY TimeCreated DESC
         LIMIT 1"
    );
    $findStmt->bind_param('is', $userId, $entryDate);
} else {
    $findStmt = $conn->prepare(
        "SELECT NotesID, Message FROM notes
         WHERE UserID = ? AND EntryDate = ? AND PlantedCropID = ?
         ORDER BY TimeCreated DESC
         LIMIT 1"
    );
    $findStmt->bind_param('isi', $userId, $entryDate, $plantedCropId);
}
$findStmt->execute();
$existing = $findStmt->get_result()->fetch_assoc();
$findStmt->close();

$wasUpdate = false;

if ($existing) {
    $wasUpdate = true;

    [$existingFreeText, $mergedGroups] = parseNoteMessage($existing['Message']);

    $mergedFreeText = $existingFreeText;
    if ($freeText !== '' && strpos($mergedFreeText, $freeText) === false) {
        $mergedFreeText = $mergedFreeText !== '' ? $mergedFreeText . ' ' . $freeText : $freeText;
    }

    $newByLabel = [
        'Gawain' => $activityTags,
        'Kalagayan' => $conditionTags,
        'Panahon' => $weatherTags,
    ];
    foreach ($newByLabel as $label => $values) {
        if (empty($values)) continue;
        if (!isset($mergedGroups[$label])) $mergedGroups[$label] = [];
        foreach ($values as $v) {
            if (!in_array($v, $mergedGroups[$label], true)) {
                $mergedGroups[$label][] = $v;
            }
        }
    }

    $message = buildNoteMessage($mergedFreeText, $mergedGroups);

    $updateStmt = $conn->prepare(
        "UPDATE notes SET Message = ?, TimeCreated = NOW() WHERE NotesID = ?"
    );
    $updateStmt->bind_param('si', $message, $existing['NotesID']);
    $ok = $updateStmt->execute();
    $notesId = $existing['NotesID'];
    $updateStmt->close();
} else {
    if ($plantedCropId === null) {
        $insertStmt = $conn->prepare(
            "INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message)
             VALUES (?, NULL, ?, ?)"
        );
        $insertStmt->bind_param('iss', $userId, $entryDate, $message);
    } else {
        $insertStmt = $conn->prepare(
            "INSERT INTO notes (UserID, PlantedCropID, EntryDate, Message)
             VALUES (?, ?, ?, ?)"
        );
        $insertStmt->bind_param('iiss', $userId, $plantedCropId, $entryDate, $message);
    }
    $ok = $insertStmt->execute();
    $notesId = $insertStmt->insert_id;
    $insertStmt->close();
}

if (!$ok) {
    error_log('notes_add.php save failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save note.']);
    $conn->close();
    exit;
}

$conn->close();

$_SESSION['lastNoteSave'][$saveKey] = time();

echo json_encode([
    'success' => true,
    'message' => $wasUpdate ? 'Na-update ang tala para sa araw na ito.' : 'Bagong tala ang na-save.',
    'notesId' => $notesId,
    'updated' => $wasUpdate,
]);