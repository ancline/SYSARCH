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

$select_cols = $session_col
    ? "IdNumber, LastName, FirstName, MiddleName, CourseLvl, Course, `$session_col` AS sessions"
    : "IdNumber, LastName, FirstName, MiddleName, CourseLvl, Course";

// Fetch all students
$result = $conn->query("SELECT $select_cols FROM student");
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

        /* ── NAVBAR ── */
        .navbar {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            padding: 0 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-left img {
            height: 38px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .nav-title {
            font-size: 13.5px;
            font-weight: 600;
            color: rgba(255,255,255,0.92);
            letter-spacing: 0.3px;
            line-height: 1.3;
        }

        .nav-divider {
            width: 1px;
            height: 28px;
            background: rgba(255,255,255,0.2);
            margin: 0 6px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 7px 13px;
            border-radius: 6px;
            transition: background 0.18s, color 0.18s;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.12);
            color: white;
        }

        .nav-links a.active {
            background: rgba(255,255,255,0.18);
            color: white;
            font-weight: 700;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important;
            color: #fff !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            padding: 7px 18px !important;
            margin-left: 6px;
            box-shadow: 0 2px 8px rgba(240,165,0,0.4);
            transition: transform 0.15s, box-shadow 0.15s !important;
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(240,165,0,0.5) !important;
        }

        /* ── PAGE ── */
        .page-wrapper {
            padding: 28px 32px 48px;
            animation: fadeUp 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header-left h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--navy);
            margin-bottom: 2px;
        }

        .page-header-left p {
            font-size: 12.5px;
            color: var(--muted);
        }

        .action-bar {
            display: flex;
            gap: 10px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-add:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(15,38,83,0.32);
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: white;
            color: #c0392b;
            border: 1.5px solid #e8b4b4;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-reset:hover {
            background: #fff0f0;
            border-color: #e74c3c;
        }

        /* ── CARD ── */
        .card {
            background: var(--panel);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
            border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white;
            padding: 13px 20px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* ── TABLE CONTROLS ── */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
            flex-wrap: wrap;
        }

        .entries-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .entries-label select {
            padding: 5px 10px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: var(--bg);
            outline: none;
            cursor: pointer;
        }

        .search-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .search-wrap input {
            padding: 6px 12px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: var(--bg);
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            width: 200px;
        }

        .search-wrap input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,111,212,0.1);
            background: white;
        }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            background: var(--tag-bg);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
        }

        thead th:hover { color: var(--navy); }

        tbody tr {
            border-bottom: 1px solid #f0f3f9;
            transition: background 0.13s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--tag-bg); }

        tbody td {
            padding: 11px 20px;
            color: var(--text);
            vertical-align: middle;
        }

        .id-cell {
            font-family: monospace;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--navy-light);
        }

        .btn-edit {
            padding: 5px 14px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            margin-right: 5px;
            transition: opacity 0.15s, transform 0.15s;
        }

        .btn-edit:hover { opacity: 0.88; transform: translateY(-1px); }

        .btn-delete {
            padding: 5px 14px;
            background: white;
            color: #c0392b;
            border: 1.5px solid #e8b4b4;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-delete:hover {
            background: #fff0f0;
            border-color: #e74c3c;
        }

        /* ── PAGINATION ── */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            font-size: 12.5px;
            color: var(--muted);
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-btns { display: flex; gap: 4px; }

        .page-btn {
            padding: 5px 13px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            background: white;
            font-size: 12.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .page-btn:hover {
            background: var(--tag-bg);
            border-color: var(--accent);
        }

        .page-btn.active {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
        }

        .empty-row td {
            text-align: center;
            color: var(--muted);
            padding: 40px 20px !important;
            font-size: 13.5px;
        }

        @media (max-width: 768px) {
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
            .page-header { flex-direction: column; align-items: flex-start; }
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
                    <tr data-id="<?= htmlspecialchars($s['IdNumber']) ?>">
                        <td class="id-cell"><?= htmlspecialchars($s['IdNumber']) ?></td>
                        <td><?= htmlspecialchars(trim($s['FirstName'] . ' ' . $s['MiddleName'] . ' ' . $s['LastName'])) ?></td>
                        <td>Year <?= htmlspecialchars($s['CourseLvl']) ?></td>
                        <td><?= htmlspecialchars($s['Course']) ?></td>
                        <td>
                            <?php if (isset($s['sessions'])): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;background:var(--tag-bg);border:1px solid var(--border);border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:700;color:var(--navy-light);">
                                💻 <?= htmlspecialchars($s['sessions']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-edit" onclick="editStudent('<?= $s['IdNumber'] ?>')">✏️ Edit</button>
                            <button class="btn-delete" onclick="deleteStudent('<?= $s['IdNumber'] ?>')">🗑️ Delete</button>
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

<script>
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

    function editStudent(id) {
        window.location.href = 'edit_student.php?id=' + id;
    }

    function deleteStudent(id) {
        if (confirm('Are you sure you want to delete student ' + id + '?')) {
            window.location.href = 'delete_student.php?id=' + id;
        }
    }

    function addStudent() {
        window.location.href = 'add_student.php';
    }

    function resetSessions() {
        if (confirm('Reset all student sessions? This cannot be undone.')) {
            window.location.href = 'reset_sessions.php';
        }
    }
</script>

</body>
</html>