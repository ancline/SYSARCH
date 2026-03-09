<?php
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'students';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idNumber   = trim($_POST['idNumber'] ?? '');
    $lastName   = trim($_POST['lastName'] ?? '');
    $firstName  = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $courseLevel= trim($_POST['courseLevel'] ?? '');
    $password   = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $address    = trim($_POST['address'] ?? '');

    if ($password === '' || $repeatPassword === '') {
        $errors[] = 'Please enter and confirm your password.';
    } elseif ($password !== $repeatPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($idNumber === '' || $lastName === '' || $firstName === '' || $email === '' || $course === '' || $address === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if (empty($errors)) {
        $conn = new mysqli($dbHost, $dbUser, $dbPass);
        if ($conn->connect_error) {
            $errors[] = 'Database connection failed: ' . $conn->connect_error;
        } else {
            if (!$conn->select_db($dbName)) {
                if ($conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === false) {
                    $errors[] = 'Unable to create database: ' . $conn->error;
                } else {
                    $conn->select_db($dbName);
                }
            }

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO student (IdNumber, LastName, FirstName, MiddleName, CourseLvl, Email, Password, Course, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if ($stmt === false) {
                    $errors[] = 'Query preparation failed: ' . $conn->error;
                } else {
                    $stmt->bind_param(
                        'ssssissss',
                        $idNumber,
                        $lastName,
                        $firstName,
                        $middleName,
                        $courseLevel,
                        $email,
                        $passwordHash,
                        $course,
                        $address
                    );

                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                        $idNumber = $lastName = $firstName = $middleName = $courseLevel = $email = $course = $address = '';
                    } else {
                        if ($conn->errno === 1062) {
                            $errors[] = 'The provided ID number or email is already registered.';
                        } else {
                            $errors[] = 'Registration failed: ' . $stmt->error;
                        }
                    }
                    $stmt->close();
                }
            }

            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System - Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
}

.navbar {
    background-color: #9757d6;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.nav-left {
    display: flex;
    align-items: center;
    gap: 5px;
}

.nav-left img {
    height: 40px;
}

.navbar h1 {
    font-size: 18px;
    font-weight: normal;
}

.nav-links a {
    color: white;
    text-decoration: none;
    margin-left: 20px;
    font-size: 14px;
}

.nav-links a:hover {
    text-decoration: underline;
}

.main {
    padding: 40px 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 60px);
}

.registration-container {
    background-color: white;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 500px;
    width: 100%;
}

.back-btn {
    display: inline-block;
    padding: 8px 20px;
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 20px;
    text-decoration: none;
}

.back-btn:hover {
    background-color: #c82333;
}

h2 {
    font-size: 28px;
    margin-bottom: 30px;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #555;
    font-size: 14px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #1e4f91;
}

.register-btn {
    width: 100%;
    padding: 12px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
}

.register-btn:hover {
    background-color: #0056b3;
}
</style>
</head>
<body>

<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="CCS Logo">
        <h1>College of Computer Studies Sit-in Monitoring System</h1>
    </div>
    <div class="nav-links">
        <a href="landingpage.php">Home</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>
</div>

<div class="main">
<div class="registration-container">

    <a href="landingpage.php" class="back-btn">Back</a>
    <h2>Sign up</h2>

    <form method="post" action="">
    <?php if (!empty($success)): ?>
        <div style="margin-bottom: 16px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">
            <?= $success ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div style="margin-bottom: 16px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">
            <ul style="margin: 0; padding-left: 18px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="idNumber">ID Number</label>
        <input type="number" id="idNumber" name="idNumber" value="<?= isset($idNumber) ? htmlspecialchars($idNumber) : '' ?>">
    </div>

    <div class="form-group">
        <label for="lastName">Last Name</label>
        <input type="text" id="lastName" name="lastName" value="<?= isset($lastName) ? htmlspecialchars($lastName) : '' ?>">
    </div>

    <div class="form-group">
        <label for="firstName">First Name</label>
        <input type="text" id="firstName" name="firstName" value="<?= isset($firstName) ? htmlspecialchars($firstName) : '' ?>">
    </div>

    <div class="form-group">
        <label for="middleName">Middle Name</label>
        <input type="text" id="middleName" name="middleName" value="<?= isset($middleName) ? htmlspecialchars($middleName) : '' ?>">
    </div>

    <div class="form-group">
        <label for="courseLevel">Course Level</label>
        <select id="courseLevel" name="courseLevel">
            <option value="1" <?= (isset($courseLevel) && $courseLevel === '1') ? 'selected' : '' ?>>1</option>
            <option value="2" <?= (isset($courseLevel) && $courseLevel === '2') ? 'selected' : '' ?>>2</option>
            <option value="3" <?= (isset($courseLevel) && $courseLevel === '3') ? 'selected' : '' ?>>3</option>
            <option value="4" <?= (isset($courseLevel) && $courseLevel === '4') ? 'selected' : '' ?>>4</option>
        </select>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password">
    </div>

    <div class="form-group">
        <label for="repeatPassword">Repeat your password</label>
        <input type="password" id="repeatPassword" name="repeatPassword">
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
    </div>

    <div class="form-group">
        <label for="course">Course</label>
        <input type="text" id="course" name="course" placeholder="BSIT" value="<?= isset($course) ? htmlspecialchars($course) : '' ?>">
    </div>

    <div class="form-group">
        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?= isset($address) ? htmlspecialchars($address) : '' ?>">
    </div>

    <button type="submit" class="register-btn">Register</button>
    </form>
</div>
</div>

</body>
</html>