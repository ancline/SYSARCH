<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['found' => false]);
    exit();
}

header('Content-Type: application/json');

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    echo json_encode(['found' => false]);
    exit();
}

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['found' => false]);
    exit();
}

// Auto-detect the sessions column name
$session_col = null;
$cols = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}

$select = $session_col
    ? "FirstName, LastName, `$session_col` AS sessions"
    : "FirstName, LastName";

$stmt = $conn->prepare("SELECT $select FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'found'    => true,
        'name'     => trim($row['FirstName'] . ' ' . $row['LastName']),
        'sessions' => isset($row['sessions']) ? (int)$row['sessions'] : '—',
    ]);
} else {
    echo json_encode(['found' => false]);
}

$stmt->close();
$conn->close();