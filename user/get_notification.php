<?php
session_start();
if (!isset($_SESSION['student_id'])) { echo json_encode(['notifications'=>[]]); exit; }

$conn = new mysqli('localhost','root','','students');
$student_id = $_SESSION['student_id'];

$notifications = [];

// Ensure notifications table has needed columns
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50),
        type       VARCHAR(30) DEFAULT 'announcement',
        subtype    VARCHAR(30) DEFAULT NULL,
        title      VARCHAR(255),
        message    TEXT,
        is_read    TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$stmt = $conn->prepare("
    SELECT id, type, subtype, title, message, is_read, created_at
    FROM notifications
    WHERE student_id = ? OR student_id IS NULL
    ORDER BY created_at DESC
    LIMIT 60
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['is_read'] = (bool)$row['is_read'];
    $notifications[] = $row;
}
$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['notifications' => $notifications]);