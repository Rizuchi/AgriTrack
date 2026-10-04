<?php
require_once __DIR__ . '/../php/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['UserID'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$conn = getDbConnection();
$role = trim((string) ($_SESSION['role'] ?? ''));
if (strcasecmp($role, 'Admin') !== 0) {
    $roleStmt = $conn->prepare('SELECT role FROM users WHERE UserID = ? LIMIT 1');
    $roleStmt->bind_param('i', $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow || strcasecmp(trim((string) $roleRow['role']), 'Admin') !== 0) {
        $conn->close();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $conn->close();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = (int) ($_GET['pageSize'] ?? 25);
if (!in_array($pageSize, [10, 25, 50], true)) {
    $pageSize = 25;
}
$search = trim((string) ($_GET['search'] ?? ''));
$search = substr($search, 0, 200);
$like = '%' . $search . '%';
$userIdFilter = $_GET['userId'] ?? null;
$userIdFilter = $userIdFilter === '' || $userIdFilter === null ? null : (int) $userIdFilter;
if ($userIdFilter !== null && $userIdFilter <= 0) {
    $userIdFilter = null;
}
$status = (string) ($_GET['status'] ?? 'unread');
if (!in_array($status, ['unread', 'read', 'all'], true)) {
    $status = 'unread';
}
$sortOrder = ($_GET['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC';
$where = "(? = '' OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.email LIKE ? OR c.message LIKE ?)";
$userWhere = $userIdFilter !== null ? ' AND c.UserID = ?' : '';

$statusCountStmt = $conn->prepare(
    'SELECT SUM(CASE WHEN COALESCE(s.is_read, 0) = 0 THEN 1 ELSE 0 END) AS unread,
            SUM(CASE WHEN COALESCE(s.is_read, 0) = 1 THEN 1 ELSE 0 END) AS `read`
     FROM contact_messages c
     LEFT JOIN contact_message_status s ON s.ContactID = c.ContactID
     WHERE ' . $where . $userWhere
);
if (!$statusCountStmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load message counts. Apply the contact messages migration and try again.']);
    exit;
}
$statusCountTypes = 'ssssss';
$statusCountParams = [$search, $like, $like, $like, $like, $like];
if ($userIdFilter !== null) {
    $statusCountTypes .= 'i';
    $statusCountParams[] = $userIdFilter;
}
$statusCountStmt->bind_param($statusCountTypes, ...$statusCountParams);
$statusCountStmt->execute();
$statusCounts = $statusCountStmt->get_result()->fetch_assoc();
$statusCountStmt->close();

$statusFilter = $status === 'all' ? '' : ' AND COALESCE(s.is_read, 0) = ?';
$countStmt = $conn->prepare(
    'SELECT COUNT(*) AS total
     FROM contact_messages c
     LEFT JOIN contact_message_status s ON s.ContactID = c.ContactID
     WHERE ' . $where . $userWhere . $statusFilter
);
if (!$countStmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load contact messages.']);
    exit;
}
$countTypes = 'ssssss';
$countParams = [$search, $like, $like, $like, $like, $like];
if ($userIdFilter !== null) {
    $countTypes .= 'i';
    $countParams[] = $userIdFilter;
}
if ($status !== 'all') {
    $countTypes .= 'i';
    $countParams[] = $status === 'read' ? 1 : 0;
}
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, (int) ceil($total / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$messageStmt = $conn->prepare(
    'SELECT c.ContactID, c.first_name, c.last_name, c.email, c.message, c.created_at,
            COALESCE(s.is_read, 0) AS is_read
     FROM contact_messages c
     LEFT JOIN contact_message_status s ON s.ContactID = c.ContactID
     WHERE ' . $where . $userWhere . $statusFilter . " ORDER BY c.created_at $sortOrder, c.ContactID $sortOrder LIMIT ? OFFSET ?"
);
if (!$messageStmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load contact messages.']);
    exit;
}
$messageTypes = 'ssssss';
$messageParams = [$search, $like, $like, $like, $like, $like];
if ($userIdFilter !== null) {
    $messageTypes .= 'i';
    $messageParams[] = $userIdFilter;
}
if ($status !== 'all') {
    $messageTypes .= 'i';
    $messageParams[] = $status === 'read' ? 1 : 0;
}
$messageTypes .= 'ii';
$messageParams[] = $pageSize;
$messageParams[] = $offset;
$messageStmt->bind_param($messageTypes, ...$messageParams);
$messageStmt->execute();
$messages = $messageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$messageStmt->close();

$repliesByContact = [];
if ($messages) {
    $contactIds = array_map(static fn(array $message): int => (int) $message['ContactID'], $messages);
    $contactIdList = implode(',', $contactIds);
    $replyResult = $conn->query(
        'SELECT ReplyID, ContactID, reply_text, email_sent, created_at
         FROM contact_message_replies WHERE ContactID IN (' . $contactIdList . ')
         ORDER BY created_at ASC, ReplyID ASC'
    );
    if (!$replyResult && $conn->errno !== 1146) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to load contact replies.']);
        exit;
    }
    if ($replyResult) {
        while ($replyRow = $replyResult->fetch_assoc()) {
            $contactId = (int) $replyRow['ContactID'];
            $repliesByContact[$contactId][] = [
                'replyId' => (int) $replyRow['ReplyID'],
                'reply' => $replyRow['reply_text'],
                'emailSent' => (bool) $replyRow['email_sent'],
                'createdAt' => $replyRow['created_at'],
            ];
        }
        $replyResult->free();
    }
}

foreach ($messages as &$row) {
    $contactId = (int) $row['ContactID'];
    $row = [
        'contactId' => $contactId,
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'email' => $row['email'],
        'message' => $row['message'],
        'createdAt' => $row['created_at'],
        'isRead' => (bool) $row['is_read'],
        'replies' => $repliesByContact[$contactId] ?? [],
    ];
}
unset($row);

$conn->close();
echo json_encode([
    'success' => true,
    'messages' => $messages,
    'page' => $page,
    'pageSize' => $pageSize,
    'total' => $total,
    'totalPages' => $totalPages,
    'unreadTotal' => (int) ($statusCounts['unread'] ?? 0),
    'readTotal' => (int) ($statusCounts['read'] ?? 0),
], JSON_UNESCAPED_UNICODE);