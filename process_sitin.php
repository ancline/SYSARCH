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

// ── Validate required fields ──
$student_id       = trim($_POST['student_id']       ?? '');
$student_name     = trim($_POST['student_name']     ?? '');
$purpose          = trim($_POST['purpose']          ?? '');
$lab              = trim($_POST['lab']              ?? '');
$remaining_session = trim($_POST['remaining_session'] ?? '');

if (empty($student_id) || empty($purpose) || empty($lab)) {
    header('Location: admin_SitIn.php?error=missing_fields');
    exit();
}

// ── Look up student ──
$stmt = $conn->prepare("SELECT IdNumber, FirstName, MiddleName, LastName, sessions FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: admin_SitIn.php?error=student_not_found');
    exit();
}

// ── Build full name if not passed ──
if (empty($student_name)) {
    $student_name = trim($student['FirstName'] . ' ' . $student['MiddleName'] . ' ' . $student['LastName']);
}

// ── Check for existing active sit-in ──
$check = $conn->prepare("SELECT id FROM sitin WHERE student_id = ? AND time_out IS NULL");
$check->bind_param('s', $student_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    header('Location: admin_SitIn.php?error=already_sitin');
    exit();
}
$check->close();

$current_sessions = (int)($student['sessions'] ?? 0);

// ── Determine sessions to store ──
if ($current_sessions > 0) {
    // Deduct 1 session from existing sessions
    $new_sessions = $current_sessions - 1;
    $store_sessions = $current_sessions; // store remaining before deduction

    $upd = $conn->prepare("UPDATE student SET sessions = ? WHERE IdNumber = ?");
    $upd->bind_param('is', $new_sessions, $student_id);
    $upd->execute();
    $upd->close();
} else {
    // Admin entered session count — assign it to student and record
    if (!empty($remaining_session) && (int)$remaining_session > 0) {
        $assigned = (int)$remaining_session;
        $new_sessions = $assigned - 1; // deduct 1 for this sit-in

        $upd = $conn->prepare("UPDATE student SET sessions = ? WHERE IdNumber = ?");
        $upd->bind_param('is', $new_sessions, $student_id);
        $upd->execute();
        $upd->close();

        $store_sessions = $assigned;
    } else {
        // No sessions — can't sit in
        header('Location: admin_SitIn.php?error=no_sessions');
        exit();
    }
}

// ── Insert sit-in record ──
$ins = $conn->prepare("
    INSERT INTO sitin (student_id, student_name, lab, purpose, time_in, sessions)
    VALUES (?, ?, ?, ?, NOW(), ?)
");
$ins->bind_param('ssssi', $student_id, $student_name, $lab, $purpose, $store_sessions);
$success = $ins->execute();
$ins->close();

$conn->close();

if ($success) {
    // ── Redirect to admin_SitIn.php showing active sit-ins ──
    header('Location: admin_SitIn.php?success=1');
} else {
    header('Location: admin_SitIn.php?error=insert_failed');
}
exit();