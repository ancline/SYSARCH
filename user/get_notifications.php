<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) { 
    echo json_encode(['notifications'=>[], 'error'=>'Not logged in']); 
    exit; 
}

$conn = new mysqli('localhost','root','','students');
if ($conn->connect_error) {
    echo json_encode(['notifications'=>[], 'error'=>'DB connection failed: '.$conn->connect_error]); 
    exit;
}

$student_id = $_SESSION['student_id'];
$notifications = [];

// Ensure notifications table has needed columns
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$table_check || $table_check->num_rows === 0) {
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
}

$stmt = $conn->prepare("
    SELECT id, type, subtype, title, message, is_read, created_at
    FROM notifications
    WHERE student_id = ? OR student_id IS NULL
    ORDER BY created_at DESC
    LIMIT 60
");

if (!$stmt) {
    echo json_encode(['notifications'=>[], 'error'=>'Prepare failed: '.$conn->error]); 
    $conn->close();
    exit;
}

$stmt->bind_param('s', $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['is_read'] = (bool)$row['is_read'];
    $notifications[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode(['notifications' => $notifications]);