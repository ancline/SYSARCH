<?php
session_start();

if (!isset($_SESSION['student_id'])) {
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

// Fetch all students
$result = $conn->query("SELECT IdNumber, LastName, FirstName, MiddleName, CourseLvl, Course FROM student");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Sit-in Monitoring System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
        }

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

        .nav-left { display: flex; align-items: center; gap: 10px; }
        .nav-left img { height: 36px; }

        .navbar h1 {
            font-size: 15px;
            font-weight: 600;
            color: white;
            letter-spacing: 0.2px;
        }

        .nav-links { display: flex; align-items: center; gap: 4px; }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 13.5px;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .nav-links a:hover { background-color: rgba(255,255,255,0.15); }
        .nav-links a.active { background-color: rgba(255,255,255,0.20); font-weight: 600; }

        .btn-logout {
            background-color: #f0a500 !important;
            color: white !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
            padding: 6px 16px !important;
        }

        .btn-logout:hover { background-color: #d4920a !important; }

        .page-content { padding: 28px 32px; }

        .page-title {
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .action-bar { display: flex; gap: 10px; margin-bottom: 16px; }

        .btn-add {
            padding: 6px 16px;
            background-color: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13.5px;
            cursor: pointer;
        }

        .btn-reset {
            padding: 6px 16px;
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13.5px;
            cursor: pointer;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13.5px;
        }

        .table-controls select { padding: 2px 6px; font-size: 13px; }

        .table-controls input {
            padding: 4px 8px;
            font-size: 13px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 13.5px;
        }

        thead tr { background-color: #f4f6fa; }

        th, td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid #dde3ec;
        }

        th { font-weight: 700; cursor: pointer; }

        tbody tr:nth-child(even) { background-color: #f9fafb; }
        tbody tr:hover { background-color: #eef3fb; }

        .btn-edit {
            padding: 5px 14px;
            background-color: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            margin-right: 4px;
        }

        .btn-delete {
            padding: 5px 14px;
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
        }

        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            font-size: 13px;
            background: white;
            border-top: 1px solid #dde3ec;
        }

        .page-btn {
            padding: 3px 12px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: white;
            font-size: 13px;
            cursor: pointer;
            margin-left: 3px;
        }

        .page-btn.active { background: #1976d2; color: white; border-color: #1976d2; }

        @media (max-width: 768px) { .navbar h1 { display: none; } }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <h1>College of Computer Studies Sit-in Monitoring System</h1>
    </div>
    <div class="nav-links">
        <a href="admin_home.php">Home</a>
        <a href="#">Search</a>
        <a href="admin_Student.php" class="active">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="#">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<div class="page-content">
    <div class="page-title">Students Information</div>

    <div class="action-bar">
        <button class="btn-add">Add Students</button>
        <button class="btn-reset">Reset All Session</button>
    </div>

    <div class="table-controls">
        <div>
            <select id="entriesSelect">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            entries per page
        </div>
        <div>
            Search: <input type="text" id="searchInput">
        </div>
    </div>

    <table id="studentsTable">
        <thead>
            <tr>
                <th>ID Number ▲</th>
                <th>Name ⬦</th>
                <th>Year Level ⬦</th>
                <th>Course ⬦</th>
                <th>Actions ⬦</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <?php foreach ($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['IdNumber']) ?></td>
                <td><?= htmlspecialchars(trim($s['FirstName'] . ' ' . $s['MiddleName'] . ' ' . $s['LastName'])) ?></td>
                <td><?= htmlspecialchars($s['CourseLvl']) ?></td>
                <td><?= htmlspecialchars($s['Course']) ?></td>
                <td>
                    <button class="btn-edit" onclick="editStudent('<?= $s['IdNumber'] ?>')">Edit</button>
                    <button class="btn-delete" onclick="deleteStudent('<?= $s['IdNumber'] ?>')">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination-bar">
        <span id="paginationInfo">
            Showing <?= count($students) ?> of <?= count($students) ?> entries
        </span>
        <div>
            <button class="page-btn">Previous</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">Next</button>
        </div>
    </div>
</div>

<script>
    // Live search (client-side filter on rendered rows)
    document.getElementById('searchInput').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tableBody tr');
        let visible = 0;
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const show = text.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('paginationInfo').textContent =
            `Showing ${visible} of ${rows.length} entries`;
    });

    function editStudent(id) {
        window.location.href = 'edit_student.php?id=' + id;
    }

    function deleteStudent(id) {
        if (confirm('Are you sure you want to delete student ' + id + '?')) {
            window.location.href = 'delete_student.php?id=' + id;
        }
    }
</script>

</body>
</html>