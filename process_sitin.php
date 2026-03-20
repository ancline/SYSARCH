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
$student_id        = trim($_POST['student_id']        ?? '');
$student_name      = trim($_POST['student_name']      ?? '');
$purpose           = trim($_POST['purpose']           ?? '');
$lab               = trim($_POST['lab']               ?? '');
$admin_sessions    = isset($_POST['remaining_session']) ? (int)$_POST['remaining_session'] : null;

if (empty($student_id) || empty($purpose) || empty($lab) || $admin_sessions === null || $admin_sessions < 1) {
    header('Location: admin_SitIn.php?error=missing_fields');
    exit();
}

// Detect the actual sessions column name in the student table
$session_col = null;
$cols_result = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols_result->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}
// Fallback: just fetch all columns and pick the student row
$select_cols = $session_col ? "IdNumber, FirstName, LastName, `$session_col`" : "IdNumber, FirstName, LastName";

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

// Check if student already has an active sit-in (no time_out yet)
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

// Sessions will be set by admin — no need to block based on current count

// Make sure sitin table exists
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

// Add sessions column to sitin if it doesn't exist yet (migration safety)
$conn->query("ALTER TABLE sitin ADD COLUMN IF NOT EXISTS sessions INT DEFAULT NULL");

// Insert sit-in record including sessions
$full_name = trim($student['FirstName'] . ' ' . $student['LastName']);
$stmt = $conn->prepare("INSERT INTO sitin (student_id, student_name, lab, purpose, sessions, time_in) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param('ssssi', $student_id, $full_name, $lab, $purpose, $admin_sessions);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    header('Location: admin_SitIn.php?error=insert_failed');
    exit();
}
$stmt->close();

// Set student sessions to the admin-specified value (only if column exists)
if ($session_col && $admin_sessions !== null) {
    $stmt = $conn->prepare("UPDATE student SET `$session_col` = ? WHERE IdNumber = ?");
    $stmt->bind_param('is', $admin_sessions, $student_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

header('Location: admin_SitIn.php?success=sitin_added');
exit();