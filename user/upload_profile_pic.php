<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'students');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit();
}

if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit();
}

$file      = $_FILES['profile_pic'];
$max_size  = 3 * 1024 * 1024; // 3MB
$allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime      = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, GIF, or WEBP allowed.']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max 3MB.']);
    exit();
}

$upload_dir = __DIR__ . '/profile_pics/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Delete old pic if exists
$student_id = $_SESSION['student_id'];
$old = $conn->query("SELECT profile_pic FROM student WHERE IdNumber = '" . $conn->real_escape_string($student_id) . "'")->fetch_assoc();
if ($old && $old['profile_pic']) {
    $old_path = $upload_dir . basename($old['profile_pic']);
    if (file_exists($old_path)) unlink($old_path);
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
$filename = 'pic_' . preg_replace('/[^a-z0-9]/i', '_', $student_id) . '_' . time() . '.' . $ext;
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
    exit();
}

$rel_path = 'profile_pics/' . $filename;
$stmt = $conn->prepare("UPDATE student SET profile_pic = ? WHERE IdNumber = ?");
$stmt->bind_param('ss', $rel_path, $student_id);
$stmt->execute();
$stmt->close();

$_SESSION['profile_pic'] = $rel_path;

echo json_encode(['success' => true, 'path' => '/SYSARCH/user/' . $rel_path]);
$conn->close();