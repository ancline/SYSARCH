<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS | Edit Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

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

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Fetch user data
$id_number = $_SESSION['id_number'];
$stmt = $conn->prepare("SELECT * FROM student WHERE IdNumber = ?");
$stmt->bind_param('s', $id_number);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    die('User not found.');
}

// Map year level to text
$year_levels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
$year_level_text = isset($year_levels[$user['CourseLvl']]) ? $year_levels[$user['CourseLvl']] : $user['CourseLvl'];
?>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            background-color: #9757d6;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-left img {
            height: 36px;
        }

        .navbar h1 {
            font-size: 15px;
            font-weight: 600;
            color: white;
            letter-spacing: 0.2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 13.5px;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .nav-links a:hover {
            background-color: rgba(255,255,255,0.15);
        }

        .nav-links a.active {
            background-color: rgba(255,255,255,0.12);
            font-weight: 600;
        }

        .btn-logout {
            background-color: #f0a500 !important;
            color: white !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
            padding: 6px 16px !important;
        }

        .btn-logout:hover {
            background-color: #d4920a !important;
        }

        /* ── PAGE LAYOUT ── */
        .page-wrapper {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #666;
            margin-bottom: 16px;
        }

        .breadcrumb span {
            color: #1a3a6b;
            font-weight: 600;
        }

        /* ── CARD ── */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            padding: 40px 48px;
            display: flex;
            gap: 60px;
            align-items: flex-start;
        }

        /* ── FORM SIDE ── */
        .form-side {
            flex: 1;
            min-width: 0;
        }

        .form-side h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            border: 1.5px solid #dde1e9;
            border-radius: 8px;
            overflow: hidden;
            background: #fafbfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrapper:focus-within {
            border-color: #1a3a6b;
            box-shadow: 0 0 0 3px rgba(26,58,107,0.1);
            background: #fff;
        }

        .input-wrapper .icon {
            padding: 0 12px;
            color: #aab0be;
            font-size: 16px;
            flex-shrink: 0;
        }

        .input-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 12px 10px 0;
            font-size: 14px;
            color: #333;
            outline: none;
        }

        .input-wrapper input[readonly] {
            color: #888;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn-save {
            margin-top: 28px;
            width: 100%;
            padding: 12px;
            background-color: #9757d6;
            color: white;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-save:hover {
            background-color: #9757d6;
            transform: translateY(-1px);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        /* ── ILLUSTRATION SIDE ── */
        .illustration-side {
            width: 280px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 40px;
        }

        .illustration-side img {
            width: 100%;
            max-width: 280px;
        }

        /* Fallback SVG illustration */
        .illus-svg {
            width: 260px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .card {
                flex-direction: column;
                padding: 28px 24px;
                gap: 30px;
            }

            .illustration-side {
                width: 100%;
                padding-top: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .navbar h1 {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <img src="uclogo-removebg-preview.png" alt="UC Logo">
            <h1>College of Computer Studies Sit-in Monitoring System</h1>
        </div>
        <div class="nav-links">
            <a href="notification.php">Notification ▾</a>
            <a href="home.php">Home</a>
            <a href="Profile.php" class="active">Edit Profile</a>
            <a href="history.php">History</a>
            <a href="reservation.php">Reservation</a>
            <a href="logout.php" class="btn-logout">Log out</a>
        </div>
    </div>

    <!-- PAGE -->
    <div class="page-wrapper">
        <p class="breadcrumb">Dashboard &rsaquo; <span>Edit Profile</span></p>

        <?php
        if (isset($_GET['success'])) {
            echo '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 16px;">Profile updated successfully!</div>';
        }
        if (isset($_GET['errors'])) {
            $errors = urldecode($_GET['errors']);
            echo '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 16px;">' . $errors . '</div>';
        }
        ?>

        <div class="card">

            <!-- FORM -->
            <div class="form-side">
                <h2>Edit Profile</h2>

                <form action="update_profile.php" method="POST">

                    <div class="form-group">
                        <label>ID Number</label>
                        <div class="input-wrapper">
                            <span class="icon">🪪</span>
                            <input type="text" name="id_number" value="<?php echo htmlspecialchars($user['IdNumber']); ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <div class="input-wrapper">
                            <span class="icon">✉</span>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['LastName']); ?>" placeholder="Last Name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <div class="input-wrapper">
                                <span class="icon">✉</span>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['FirstName']); ?>" placeholder="First Name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Middle Name</label>
                            <div class="input-wrapper">
                                <span class="icon">✉</span>
                                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['MiddleName']); ?>" placeholder="Middle Name">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <span class="icon">📧</span>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" placeholder="your@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level</label>
                            <div class="input-wrapper">
                                <span class="icon">📚</span>
                                <input type="text" name="year_level" value="<?php echo htmlspecialchars($year_level_text); ?>" placeholder="e.g. 3rd Year">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Course</label>
                            <div class="input-wrapper">
                                <span class="icon">🎓</span>
                                <input type="text" name="course" value="<?php echo htmlspecialchars($user['Course']); ?>" placeholder="e.g. BSIT">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-wrapper">
                            <span class="icon">🔒</span>
                            <input type="password" name="password" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">Save Changes</button>

                </form>
            </div>


        </div>
    </div>

</body>
</html>