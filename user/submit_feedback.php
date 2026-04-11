<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true);
$sitin_id = (int)($input['sitin_id'] ?? 0);
$message  = trim($input['message'] ?? '');

if (!$sitin_id || empty($message)) {
    echo json_encode(['error' => 'Missing fields']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'students');
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// Create feedback table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        sitin_id   INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        message    TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Prevent duplicate feedback for same sitin
$check = $conn->prepare("SELECT id FROM feedback WHERE sitin_id = ? AND student_id = ?");
$check->bind_param('is', $sitin_id, $_SESSION['student_id']);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    $conn->close();
    echo json_encode(['error' => 'Feedback already submitted for this session.']);
    exit();
}
$check->close();

$stmt = $conn->prepare("INSERT INTO feedback (sitin_id, student_id, message) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $sitin_id, $_SESSION['student_id'], $message);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to save feedback.']);
}