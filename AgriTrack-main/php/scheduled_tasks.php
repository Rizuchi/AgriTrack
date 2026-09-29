<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$cliUserId = 0;
if (PHP_SAPI === 'cli') {
    $cliUserId = (int) ($argv[1] ?? 0);
    if ($cliUserId <= 0) {
        fwrite(STDERR, "Usage: php scheduled_tasks.php <user_id>\n");
        exit(2);
    }
} else {
    require_once __DIR__ . '/require_user_session.php';
}

if (PHP_SAPI !== 'cli' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

function taskDate(string $date, int $days): string
{
    return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function taskTips(string $fix, string $prevention, string $followUp, ?string $action = null): string
{
    $tips = ['fix' => $fix, 'prevention' => $prevention, 'followUp' => $followUp];
    if ($action !== null && $action !== '') {
        $tips['action'] = $action;
    }
    return json_encode($tips, JSON_UNESCAPED_UNICODE);
}

function insertScheduledTask(mysqli $conn, int $userId, int $plantId, string $type, string $dueDate, string $priority, string $tips, bool $urgent = false): void
{
    $stmt = $conn->prepare(
        'INSERT INTO tasks (user_id, plant_id, type, due_date, status, priority, tips, is_urgent)
         VALUES (?, ?, ?, ?, IF(? < CURDATE(), \'Overdue\', \'Pending\'), ?, ?, ?)
         ON DUPLICATE KEY UPDATE priority = VALUES(priority), tips = VALUES(tips), is_urgent = VALUES(is_urgent)'
    );
    $urgentValue = $urgent ? 1 : 0;
    $stmt->bind_param('iisssssi', $userId, $plantId, $type, $dueDate, $dueDate, $priority, $tips, $urgentValue);
    $stmt->execute();
    $stmt->close();
}

function latestStage(array $crop): string
{
    if (empty($crop['ExpectedHarvestDate']) || $crop['ExpectedHarvestDate'] <= $crop['DateOfPlant']) {
        return 'growth';
    }
    $planted = new DateTimeImmutable($crop['DateOfPlant']);
    $harvest = new DateTimeImmutable($crop['ExpectedHarvestDate']);
    $today = new DateTimeImmutable('today');
    $total = max(1, (int) $planted->diff($harvest)->days);
    $elapsed = max(0, (int) $planted->diff($today)->days);
    $progress = min(1, $elapsed / $total);
    if ($progress < 0.25) return 'seedling';
    if ($progress < 0.5) return 'growth';
    if ($progress < 0.75) return 'flowering';
    return 'fruiting';
}

function pestTips(array $pests, string $pest): array
{
    $normalized = strtolower($pest);
    foreach ($pests as $keyword => $rule) {
        if ($keyword !== 'default' && str_contains($normalized, strtolower($keyword))) {
            return $rule;
        }
    }
    return $pests['default'];
}

try {
    $userId = PHP_SAPI === 'cli' ? $cliUserId : (int) ($_SESSION['UserID'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not logged in.']);
        exit;
    }

    $rules = json_decode(file_get_contents(__DIR__ . '/crop_task_rules.json'), true, 512, JSON_THROW_ON_ERROR);
    $conn = getDbConnection();
    $cropStmt = $conn->prepare(
        "SELECT pc.PlantedCropID, pc.PlantLabel, pc.CropID, pc.DateOfPlant, pc.ExpectedHarvestDate, pc.Status,
                c.CropName, c.CropType,
                (SELECT n.Message FROM notes n WHERE n.UserID = pc.UserID AND n.PlantedCropID = pc.PlantedCropID
                 ORDER BY n.EntryDate DESC, n.TimeCreated DESC LIMIT 1) AS LatestNote,
                (SELECT n.EntryDate FROM notes n WHERE n.UserID = pc.UserID AND n.PlantedCropID = pc.PlantedCropID
                 ORDER BY n.EntryDate DESC, n.TimeCreated DESC LIMIT 1) AS LatestNoteDate
         FROM planted_crop pc JOIN crops c ON c.CropID = pc.CropID
         WHERE pc.UserID = ? ORDER BY pc.DateOfPlant"
    );
    $cropStmt->bind_param('i', $userId);
    $cropStmt->execute();
    $crops = $cropStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cropStmt->close();

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $horizon = taskDate($today, (int) $rules['generateAheadDays']);
    $deleteStmt = $conn->prepare(
        "DELETE FROM tasks WHERE user_id = ? AND plant_id = ? AND status <> 'Done'
         AND type IN ('Watering','Fertilizing','Weeding','Pruning','Harvest reminder')"
    );
    foreach ($crops as $crop) {
        $plantId = (int) $crop['PlantedCropID'];
        if (in_array($crop['Status'], ['Harvested', 'Archived'], true)) {
            $deleteStmt->bind_param('ii', $userId, $plantId);
            $deleteStmt->execute();
            continue;
        }

        $clearPestStmt = $conn->prepare("DELETE FROM tasks WHERE user_id = ? AND plant_id = ? AND is_urgent = 1 AND status <> 'Done'");
        $clearPestStmt->bind_param('ii', $userId, $plantId);
        $clearPestStmt->execute();
        $clearPestStmt->close();

        $cropRules = array_merge($rules['defaults'], $rules['cropTypes'][$crop['CropType']] ?? [], $rules['crops'][(string) $crop['CropID']] ?? []);
        $stage = latestStage($crop);
        $wateringDays = (int) ($cropRules['wateringDaysByStage'][$stage] ?? 3);
        $schedule = [
            'Watering' => [1, max(1, $wateringDays), 'High', 'Water the crop at soil level; adjust for rainfall and soil moisture.', 'Check soil moisture before watering and keep drainage clear.', 'Check soil moisture again tomorrow.'],
            'Fertilizing' => [(int) $cropRules['fertilizerStartDays'], max(0, (int) $cropRules['fertilizerIntervalDays']), 'Medium', 'Apply a crop-appropriate fertilizer at the label rate.', 'Use soil-test guidance and avoid over-fertilizing.', 'Check plant response in 7 days.'],
            'Weeding' => [(int) $cropRules['weedingStartDays'], max(0, (int) $cropRules['weedingIntervalDays']), 'Medium', 'Remove weeds around the crop without disturbing its roots.', 'Mulch and inspect the bed weekly.', 'Re-check for new weeds in 7 days.'],
            'Pruning' => [(int) $cropRules['pruningStartDays'], max(0, (int) $cropRules['pruningIntervalDays']), 'Low', 'Remove dead or damaged growth using clean tools.', 'Sanitize pruning tools and avoid removing healthy growth.', 'Inspect new growth in 7 days.'],
        ];

        $pastCandidates = [];
        $upcomingCandidates = [];
        foreach ($schedule as $type => [$startDays, $interval, $priority, $fix, $prevention, $followUp]) {
            if ($interval <= 0) continue;
            $firstDate = new DateTimeImmutable(taskDate($crop['DateOfPlant'], $startDays));
            $todayDate = new DateTimeImmutable($today);
            $nextDate = $firstDate;
            $lastPast = null;

            if ($firstDate < $todayDate) {
                $elapsedDays = (int) $firstDate->diff($todayDate)->days;
                $periods = intdiv($elapsedDays, $interval);
                $nextDate = $firstDate->modify('+' . ($periods * $interval) . ' days');
                if ($nextDate < $todayDate) {
                    $lastPast = $nextDate;
                    $nextDate = $nextDate->modify('+' . $interval . ' days');
                } elseif ($periods > 0) {
                    $lastPast = $nextDate->modify('-' . $interval . ' days');
                }
            }

            if ($lastPast !== null) {
                $pastCandidates[] = [
                    'type' => $type,
                    'date' => $lastPast->format('Y-m-d'),
                    'priority' => $priority,
                    'tips' => taskTips($fix, $prevention, $followUp),
                ];
            }
            if ($nextDate->format('Y-m-d') <= $horizon) {
                $upcomingCandidates[] = [
                    'type' => $type,
                    'date' => $nextDate->format('Y-m-d'),
                    'priority' => $priority,
                    'tips' => taskTips($fix, $prevention, $followUp),
                ];
            }
        }

        if (!empty($crop['ExpectedHarvestDate'])) {
            $upcomingCandidates[] = [
                'type' => 'Harvest reminder',
                'date' => $crop['ExpectedHarvestDate'],
                'priority' => 'High',
                'tips' => taskTips(
                    'Harvest when the crop reaches maturity and shows the expected signs of readiness.',
                    'Monitor the crop near its expected harvest date and use clean harvesting tools.',
                    'Re-check maturity in 2 days if it is not ready.'
                ),
            ];
        }

        usort($pastCandidates, static fn(array $a, array $b): int => $b['date'] <=> $a['date']);
        usort($upcomingCandidates, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
        $selectedCandidates = array_slice($pastCandidates, 0, 1);
        foreach ($upcomingCandidates as $candidate) {
            if (count($selectedCandidates) >= 3) break;
            $selectedCandidates[] = $candidate;
        }

        $selectedKeys = [];
        foreach ($selectedCandidates as $candidate) {
            $selectedKeys[$candidate['type'] . '|' . $candidate['date']] = true;
            insertScheduledTask(
                $conn,
                $userId,
                $plantId,
                $candidate['type'],
                $candidate['date'],
                $candidate['priority'],
                $candidate['tips']
            );
        }

        $existingStmt = $conn->prepare(
            "SELECT id, type, due_date FROM tasks WHERE user_id = ? AND plant_id = ? AND status <> 'Done'
             AND type IN ('Watering','Fertilizing','Weeding','Pruning','Harvest reminder')"
        );
        $existingStmt->bind_param('ii', $userId, $plantId);
        $existingStmt->execute();
        $existingTasks = $existingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $existingStmt->close();
        $removeStmt = $conn->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        foreach ($existingTasks as $existingTask) {
            $key = $existingTask['type'] . '|' . $existingTask['due_date'];
            if (!isset($selectedKeys[$key])) {
                $taskId = (int) $existingTask['id'];
                $removeStmt->bind_param('ii', $taskId, $userId);
                $removeStmt->execute();
            }
        }
        $removeStmt->close();

        $pest = '';
        if (!empty($crop['LatestNote']) && preg_match('/Peste\/Sakit:\s*(.*?)(?:\||$)/u', $crop['LatestNote'], $match)) {
            $pest = trim($match[1]);
        }
        if ($pest !== '' && strcasecmp($pest, 'Wala') !== 0 && strcasecmp($pest, 'None') !== 0) {
            $pestRule = pestTips($rules['pests'], $pest);
            $dueDate = $crop['LatestNoteDate'] ?: $today;
            insertScheduledTask(
                $conn,
                $userId,
                $plantId,
                'Pest response: ' . $pest,
                $dueDate,
                'Urgent',
                taskTips($pestRule['fix'], $pestRule['prevention'], $pestRule['followUp'], $pestRule['action'] ?? null),
                true
            );
        }
    }
    $deleteStmt->close();

    $overdueStmt = $conn->prepare("UPDATE tasks SET status = 'Overdue' WHERE user_id = ? AND status = 'Pending' AND due_date < CURDATE()");
    $overdueStmt->bind_param('i', $userId);
    $overdueStmt->execute();
    $overdueStmt->close();

    $reminderStmt = $conn->prepare(
        "SELECT t.id, t.type, t.due_date, t.priority, t.is_urgent, pc.PlantLabel, c.CropName
         FROM tasks t JOIN planted_crop pc ON pc.PlantedCropID = t.plant_id
         JOIN crops c ON c.CropID = pc.CropID
                 WHERE t.user_id = ? AND pc.Status <> 'Archived'
                     AND t.status IN ('Pending','Overdue') AND t.due_date <= CURDATE()
           AND (t.last_reminded_date IS NULL OR t.last_reminded_date < CURDATE())"
    );
    $reminderStmt->bind_param('i', $userId);
    $reminderStmt->execute();
    $reminders = $reminderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reminderStmt->close();

    $reminderSent = false;
    if ($reminders && PHP_SAPI !== 'cli') {
        $userStmt = $conn->prepare('SELECT email FROM users WHERE UserID = ?');
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $user = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        if (!empty($user['email'])) {
            require_once __DIR__ . '/smtp_mailer.php';
            $items = '';
            foreach ($reminders as $reminder) {
                $plantName = htmlspecialchars(($reminder['PlantLabel'] ?: $reminder['CropName']), ENT_QUOTES, 'UTF-8');
                $items .= '<li><strong>' . $plantName . '</strong>: ' . htmlspecialchars($reminder['type'], ENT_QUOTES, 'UTF-8')
                    . ' (' . htmlspecialchars($reminder['due_date'], ENT_QUOTES, 'UTF-8') . ')</li>';
            }
            $mailer = new SmtpMailer();
            $reminderSent = $mailer->send($user['email'], 'AgriTrack: crop task reminder',
                '<p>You have crop care tasks due today or overdue:</p><ul>' . $items . '</ul><p>Open AgriTrack to review and mark tasks done.</p>');
            if ($reminderSent) {
                $markStmt = $conn->prepare('UPDATE tasks SET last_reminded_date = CURDATE() WHERE id = ? AND user_id = ?');
                foreach ($reminders as $reminder) {
                    $taskId = (int) $reminder['id'];
                    $markStmt->bind_param('ii', $taskId, $userId);
                    $markStmt->execute();
                }
                $markStmt->close();
            }
        }
    }

    $taskStmt = $conn->prepare(
        "SELECT t.id, t.plant_id, t.type, t.due_date, t.status, t.priority, t.tips, t.is_urgent,
                pc.PlantLabel, c.CropName
         FROM tasks t JOIN planted_crop pc ON pc.PlantedCropID = t.plant_id
         JOIN crops c ON c.CropID = pc.CropID
                 WHERE t.user_id = ? AND pc.Status <> 'Archived'
                     AND (t.status <> 'Done' OR t.due_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
         ORDER BY t.is_urgent DESC, t.due_date ASC, t.id ASC"
    );
    $taskStmt->bind_param('i', $userId);
    $taskStmt->execute();
    $tasks = $taskStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $taskStmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'tasks' => $tasks, 'reminderSent' => $reminderSent]);
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    error_log('Scheduled tasks error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load scheduled tasks. Apply the scheduled tasks database migration and try again.']);
}
