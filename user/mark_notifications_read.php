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

// Check table exists
$check = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['success' => true, 'affected' => 0]);
    $conn->close();
    exit();
}

$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'affected' => $affected]);