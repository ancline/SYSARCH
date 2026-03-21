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

// Auto-detect sessions column name
$session_col = null;
$cols = $conn->query("SHOW COLUMNS FROM student");
while ($col = $cols->fetch_assoc()) {
    if (preg_match('/^(sessions?|no_?of_?sessions?|remaining_?sessions?|session_?count)$/i', $col['Field'])) {
        $session_col = $col['Field'];
        break;
    }
}

// ── AJAX: handle edit POST, return JSON ──
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['edit_student']) &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');

    $id          = trim($_POST['id_number']   ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $year_level  = intval($_POST['year_level'] ?? 0);
    $course      = trim($_POST['course']      ?? '');
    $sessions    = intval($_POST['sessions']  ?? 0);

    if ($session_col) {
        $stmt = $conn->prepare("UPDATE student SET LastName=?, FirstName=?, MiddleName=?, Email=?, CourseLvl=?, Course=?, `$session_col`=? WHERE IdNumber=?");
        $stmt->bind_param('ssssisss', $last_name, $first_name, $middle_name, $email, $year_level, $course, $sessions, $id);
    } else {
        $stmt = $conn->prepare("UPDATE student SET LastName=?, FirstName=?, MiddleName=?, Email=?, CourseLvl=?, Course=? WHERE IdNumber=?");
        $stmt->bind_param('ssssiss', $last_name, $first_name, $middle_name, $email, $year_level, $course, $id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success'     => true,
            'id'          => $id,
            'first_name'  => $first_name,
            'middle_name' => $middle_name,
            'last_name'   => $last_name,
            'email'       => $email,
            'year_level'  => $year_level,
            'course'      => $course,
            'sessions'    => $sessions,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}

$select_cols = $session_col
    ? "IdNumber, LastName, FirstName, MiddleName, CourseLvl, Course, Email, `$session_col` AS sessions"
    : "IdNumber, LastName, FirstName, MiddleName, CourseLvl, Course, Email";

$result   = $conn->query("SELECT $select_cols FROM student");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$conn->close();

$courses = [
    'Information Technology','Computer Engineering','Civil Engineering',
    'Mechanical Engineering','Electrical Engineering','Industrial Engineering',
    'Naval Architecture and Marine Engineering','Elementary Education (BEEd)',
    'Secondary Education (BSEd)','Criminology','Commerce','Accountancy',
    'Hotel and Restaurant Management','Customs Administration',
    'Computer Secretarial','Industrial Psychology','AB Political Science','AB English',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:       #0f2653;
            --navy-mid:   #1a3a72;
            --navy-light: #2452a0;
            --gold:       #f0a500;
            --gold-light: #ffc84a;
            --bg:         #eef1f8;
            --panel:      #ffffff;
            --border:     #d6dce8;
            --text:       #1c2b4a;
            --muted:      #6b7fa3;
            --accent:     #3b6fd4;
            --tag-bg:     #e8edf8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

        .navbar {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            padding: 0 28px;
            display: flex; justify-content: space-between; align-items: center;
            height: 60px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }

        .nav-title {
            font-size: 13.5px; font-weight: 600;
            color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3;
        }

        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 13px; font-weight: 500; padding: 7px 13px;
            border-radius: 6px; transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px; white-space: nowrap;
        }

        .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }
        .nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important; font-weight: 700 !important;
            border-radius: 8px !important; padding: 7px 18px !important;
            margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important; }

        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; flex-wrap: wrap; gap: 12px;
        }

        .page-header-left h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--navy); margin-bottom: 2px;
        }

        .page-header-left p { font-size: 12.5px; color: var(--muted); }

        .action-bar { display: flex; gap: 10px; }

        .btn-add {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; box-shadow: 0 2px 8px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-add:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,38,83,0.32); }

        .btn-reset {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px;
            background: white; color: #c0392b; border: 1.5px solid #e8b4b4;
            border-radius: 8px; font-size: 13px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-reset:hover { background: #fff0f0; border-color: #e74c3c; }

        .card {
            background: var(--panel); border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 20px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 14px;
        }

        .table-controls {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
        }

        .entries-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

        .entries-label select {
            padding: 5px 10px; border: 1.5px solid var(--border); border-radius: 7px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: var(--bg); outline: none; cursor: pointer;
        }

        .search-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }

        .search-wrap input {
            padding: 6px 12px; border: 1.5px solid var(--border); border-radius: 7px;
            font-size: 13px; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: var(--bg); outline: none;
            transition: border-color 0.18s, box-shadow 0.18s; width: 200px;
        }

        .search-wrap input:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white;
        }

        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }

        thead th {
            background: var(--tag-bg); color: var(--muted);
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; padding: 11px 20px; text-align: left;
            border-bottom: 1px solid var(--border); cursor: pointer;
            white-space: nowrap; user-select: none;
        }

        thead th:hover { color: var(--navy); }

        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.13s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--tag-bg); }

        tbody td { padding: 11px 20px; color: var(--text); vertical-align: middle; }

        .id-cell { font-family: monospace; font-size: 12.5px; font-weight: 600; color: var(--navy-light); }

        .btn-edit {
            padding: 5px 14px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border: none; border-radius: 6px;
            font-size: 12px; font-weight: 600; font-family: 'DM Sans', sans-serif;
            cursor: pointer; margin-right: 5px; transition: opacity 0.15s, transform 0.15s;
        }

        .btn-edit:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-delete {
            padding: 5px 14px; background: white; color: #c0392b;
            border: 1.5px solid #e8b4b4; border-radius: 6px;
            font-size: 12px; font-weight: 600; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: background 0.15s, border-color 0.15s;
        }

        .btn-delete:hover { background: #fff0f0; border-color: #e74c3c; }

        .pagination-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 20px; border-top: 1px solid var(--border);
            font-size: 12.5px; color: var(--muted); flex-wrap: wrap; gap: 10px;
        }

        .page-btns { display: flex; gap: 4px; }

        .page-btn {
            padding: 5px 13px; border: 1.5px solid var(--border); border-radius: 7px;
            background: white; font-size: 12.5px; font-family: 'DM Sans', sans-serif;
            color: var(--text); cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .page-btn:hover { background: var(--tag-bg); border-color: var(--accent); }

        .page-btn.active {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border-color: transparent;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
        }

        .empty-row td {
            text-align: center; color: var(--muted);
            padding: 40px 20px !important; font-size: 13.5px;
        }

        /* ── ROW FLASH ── */
        @keyframes rowFlash {
            0%   { background: #d4f5e2; }
            100% { background: transparent; }
        }

        tr.updated { animation: rowFlash 1.6s ease forwards; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10,20,50,0.45);
            backdrop-filter: blur(3px);
            z-index: 999; align-items: center; justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: white; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15,38,83,0.25);
            width: 100%; max-width: 500px; margin: 20px;
            animation: modalIn 0.25s ease both; overflow: hidden;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between; color: white;
        }

        .modal-header h3 {
            font-size: 14px; font-weight: 700;
            letter-spacing: 0.5px; text-transform: uppercase;
            display: flex; align-items: center; gap: 8px;
        }

        .modal-close {
            background: rgba(255,255,255,0.15); border: none; color: white;
            width: 28px; height: 28px; border-radius: 8px; font-size: 16px;
            cursor: pointer; line-height: 1;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }

        .modal-close:hover { background: rgba(255,255,255,0.28); }

        .modal-body { padding: 24px 26px 10px; }

        .modal-field {
            display: grid; grid-template-columns: 140px 1fr;
            align-items: center; padding: 10px 0;
            border-bottom: 1px solid #f0f3f9; gap: 12px;
        }

        .modal-field:last-child { border-bottom: none; }

        .modal-field label {
            font-size: 12px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.4px;
        }

        .modal-field input,
        .modal-field select {
            padding: 7px 11px; border: 1.5px solid var(--border);
            border-radius: 8px; font-size: 13px;
            font-family: 'DM Sans', sans-serif; color: var(--text);
            background: var(--bg); outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s; width: 100%;
        }

        .modal-field input:focus,
        .modal-field select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white;
        }

        .modal-field input[readonly] { color: var(--muted); cursor: not-allowed; background: #f4f6fb; }

        .modal-footer {
            padding: 16px 26px 20px;
            display: flex; justify-content: flex-end; gap: 10px;
        }

        .btn-modal-close {
            padding: 8px 20px; background: white; color: var(--muted);
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 13px; font-weight: 600; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: background 0.15s, border-color 0.15s;
        }

        .btn-modal-close:hover { background: var(--tag-bg); border-color: #b8c5e0; }

        .btn-modal-save {
            padding: 8px 22px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; box-shadow: 0 2px 8px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-modal-save:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(15,38,83,0.32); }
        .btn-modal-save:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 28px; right: 28px;
            padding: 12px 20px; border-radius: 10px;
            font-size: 13.5px; font-weight: 600;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            z-index: 9999; display: none;
            animation: toastIn 0.3s ease both;
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .toast.success { background: #edfaf3; color: #1a6e3f; border-left: 4px solid #2ecc71; }
        .toast.error   { background: #fff0f0; color: #c0392b; border-left: 4px solid #e74c3c; }

        @media (max-width: 768px) {
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .modal-field { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="admin_home.php">Home ▾</a>
        <a href="admin_search.php">Search</a>
        <a href="admin_Student.php" class="active">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="#">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <div class="page-header-left">
            <h2>Students Information</h2>
            <p>Manage and view all registered students in the system.</p>
        </div>
        <div class="action-bar">
            <button class="btn-add" onclick="addStudent()">➕ Add Student</button>
            <button class="btn-reset" onclick="resetSessions()">🔄 Reset All Sessions</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="hicon">👥</div>
            Student Records
        </div>

        <div class="table-controls">
            <div class="entries-label">
                Show
                <select id="entriesSelect" onchange="applyFilter()">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                entries
            </div>
            <div class="search-wrap">
                Search:
                <input type="text" id="searchInput" placeholder="Name, ID, course…" oninput="applyFilter()">
            </div>
        </div>

        <div class="table-wrap">
            <table id="studentsTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">ID Number ⬦</th>
                        <th onclick="sortTable(1)">Name ⬦</th>
                        <th onclick="sortTable(2)">Year Level ⬦</th>
                        <th onclick="sortTable(3)">Course ⬦</th>
                        <th onclick="sortTable(4)">Sessions ⬦</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($students)): ?>
                    <tr class="empty-row">
                        <td colspan="6">No students found in the system.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $s): ?>
                    <tr data-id="<?= htmlspecialchars($s['IdNumber']) ?>"
                        data-first="<?= htmlspecialchars($s['FirstName']) ?>"
                        data-middle="<?= htmlspecialchars($s['MiddleName']) ?>"
                        data-last="<?= htmlspecialchars($s['LastName']) ?>"
                        data-email="<?= htmlspecialchars($s['Email'] ?? '') ?>"
                        data-year="<?= htmlspecialchars($s['CourseLvl']) ?>"
                        data-course="<?= htmlspecialchars($s['Course']) ?>"
                        data-sessions="<?= htmlspecialchars($s['sessions'] ?? 0) ?>">
                        <td class="id-cell"><?= htmlspecialchars($s['IdNumber']) ?></td>
                        <td class="col-name"><?= htmlspecialchars(trim($s['FirstName'] . ' ' . $s['MiddleName'] . ' ' . $s['LastName'])) ?></td>
                        <td class="col-year">Year <?= htmlspecialchars($s['CourseLvl']) ?></td>
                        <td class="col-course"><?= htmlspecialchars($s['Course']) ?></td>
                        <td class="col-sessions">
                            <?php if (isset($s['sessions'])): ?>
                            <span class="sess-badge" style="display:inline-flex;align-items:center;gap:5px;background:var(--tag-bg);border:1px solid var(--border);border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:700;color:var(--navy-light);">
                                💻 <?= htmlspecialchars($s['sessions']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-edit" onclick="openEditModal(this)">✏️ Edit</button>
                            <button class="btn-delete" onclick="deleteStudent('<?= htmlspecialchars($s['IdNumber']) ?>')">🗑️ Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span id="paginationInfo">Showing <?= count($students) ?> of <?= count($students) ?> entries</span>
            <div class="page-btns">
                <button class="page-btn" onclick="changePage(-1)">← Previous</button>
                <button class="page-btn active" id="pageIndicator">1</button>
                <button class="page-btn" onclick="changePage(1)">Next →</button>
            </div>
        </div>
    </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay" id="editModalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Edit Student</h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>

        <div class="modal-body">
            <div class="modal-field">
                <label>ID Number</label>
                <input type="text" id="modal_id" readonly>
            </div>
            <div class="modal-field">
                <label>Last Name</label>
                <input type="text" id="modal_last" required maxlength="100">
            </div>
            <div class="modal-field">
                <label>First Name</label>
                <input type="text" id="modal_first" required maxlength="100">
            </div>
            <div class="modal-field">
                <label>Middle Name</label>
                <input type="text" id="modal_middle" maxlength="100">
            </div>
            <div class="modal-field">
                <label>Email</label>
                <input type="email" id="modal_email" maxlength="150">
            </div>
            <div class="modal-field">
                <label>Year Level</label>
                <select id="modal_year">
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="modal-field">
                <label>Course</label>
                <select id="modal_course">
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-field">
                <label>Sessions</label>
                <input type="number" id="modal_sessions" min="0" max="999">
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-modal-close" onclick="closeEditModal()">Close</button>
            <button type="button" class="btn-modal-save" id="btnSave" onclick="saveStudent()">💾 Save Changes</button>
        </div>
    </div>
</div>

<!-- ── TOAST ── -->
<div class="toast" id="toast"></div>

<script>
    let activeRow = null;

    // ── Open modal ──
    function openEditModal(btn) {
        activeRow = btn.closest('tr');
        document.getElementById('modal_id').value       = activeRow.dataset.id;
        document.getElementById('modal_last').value     = activeRow.dataset.last;
        document.getElementById('modal_first').value    = activeRow.dataset.first;
        document.getElementById('modal_middle').value   = activeRow.dataset.middle;
        document.getElementById('modal_email').value    = activeRow.dataset.email;
        document.getElementById('modal_year').value     = activeRow.dataset.year;
        document.getElementById('modal_sessions').value = activeRow.dataset.sessions;

        const courseSelect = document.getElementById('modal_course');
        for (let opt of courseSelect.options) {
            opt.selected = (opt.value === activeRow.dataset.course);
        }

        document.getElementById('editModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    // ── Close modal ──
    function closeEditModal() {
        document.getElementById('editModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
        activeRow = null;
    }

    document.getElementById('editModalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeEditModal();
    });

    // ── Save via AJAX — update row in-place ──
    function saveStudent() {
        const btn = document.getElementById('btnSave');
        btn.disabled = true;
        btn.textContent = '⏳ Saving…';

        const payload = new FormData();
        payload.append('edit_student', '1');
        payload.append('id_number',   document.getElementById('modal_id').value);
        payload.append('last_name',   document.getElementById('modal_last').value);
        payload.append('first_name',  document.getElementById('modal_first').value);
        payload.append('middle_name', document.getElementById('modal_middle').value);
        payload.append('email',       document.getElementById('modal_email').value);
        payload.append('year_level',  document.getElementById('modal_year').value);
        payload.append('course',      document.getElementById('modal_course').value);
        payload.append('sessions',    document.getElementById('modal_sessions').value);

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: payload
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // ── Update data-* attributes on the row ──
                activeRow.dataset.first    = data.first_name;
                activeRow.dataset.middle   = data.middle_name;
                activeRow.dataset.last     = data.last_name;
                activeRow.dataset.email    = data.email;
                activeRow.dataset.year     = data.year_level;
                activeRow.dataset.course   = data.course;
                activeRow.dataset.sessions = data.sessions;

                // ── Update visible cells ──
                const fullName = [data.first_name, data.middle_name, data.last_name]
                    .filter(Boolean).join(' ');
                activeRow.querySelector('.col-name').textContent    = fullName;
                activeRow.querySelector('.col-year').textContent    = 'Year ' + data.year_level;
                activeRow.querySelector('.col-course').textContent  = data.course;

                // Update sessions badge
                const sessCell = activeRow.querySelector('.col-sessions');
                let badge = sessCell.querySelector('.sess-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'sess-badge';
                    badge.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:var(--tag-bg);border:1px solid var(--border);border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:700;color:var(--navy-light);';
                    sessCell.innerHTML = '';
                    sessCell.appendChild(badge);
                }
                badge.textContent = '💻 ' + data.sessions;

                // ── Flash the row green ──
                activeRow.classList.remove('updated');
                void activeRow.offsetWidth; // reflow to restart animation
                activeRow.classList.add('updated');

                closeEditModal();
                showToast('✅ Student ' + data.id + ' updated successfully!', 'success');
            } else {
                showToast('❌ Update failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(() => showToast('❌ Network error. Please try again.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = '💾 Save Changes';
        });
    }

    // ── Toast ──
    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type;
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; }, 3500);
    }

    // ── Table filter / pagination ──
    const allRows = Array.from(document.querySelectorAll('#tableBody tr:not(.empty-row)'));
    let currentPage = 1;

    function applyFilter() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        const perPage = parseInt(document.getElementById('entriesSelect').value);

        const filtered = allRows.filter(row => {
            const match = row.innerText.toLowerCase().includes(q);
            row.style.display = 'none';
            return match;
        });

        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;

        filtered.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        const showing = Math.min(perPage, filtered.length - start);
        document.getElementById('paginationInfo').textContent =
            `Showing ${Math.max(0, showing)} of ${filtered.length} entries`;
    }

    function changePage(dir) {
        currentPage = Math.max(1, currentPage + dir);
        document.getElementById('pageIndicator').textContent = currentPage;
        applyFilter();
    }

    function sortTable(col) {
        const tbody = document.getElementById('tableBody');
        const rows  = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
        rows.sort((a, b) => {
            const aText = a.cells[col]?.innerText.trim() ?? '';
            const bText = b.cells[col]?.innerText.trim() ?? '';
            return aText.localeCompare(bText);
        });
        rows.forEach(r => tbody.appendChild(r));
        applyFilter();
    }

    function deleteStudent(id) {
        if (confirm('Are you sure you want to delete student ' + id + '?')) {
            window.location.href = 'delete_student.php?id=' + id;
        }
    }

    function addStudent()    { window.location.href = 'add_student.php'; }

    function resetSessions() {
        if (confirm('Reset all student sessions? This cannot be undone.')) {
            window.location.href = 'reset_sessions.php';
        }
    }
</script>

</body>
</html>