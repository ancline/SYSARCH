<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

$student_id = $_SESSION['student_id'];

$check = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['success' => true, 'affected' => 0]);
    $conn->close();
    exit();
}

// Delete only the student's own notifications; leave global (NULL) ones intact
// If you want to also hide global ones, use a soft-delete approach instead
$stmt = $conn->prepare("
    DELETE FROM notifications
    WHERE student_id = ?
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'affected' => $affected]);