<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: /SYSARCH/login.php');
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

// Create feedback table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        sitin_id   INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        message    TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ── Fetch all feedback joined with sitin + student info ──
$feedbacks = [];
$result = $conn->query("
    SELECT
        f.id            AS feedback_id,
        f.sitin_id,
        f.message,
        f.created_at,
        f.student_id,
        st.FirstName,
        st.LastName,
        st.Course,
        st.CourseLvl,
        si.lab,
        si.purpose,
        si.time_in,
        si.time_out
    FROM feedback f
    LEFT JOIN sitin si   ON si.id       = f.sitin_id
    LEFT JOIN student st ON st.IdNumber = f.student_id
    ORDER BY f.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
}

$total_feedback = count($feedbacks);

// ── Stats ──

$labs = array_filter(array_column($feedbacks, 'lab'));
$top_lab = !empty($labs) ? array_search(max(array_count_values($labs)), array_count_values($labs)) : '—';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Admin – Feedback Reports</title>
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
            --green:      #1cb87e;
            --green-bg:   #e6f8f2;
            --orange:     #f07b00;
            --orange-bg:  #fff3e0;
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
            display: flex; justify-content: space-between; align-items: center;
            height: 60px; position: sticky; top: 0; z-index: 200;
            box-shadow: 0 4px 20px rgba(15,38,83,0.35);
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .nav-title { font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3; }
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

        /* ── PAGE ── */
        .page-wrapper { padding: 28px 32px 48px; animation: fadeUp 0.45s ease both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; flex-wrap: wrap; gap: 12px;
        }

        .page-header-left { display: flex; align-items: center; gap: 14px; }

        .page-header-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 14px rgba(36,82,160,0.35);
        }

        .page-header-text h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px; color: var(--navy);
        }

        .page-header-text p { font-size: 12.5px; color: var(--muted); margin-top: 2px; }

        /* ── STAT STRIP ── */
        .stat-strip { display: flex; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }

        .stat-pill {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 12px; padding: 14px 20px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 2px 10px rgba(15,38,83,0.06);
            flex: 1; min-width: 150px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-pill:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,38,83,0.1); }

        .sp-icon {
            width: 42px; height: 42px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        .sp-icon.blue  { background: linear-gradient(135deg, var(--navy-light), var(--accent)); }
        .sp-icon.gold  { background: linear-gradient(135deg, var(--orange), var(--gold)); }

        .sp-value { font-family: 'DM Serif Display', serif; font-size: 24px; color: var(--navy); line-height: 1; }
        .sp-label { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

        /* ── CARD ── */
        .card { background: var(--panel); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(15,38,83,0.08); border: 1px solid var(--border); }

        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white; padding: 13px 20px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
        }

        .card-header .hicon {
            width: 28px; height: 28px; background: rgba(255,255,255,0.15);
            border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;
        }

        /* ── TABLE CONTROLS ── */
        .table-controls {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            gap: 12px; flex-wrap: wrap; background: #fafbfd;
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
            transition: border-color 0.18s, box-shadow 0.18s; width: 220px;
        }

        .search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,111,212,0.1); background: white; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }

        thead th {
            background: var(--tag-bg); color: var(--navy);
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; padding: 11px 16px;
            text-align: left; border-bottom: 2px solid var(--border);
            white-space: nowrap; cursor: pointer; user-select: none;
        }

        thead th:hover { color: var(--accent); }

        tbody tr { border-bottom: 1px solid #f0f3f9; transition: background 0.13s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7fd; }
        tbody td { padding: 13px 16px; color: var(--text); vertical-align: middle; }

        .student-cell .s-name { font-weight: 700; color: var(--navy); font-size: 13px; }
        .student-cell .s-id   { font-size: 11.5px; color: var(--muted); font-family: monospace; margin-top: 2px; }
        .student-cell .s-course { font-size: 11px; color: var(--muted); margin-top: 1px; }

        .lab-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: var(--tag-bg); color: var(--navy-light);
            padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; border: 1px solid #c8d4ee;
        }

        .purpose-tag {
            display: inline-flex; align-items: center;
            background: var(--orange-bg); color: var(--orange);
            padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; border: 1px solid #fdd9a0;
        }

        .message-cell {
            max-width: 300px;
            font-size: 13px; color: var(--text); line-height: 1.5;
        }

        .message-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            cursor: pointer;
            transition: color 0.15s;
        }

        .message-preview:hover { color: var(--accent); }

        .read-more {
            font-size: 11px; color: var(--accent); font-weight: 600;
            cursor: pointer; margin-top: 2px; display: inline-block;
        }

        .date-cell { font-size: 12.5px; white-space: nowrap; }
        .date-cell .d-date { font-weight: 700; color: var(--navy); }
        .date-cell .d-time { font-size: 11px; color: var(--muted); margin-top: 1px; }

        .btn-view {
            padding: 5px 14px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white; border: none; border-radius: 6px;
            font-size: 12px; font-weight: 700;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            box-shadow: 0 2px 6px rgba(15,38,83,0.2);
            transition: opacity 0.15s, transform 0.15s;
            white-space: nowrap;
        }

        .btn-view:hover { opacity: 0.88; transform: translateY(-1px); }

        /* ── PAGINATION ── */
        .pagination-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 20px; border-top: 1px solid var(--border);
            font-size: 12.5px; color: var(--muted); flex-wrap: wrap; gap: 10px;
            background: #fafbfd;
        }

        .page-btns { display: flex; gap: 4px; }

        .page-btn {
            padding: 5px 12px; border: 1.5px solid var(--border); border-radius: 7px;
            background: white; font-size: 12.5px; font-family: 'DM Sans', sans-serif;
            color: var(--text); cursor: pointer; transition: background 0.15s, border-color 0.15s;
            display: flex; align-items: center; justify-content: center; min-width: 32px;
        }

        .page-btn:hover:not(:disabled) { background: var(--tag-bg); border-color: var(--accent); color: var(--accent); }
        .page-btn.active { background: linear-gradient(135deg, var(--navy), var(--navy-light)); color: white; border-color: transparent; box-shadow: 0 2px 8px rgba(15,38,83,0.22); }
        .page-btn:disabled { opacity: 0.38; cursor: default; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 64px 20px; color: var(--muted); }
        .empty-state .es-icon { font-size: 52px; margin-bottom: 14px; opacity: 0.4; }
        .empty-state h4 { font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--navy); margin-bottom: 6px; }
        .empty-state p { font-size: 13px; }

        /* ── VIEW MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10,20,50,0.45); backdrop-filter: blur(3px);
            z-index: 999; align-items: center; justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: var(--panel); border-radius: 16px;
            box-shadow: 0 20px 60px rgba(15,38,83,0.25);
            width: 100%; max-width: 520px; overflow: hidden;
            animation: modalIn 0.25s ease both;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-16px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .modal-header h3 { font-size: 15px; font-weight: 700; color: white; }

        .modal-close {
            background: rgba(255,255,255,0.15); border: none; color: white;
            width: 28px; height: 28px; border-radius: 6px; font-size: 16px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: background 0.15s; font-family: 'DM Sans', sans-serif;
        }

        .modal-close:hover { background: rgba(255,255,255,0.28); }

        .modal-body { padding: 24px; }

        .modal-info-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
            margin-bottom: 18px;
        }

        .modal-info-item {
            background: var(--tag-bg); border: 1px solid var(--border);
            border-radius: 9px; padding: 10px 14px;
        }

        .modal-info-item .mi-label {
            font-size: 10px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;
        }

        .modal-info-item .mi-value {
            font-size: 13px; font-weight: 600; color: var(--navy);
        }

        .modal-message-box {
            background: var(--bg); border: 1.5px solid var(--border);
            border-radius: 10px; padding: 16px;
        }

        .modal-message-box .mm-label {
            font-size: 10px; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;
        }

        .modal-message-box .mm-text {
            font-size: 14px; color: var(--text); line-height: 1.7;
            white-space: pre-wrap; word-break: break-word;
        }

        .modal-footer {
            padding: 14px 24px 20px;
            display: flex; justify-content: flex-end;
        }

        .btn-close-modal {
            padding: 9px 24px; background: white; color: var(--text);
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 13px; font-weight: 600;
            font-family: 'DM Sans', sans-serif; cursor: pointer;
            transition: background 0.15s;
        }

        .btn-close-modal:hover { background: var(--tag-bg); }

        @media (max-width: 768px) {
            .nav-title { display: none; }
            .page-wrapper { padding: 20px 16px 40px; }
            .modal-info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-left">
        <img src="/SYSARCH/uclogo-removebg-preview.png" alt="UC Logo">
        <div class="nav-divider"></div>
        <div class="nav-title">College of Computer Studies<br>Sit-in Monitoring System</div>
    </div>
    <div class="nav-links">
        <a href="/SYSARCH/admin/admin_home.php">Home ▾</a>
        <a href="/SYSARCH/admin/admin_search.php">Search</a>
        <a href="/SYSARCH/admin/admin_Student.php">Students</a>
        <a href="/SYSARCH/admin/admin_SitIn.php">Sit-in</a>
        <a href="/SYSARCH/admin/admin_ViewSitInRecords.php">View Sit-in Records</a>
        <a href="/SYSARCH/admin/admin_SitInReports.php">Sit-in Reports</a>
        <a href="/SYSARCH/admin/admin_feedback.php" class="active">Feedback Reports</a>
        <a href="/SYSARCH/admin/admin_reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrapper">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">💬</div>
            <div class="page-header-text">
                <h2>Feedback Reports</h2>
                <p>Student feedback submitted after sit-in sessions.</p>
            </div>
        </div>
    </div>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="stat-pill">
            <div class="sp-icon blue">💬</div>
            <div>
                <div class="sp-value"><?= $total_feedback ?></div>
                <div class="sp-label">Total Feedback</div>
            </div>
        </div>
    
        <div class="stat-pill">
            <div class="sp-icon gold">🏛️</div>
            <div>
                <div class="sp-value"><?= htmlspecialchars($top_lab) ?></div>
                <div class="sp-label">Most Feedback Lab</div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="hicon">📋</div>
            Student Feedback
        </div>

        <?php if (empty($feedbacks)): ?>
        <div class="empty-state">
            <div class="es-icon">💬</div>
            <h4>No Feedback Yet</h4>
            <p>Student feedback will appear here once submitted from the history page.</p>
        </div>

        <?php else: ?>

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
                <input type="text" id="searchInput" placeholder="Name, ID, lab, message…" oninput="applyFilter()">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">#</th>
                        <th onclick="sortTable(1)">Student</th>
                        <th onclick="sortTable(2)">Lab</th>
                        <th onclick="sortTable(3)">Purpose</th>
                        <th onclick="sortTable(4)">Message</th>
                        <th onclick="sortTable(5)">Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($feedbacks as $i => $fb):
                        $full_name = trim(($fb['FirstName'] ?? '') . ' ' . ($fb['LastName'] ?? ''));
                        if (empty(trim($full_name))) $full_name = 'Unknown Student';
                        $course_info = trim(($fb['Course'] ?? '') . ($fb['CourseLvl'] ? ' – Year ' . $fb['CourseLvl'] : ''));
                        $date_fmt = $fb['created_at'] ? date('M j, Y', strtotime($fb['created_at'])) : '—';
                        $time_fmt = $fb['created_at'] ? date('h:i A', strtotime($fb['created_at'])) : '';

                        // Escape for JS attribute
                        $js_name     = addslashes($full_name);
                        $js_id       = addslashes($fb['student_id'] ?? '');
                        $js_course   = addslashes($course_info);
                        $js_lab      = addslashes($fb['lab'] ?? '—');
                        $js_purpose  = addslashes($fb['purpose'] ?? '—');
                        $js_message  = addslashes($fb['message'] ?? '');
                        $js_date     = addslashes($date_fmt . ' ' . $time_fmt);
                        $js_sitin    = $fb['sitin_id'] ?? '—';
                    ?>
                    <tr>
                        <td style="color:var(--muted); font-size:12px; font-weight:600;"><?= $i + 1 ?></td>
                        <td>
                            <div class="student-cell">
                                <div class="s-name"><?= htmlspecialchars($full_name) ?></div>
                                <div class="s-id"><?= htmlspecialchars($fb['student_id'] ?? '—') ?></div>
                                <?php if ($course_info): ?>
                                <div class="s-course"><?= htmlspecialchars($course_info) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="lab-tag">🏛️ <?= htmlspecialchars($fb['lab'] ?? '—') ?></span></td>
                        <td><span class="purpose-tag"><?= htmlspecialchars($fb['purpose'] ?? '—') ?></span></td>
                        <td class="message-cell">
                            <div class="message-preview"
                                 onclick="viewFeedback('<?= $js_name ?>', '<?= $js_id ?>', '<?= $js_course ?>', '<?= $js_lab ?>', '<?= $js_purpose ?>', '<?= $js_message ?>', '<?= $js_date ?>', <?= $js_sitin ?>)">
                                <?= htmlspecialchars(mb_substr($fb['message'] ?? '', 0, 120)) ?><?= mb_strlen($fb['message'] ?? '') > 120 ? '…' : '' ?>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <div class="d-date"><?= htmlspecialchars($date_fmt) ?></div>
                                <div class="d-time"><?= htmlspecialchars($time_fmt) ?></div>
                            </div>
                        </td>
                        <td>
                            <button class="btn-view"
                                onclick="viewFeedback('<?= $js_name ?>', '<?= $js_id ?>', '<?= $js_course ?>', '<?= $js_lab ?>', '<?= $js_purpose ?>', '<?= $js_message ?>', '<?= $js_date ?>', <?= $js_sitin ?>)">
                                👁 View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span id="paginationInfo"></span>
            <div class="page-btns" id="pageBtns"></div>
        </div>

        <?php endif; ?>
    </div>

</div>

<!-- VIEW FEEDBACK MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h3>💬 Feedback Detail</h3>
            <button class="modal-close" onclick="closeModal()">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <div class="mi-label">Student Name</div>
                    <div class="mi-value" id="m_name">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="mi-label">ID Number</div>
                    <div class="mi-value" id="m_id" style="font-family:monospace;">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="mi-label">Course</div>
                    <div class="mi-value" id="m_course">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="mi-label">Sit-in #</div>
                    <div class="mi-value" id="m_sitin">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="mi-label">Laboratory</div>
                    <div class="mi-value" id="m_lab">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="mi-label">Purpose</div>
                    <div class="mi-value" id="m_purpose">—</div>
                </div>
                <div class="modal-info-item" style="grid-column: span 2;">
                    <div class="mi-label">Submitted On</div>
                    <div class="mi-value" id="m_date">—</div>
                </div>
            </div>

            <div class="modal-message-box">
                <div class="mm-label">📝 Feedback Message</div>
                <div class="mm-text" id="m_message">—</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
    const allRows = Array.from(document.querySelectorAll('#tableBody tr'));
    let currentPage = 1;
    let filtered = [...allRows];

    function applyFilter() {
        const q       = document.getElementById('searchInput')?.value.toLowerCase() ?? '';
        const perPage = parseInt(document.getElementById('entriesSelect')?.value ?? 10);

        filtered = allRows.filter(row => {
            const match = row.innerText.toLowerCase().includes(q);
            row.style.display = 'none';
            return match;
        });

        currentPage = 1;
        renderPage(perPage);
    }

    function renderPage(perPage) {
        perPage = perPage || parseInt(document.getElementById('entriesSelect')?.value ?? 10);
        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;

        filtered.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        const showing    = Math.min(perPage, Math.max(0, filtered.length - start));

        const info = document.getElementById('paginationInfo');
        if (info) info.textContent = `Showing ${start + 1}–${start + showing} of ${filtered.length} entries`;

        buildPageBtns(totalPages);
    }

    function buildPageBtns(totalPages) {
        const container = document.getElementById('pageBtns');
        if (!container) return;
        container.innerHTML = '';

        const prev = makeBtn('← Prev', currentPage > 1, () => { currentPage--; renderPage(); });
        container.appendChild(prev);

        for (let p = 1; p <= totalPages; p++) {
            if (totalPages > 7 && p > 2 && p < totalPages - 1 && Math.abs(p - currentPage) > 1) {
                if (p === 3 || p === totalPages - 2) {
                    const dots = document.createElement('span');
                    dots.style.cssText = 'padding:0 6px;color:var(--muted);display:flex;align-items:center;font-size:13px;';
                    dots.textContent = '…';
                    container.appendChild(dots);
                }
                continue;
            }
            const btn = makeBtn(p, true, (function(page) { return () => { currentPage = page; renderPage(); }; })(p));
            if (p === currentPage) btn.classList.add('active');
            container.appendChild(btn);
        }

        const next = makeBtn('Next →', currentPage < totalPages, () => { currentPage++; renderPage(); });
        container.appendChild(next);
    }

    function makeBtn(label, enabled, onClick) {
        const btn = document.createElement('button');
        btn.className   = 'page-btn';
        btn.textContent = label;
        btn.disabled    = !enabled;
        if (enabled) btn.addEventListener('click', onClick);
        return btn;
    }

    function sortTable(col) {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => (a.cells[col]?.innerText.trim() ?? '').localeCompare(b.cells[col]?.innerText.trim() ?? ''));
        rows.forEach(r => tbody.appendChild(r));
        filtered = rows;
        currentPage = 1;
        renderPage();
    }

    // Init
    renderPage();

    // ── MODAL ──
    function viewFeedback(name, id, course, lab, purpose, message, date, sitinId) {
        document.getElementById('m_name').textContent    = name    || '—';
        document.getElementById('m_id').textContent      = id      || '—';
        document.getElementById('m_course').textContent  = course  || '—';
        document.getElementById('m_lab').textContent     = lab     || '—';
        document.getElementById('m_purpose').textContent = purpose || '—';
        document.getElementById('m_message').textContent = message || '—';
        document.getElementById('m_date').textContent    = date    || '—';
        document.getElementById('m_sitin').textContent   = '#' + sitinId;
        document.getElementById('viewModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('viewModal').classList.remove('open');
    }

    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>

</body>
</html>