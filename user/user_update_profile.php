<?php
session_start();

define('BASE_PATH', '/SYSARCH/user/');

// Guard: must be logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit();
}

// Verify CSRF token
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Invalid request. Please try again.'));
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php');
    exit();
}

// DB connection
$conn = new mysqli('127.0.0.1', 'root', '', 'students');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Sanitize & validate inputs
$id_number        = $_SESSION['student_id'];
$last_name        = trim($_POST['last_name']        ?? '');
$first_name       = trim($_POST['first_name']       ?? '');
$middle_name      = trim($_POST['middle_name']      ?? '');
$email            = trim($_POST['email']            ?? '');
$year_level       = intval($_POST['year_level']     ?? 0);
$course           = trim($_POST['course']           ?? '');
$password         = $_POST['password']              ?? '';
$password_confirm = $_POST['password_confirm']      ?? '';

// Validate required fields
if (empty($last_name) || empty($first_name) || empty($email) || empty($course)) {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Please fill in all required fields.'));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Invalid email address.'));
    exit();
}

if (!in_array($year_level, [1, 2, 3, 4])) {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Invalid year level selected.'));
    exit();
}

// Password confirmation check
if (!empty($password)) {
    if (strlen($password) < 6) {
        header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Password must be at least 6 characters.'));
        exit();
    }
    if ($password !== $password_confirm) {
        header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Passwords do not match.'));
        exit();
    }
}

// Build query — update password only if provided
if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare(
        "UPDATE student
         SET LastName=?, FirstName=?, MiddleName=?, Email=?, CourseLvl=?, Course=?, Password=?
         WHERE IdNumber=?"
    );
    $stmt->bind_param('ssssissa', $last_name, $first_name, $middle_name, $email, $year_level, $course, $hashed, $id_number);
} else {
    $stmt = $conn->prepare(
        "UPDATE student
         SET LastName=?, FirstName=?, MiddleName=?, Email=?, CourseLvl=?, Course=?
         WHERE IdNumber=?"
    );
    $stmt->bind_param('ssssiss', $last_name, $first_name, $middle_name, $email, $year_level, $course, $id_number);
}

if ($stmt->execute()) {
    // Sync session so user_home.php reflects changes immediately
    $_SESSION['student_name'] = trim($first_name . ' ' . (!empty($middle_name) ? $middle_name . ' ' : '') . $last_name);
    $_SESSION['email']        = $email;
    $_SESSION['course']       = $course;
    $_SESSION['year_level']   = $year_level;
    $_SESSION['csrf_token']   = bin2hex(random_bytes(32));

    header('Location: ' . BASE_PATH . 'user_edit_profile.php?success=1');
} else {
    header('Location: ' . BASE_PATH . 'user_edit_profile.php?error=' . urlencode('Update failed. Please try again.'));
}

$stmt->close();
$conn->close();