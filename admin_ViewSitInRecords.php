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

// Make sure sitin table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS sitin (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   VARCHAR(50)  NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        lab          VARCHAR(100) NOT NULL,
        purpose      VARCHAR(255) DEFAULT '',
        time_in      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        time_out     DATETIME     DEFAULT NULL
    )
");

// Add sessions column to sitin if missing (migration safety)
$conn->query("ALTER TABLE sitin ADD COLUMN IF NOT EXISTS sessions INT DEFAULT NULL");

// JOIN student table to get live session count
$result = $conn->query("
    SELECT s.id, s.student_id, s.student_name, s.purpose, s.lab,
           st.sessions, s.time_in, s.time_out
    FROM sitin s
    LEFT JOIN student st ON st.IdNumber = s.student_id
    ORDER BY s.id DESC
");

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin - View Sit-in Records</title>
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

        .nav-left { display: flex; align-items: center; gap: 12px; }

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

        .nav-links { display: flex; align-items: center; gap: 2px; }

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

        .nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }

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
            text-align: center;
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--navy);
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
            padding: 11px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            white-space: nowrap;
            user-select: none;
        }

        thead th:hover { color: var(--navy); }

        thead th.sort-asc::after  { content: ' ▲'; font-size: 9px; }
        thead th.sort-desc::after { content: ' ▼'; font-size: 9px; }

        tbody tr {
            border-bottom: 1px solid #f0f3f9;
            transition: background 0.13s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--tag-bg); }

        tbody td {
            padding: 11px 16px;
            color: var(--text);
            vertical-align: middle;
        }

        .id-cell {
            font-family: monospace;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--navy-light);
        }

        .session-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--tag-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--navy-light);
        }

        /* ── TIME CELLS ── */
        .time-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .time-date {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
        }

        .time-clock {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--navy);
        }

        .time-none {
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .status-active { background: #e6f9f0; color: #1a7a4a; }
        .status-done   { background: var(--tag-bg); color: var(--muted); }

        .btn-timeout {
            padding: 5px 13px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(240,165,0,0.3);
            transition: opacity 0.15s, transform 0.15s;
        }

        .btn-timeout:hover { opacity: 0.88; transform: translateY(-1px); }

        .completed-text {
            font-size: 12px;
            color: var(--muted);
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
            padding: 5px 11px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            background: white;
            font-size: 12.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .page-btn:hover { background: var(--tag-bg); border-color: var(--accent); }

        .page-btn.active {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(15,38,83,0.22);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
        <a href="admin_Student.php">Students</a>
        <a href="admin_SitIn.php">Sit-in</a>
        <a href="admin_ViewSitInRecords.php" class="active">View Sit-in Records</a>
        <a href="#">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <h2>Current Sit-in</h2>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="hicon">📋</div>
            Sit-in Records
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
                entries per page
            </div>
            <div class="search-wrap">
                Search:
                <input type="text" id="searchInput" placeholder="Name, ID, lab, purpose…" oninput="applyFilter()">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">ID Number</th>
                        <th onclick="sortTable(1)">Name</th>
                        <th onclick="sortTable(2)">Purpose</th>
                        <th onclick="sortTable(3)">Sit Lab</th>
                        <th onclick="sortTable(4)">Session</th>
                        <th onclick="sortTable(5)">Time In</th>
                        <th onclick="sortTable(6)">Time Out</th>
                        <th onclick="sortTable(7)">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($records)): ?>
                    <tr class="empty-row">
                        <td colspan="9">No data available</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($records as $r):
                        $is_active = empty($r['time_out']);
                    ?>
                    <tr data-id="<?= $r['id'] ?>">
                        <td class="id-cell"><?= htmlspecialchars($r['student_id']) ?></td>
                        <td><?= htmlspecialchars($r['student_name']) ?></td>
                        <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['lab'] ?? '—') ?></td>
                        <td>
                            <?php if (!is_null($r['sessions'])): ?>
                                <span class="session-pill">💻 <?= htmlspecialchars($r['sessions']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['time_in'])): ?>
                                <div class="time-cell">
                                    <span class="time-date"><?= date('M d, Y', strtotime($r['time_in'])) ?></span>
                                    <span class="time-clock">🕐 <?= date('h:i A', strtotime($r['time_in'])) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="time-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['time_out'])): ?>
                                <div class="time-cell">
                                    <span class="time-date"><?= date('M d, Y', strtotime($r['time_out'])) ?></span>
                                    <span class="time-clock">🕐 <?= date('h:i A', strtotime($r['time_out'])) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="time-none">Still active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $is_active ? 'status-active' : 'status-done' ?>">
                                <?= $is_active ? '🟢 Active' : '⚪ Done' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <button class="btn-timeout" onclick="timeOut(<?= $r['id'] ?>)">🔚 Time Out</button>
                            <?php else: ?>
                                <span class="completed-text">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span id="paginationInfo">
                Showing 1 to <?= min(10, count($records)) ?> of <?= count($records) ?> entries
            </span>
            <div class="page-btns">
                <button class="page-btn" id="btnFirst"    onclick="goPage(1)">«</button>
                <button class="page-btn" id="btnPrev"     onclick="goPage(currentPage - 1)">‹</button>
                <button class="page-btn active" id="pageIndicator">1</button>
                <button class="page-btn" id="btnNext"     onclick="goPage(currentPage + 1)">›</button>
                <button class="page-btn" id="btnLast"     onclick="goPage(totalPages())">»</button>
            </div>
        </div>
    </div>
</div>

<script>
    const allRows = Array.from(document.querySelectorAll('#tableBody tr:not(.empty-row)'));
    let currentPage = 1;
    let sortCol = -1, sortAsc = true;
    let filtered = [...allRows];

    function totalPages() {
        const perPage = parseInt(document.getElementById('entriesSelect').value);
        return Math.max(1, Math.ceil(filtered.length / perPage));
    }

    function applyFilter() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        const perPage = parseInt(document.getElementById('entriesSelect').value);

        filtered = allRows.filter(row => row.innerText.toLowerCase().includes(q));
        allRows.forEach(r => r.style.display = 'none');

        currentPage = 1;
        renderPage(perPage);
    }

    function renderPage(perPage) {
        perPage = perPage || parseInt(document.getElementById('entriesSelect').value);
        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;

        allRows.forEach(r => r.style.display = 'none');
        filtered.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        const showing = Math.min(end, filtered.length);
        const from    = filtered.length ? start + 1 : 0;
        document.getElementById('paginationInfo').textContent =
            `Showing ${from} to ${showing} of ${filtered.length} entries`;
        document.getElementById('pageIndicator').textContent = currentPage;

        document.getElementById('btnFirst').disabled = currentPage === 1;
        document.getElementById('btnPrev').disabled  = currentPage === 1;
        document.getElementById('btnNext').disabled  = currentPage >= totalPages();
        document.getElementById('btnLast').disabled  = currentPage >= totalPages();
    }

    function goPage(p) {
        p = Math.max(1, Math.min(p, totalPages()));
        currentPage = p;
        renderPage();
    }

    function sortTable(col) {
        const headers = document.querySelectorAll('thead th');
        headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));

        if (sortCol === col) {
            sortAsc = !sortAsc;
        } else {
            sortCol = col;
            sortAsc = true;
        }

        headers[col].classList.add(sortAsc ? 'sort-asc' : 'sort-desc');

        const tbody = document.getElementById('tableBody');
        allRows.sort((a, b) => {
            const aText = a.cells[col]?.innerText.trim() ?? '';
            const bText = b.cells[col]?.innerText.trim() ?? '';
            return sortAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        allRows.forEach(r => tbody.appendChild(r));

        const q = document.getElementById('searchInput').value.toLowerCase();
        filtered = allRows.filter(row => row.innerText.toLowerCase().includes(q));
        currentPage = 1;
        renderPage();
    }

    function timeOut(id) {
        if (confirm('Mark this session as timed out?')) {
            window.location.href = 'timeout_sitin.php?id=' + id;
        }
    }

    // Initial render
    renderPage();
</script>

</body>
</html>