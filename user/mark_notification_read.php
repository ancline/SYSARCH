<?php
session_start();
if (!isset($_SESSION['student_id'])) { echo json_encode(['ok'=>false]); exit; }

$conn = new mysqli('localhost','root','','students');
$student_id = $_SESSION['student_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!empty($input['all'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE student_id=? OR student_id IS NULL");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
} elseif (!empty($input['id'])) {
    $id = (int)$input['id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND (student_id=? OR student_id IS NULL)");
    $stmt->bind_param('is', $id, $student_id);
    $stmt->execute();
}

$conn->close();
header('Content-Type: application/json');
echo json_encode(['ok' => true]);