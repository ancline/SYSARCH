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

// Validate required fields
$student_id   = trim($_POST['student_id']   ?? '');
$student_name = trim($_POST['student_name'] ?? '');
$purpose      = trim($_POST['purpose']      ?? '');
$lab          = trim($_POST['lab']          ?? '');

if (empty($student_id) || empty($purpose) || empty($lab)) {
    header('Location: admin_SitIn.php?error=missing_fields');
    exit();
}

// Detect the sessions column name in the student table
$session_col = null;
$cols_result = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols_result->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}

$select_cols = $session_col
    ? "IdNumber, FirstName, LastName, `$session_col` AS sessions"
    : "IdNumber, FirstName, LastName";

// Verify student exists
$stmt = $conn->prepare("SELECT $select_cols FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=student_not_found');
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Get current sessions remaining from student table
$current_sessions = isset($student['sessions']) ? (int)$student['sessions'] : 0;

// Block sit-in if student has no sessions left
if ($current_sessions < 1) {
    $conn->close();
    header('Location: admin_SitIn.php?error=no_sessions');
    exit();
}

// Check if student already has an active sit-in
$stmt = $conn->prepare("SELECT id FROM sitin WHERE student_id = ? AND time_out IS NULL");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$active = $stmt->get_result();

if ($active->num_rows > 0) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=already_sitin');
    exit();
}
$stmt->close();

// Deduct 1 session from student table
$new_sessions = $current_sessions - 1;

if ($session_col) {
    $stmt = $conn->prepare("UPDATE student SET `$session_col` = ? WHERE IdNumber = ?");
    $stmt->bind_param('is', $new_sessions, $student_id);
    $stmt->execute();
    $stmt->close();
}

// Make sure sitin table exists with sessions column
$conn->query("
    CREATE TABLE IF NOT EXISTS sitin (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50)  NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        purpose      VARCHAR(255) DEFAULT '',
        sessions     INT          DEFAULT NULL,
        time_in      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        time_out     DATETIME     DEFAULT NULL
    )
");
$conn->query("ALTER TABLE sitin ADD COLUMN IF NOT EXISTS sessions INT DEFAULT NULL");

// Insert sit-in record — store the REMAINING sessions AFTER deduction
$full_name = trim($student['FirstName'] . ' ' . $student['LastName']);
$stmt = $conn->prepare("INSERT INTO sitin (student_id, student_name, lab, purpose, sessions, time_in) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param('ssssi', $student_id, $full_name, $lab, $purpose, $new_sessions);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=insert_failed');
    exit();
}
$stmt->close();
$conn->close();

header('Location: admin_SitIn.php?success=sitin_added');
exit();