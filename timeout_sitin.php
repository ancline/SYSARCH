<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: admin_SitIn.php?error=invalid_id');
    exit();
}

$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Make sure the sit-in record exists and is still active
$stmt = $conn->prepare("SELECT id, student_id FROM sitin WHERE id = ? AND time_out IS NULL");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=not_found');
    exit();
}

$sitin = $result->fetch_assoc();
$stmt->close();

// Set time_out to now
$stmt = $conn->prepare("UPDATE sitin SET time_out = NOW() WHERE id = ?");
$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=timeout_failed');
    exit();
}
$stmt->close();
$conn->close();

header('Location: admin_SitIn.php?success=timed_out');
exit();