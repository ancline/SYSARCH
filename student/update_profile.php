<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id_number'])) {
    header('Location: ../login.php');
    exit();
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'students';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if ($last_name === '' || $first_name === '' || $email === '' || $course === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Map year level to int
    $year_levels = ['1st Year' => 1, '2nd Year' => 2, '3rd Year' => 3, '4th Year' => 4];
    $course_lvl = isset($year_levels[$year_level]) ? $year_levels[$year_level] : null;
    if ($course_lvl === null) {
        $errors[] = 'Please enter a valid year level (e.g., 1st Year, 2nd Year, etc.).';
    }

    // Check if ID matches session
    if ($id_number !== $_SESSION['id_number']) {
        $errors[] = 'Unauthorized access.';
    }

    if (empty($errors)) {
        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            $errors[] = 'Database connection failed: ' . $conn->connect_error;
        } else {
            // Check if email is unique (except for current user)
            $stmt = $conn->prepare("SELECT IdNumber FROM student WHERE Email = ? AND IdNumber != ?");
            $stmt->bind_param('ss', $email, $id_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = 'The email address is already in use by another account.';
            }
            $stmt->close();

            if (empty($errors)) {
                // Prepare update query
                $update_fields = "LastName = ?, FirstName = ?, MiddleName = ?, Email = ?, CourseLvl = ?, Course = ?";
                $params = [$last_name, $first_name, $middle_name, $email, $course_lvl, $course];
                $types = 'ssssis';

                if ($password !== '') {
                    // Validate password strength if provided
                    if (strlen($password) < 6) {
                        $errors[] = 'Password must be at least 6 characters long.';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_fields .= ", Password = ?";
                        $params[] = $password_hash;
                        $types .= 's';
                    }
                }

                if (empty($errors)) {
                    $params[] = $id_number;
                    $types .= 's';

                    $stmt = $conn->prepare("UPDATE student SET $update_fields WHERE IdNumber = ?");
                    $stmt->bind_param($types, ...$params);

                    if ($stmt->execute()) {
                        $success = 'Profile updated successfully!';
                        // Redirect back to profile
                        header('Location: user_profile.php?success=1');
                        exit();
                    } else {
                        $errors[] = 'Failed to update profile: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            $conn->close();
        }
    }
}

// If not POST or errors, redirect back with errors
if (!empty($errors)) {
    $error_str = urlencode(implode('<br>', $errors));
    header('Location: user_profile.php?errors=' . $error_str);
    exit();
}
?>