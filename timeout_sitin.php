<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
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

$id       = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? 'admin_SitIn.php';

// Only allow redirect to known safe pages
$allowed = ['admin_SitIn.php', 'admin_ViewSitInRecords.php'];
if (!in_array($redirect, $allowed)) {
    $redirect = 'admin_SitIn.php';
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE sitin SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// After timeout, redirect back — admin_SitIn.php will now show one fewer active record
header('Location: /SYSARCH/admin/' . $redirect);
exit();