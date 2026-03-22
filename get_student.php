<?php
// get_student.php — AJAX endpoint
// Returns student name and remaining sessions from the student table.

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['found' => false]);
    exit();
}

$id = trim($_GET['id'] ?? '');
if (!$id) {
    echo json_encode(['found' => false]);
    exit();
}

// ── Detect session column name in the student table ──
$session_col = null;
$cols = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}

$select = $session_col
    ? "IdNumber, FirstName, LastName, `$session_col` AS sessions"
    : "IdNumber, FirstName, LastName";

$stmt = $conn->prepare("SELECT $select FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if ($row) {
    echo json_encode([
        'found'    => true,
        'name'     => trim($row['FirstName'] . ' ' . $row['LastName']),
        // Always return integer so JS can compare against 0
        'sessions' => isset($row['sessions']) ? (int)$row['sessions'] : 0,
    ]);
} else {
    echo json_encode(['found' => false]);
}