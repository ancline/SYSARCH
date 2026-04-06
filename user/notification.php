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

// Fetch all notifications for this student
$notifications = [];
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $nstmt = $conn->prepare("
        SELECT id, title, message, created_at, is_read
        FROM notifications
        WHERE student_id = ? OR student_id IS NULL
        ORDER BY created_at DESC
    ");
    $nstmt->bind_param('s', $_SESSION['student_id']);
    $nstmt->execute();
    $nr = $nstmt->get_result();
    while ($nrow = $nr->fetch_assoc()) {
        $notifications[] = $nrow;
    }
    $nstmt->close();
}

// Auto-mark all as read when visiting this page
if (!empty($notifications)) {
    $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0
    ")->execute() ?: null;
    // Re-bind properly
    $upstmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE (student_id = ? OR student_id IS NULL) AND is_read = 0
    ");
    $upstmt->bind_param('s', $_SESSION['student_id']);
    $upstmt->execute();
    $upstmt->close();
}

$unread_count = count(array_filter($notifications, fn($n) => empty($n['is_read'])));
$total_count  = count($notifications);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications — CCS Sit-in Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Serif+Display&display=swap" rel="stylesheet">

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
            --unread-bg:  #f0f4ff;
            --unread-border: #c0d0f0;
            --success:    #16a34a;
            --danger:     #dc2626;
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

        .nav-title {
            font-size: 13.5px; font-weight: 600;
            color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3;
        }

        .nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 13px; font-weight: 500; padding: 7px 13px;
            border-radius: 6px; transition: background 0.18s, color 0.18s; letter-spacing: 0.2px;
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

        /* ── PAGE WRAPPER ── */
        .page-wrap {
            max-width: 860px;
            margin: 32px auto;
            padding: 0 20px 48px;
            animation: fadeUp 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: flex-end; justify-content: space-between;
            margin-bottom: 22px; gap: 12px; flex-wrap: wrap;
        }


        .breadcrumb {
            font-size: 12px; color: var(--muted); font-weight: 500;
            display: flex; align-items: center; gap: 6px;
            margin-bottom: 6px;
        }

        .breadcrumb a { color: var(--accent); text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--navy); }
        .breadcrumb .sep { opacity: 0.5; }

        .page-title {
            font-family: 'DM Serif Display', serif;
            font-size: 26px; color: var(--navy); line-height: 1.2;
            display: flex; align-items: center; gap: 10px;
        }

        .page-title .title-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        .page-subtitle {
            font-size: 13px; color: var(--muted); margin-top: 4px; font-weight: 400;
        }

        /* ── ACTION BAR ── */
        .action-bar {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }

        .stat-pill {
            display: flex; align-items: center; gap: 6px;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 20px; padding: 6px 14px;
            font-size: 12px; font-weight: 600; color: var(--muted);
            box-shadow: 0 1px 4px rgba(15,38,83,0.06);
        }

        .stat-pill .dot {
            width: 7px; height: 7px; border-radius: 50%;
        }

        .dot-unread { background: var(--accent); }
        .dot-total  { background: var(--border); }

        .btn-action {
            background: var(--panel); border: 1px solid var(--border);
            color: var(--text); font-size: 12px; font-weight: 600;
            padding: 7px 14px; border-radius: 8px; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex; align-items: center; gap: 5px;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
            box-shadow: 0 1px 4px rgba(15,38,83,0.06);
        }

        .btn-action:hover {
            background: var(--tag-bg); border-color: #b8c5e0;
            box-shadow: 0 2px 8px rgba(15,38,83,0.1);
        }

        .btn-action.danger { color: var(--danger); border-color: #fca5a5; }
        .btn-action.danger:hover { background: #fff5f5; border-color: var(--danger); }

        /* ── FILTER TABS ── */
        .filter-tabs {
            display: flex; align-items: center; gap: 4px;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 10px; padding: 4px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(15,38,83,0.06);
        }

        .filter-tab {
            flex: 1; text-align: center;
            padding: 8px 16px; border-radius: 7px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            color: var(--muted); transition: background 0.18s, color 0.18s;
            user-select: none;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            color: white;
            box-shadow: 0 2px 8px rgba(15,38,83,0.25);
        }

        .filter-tab:not(.active):hover { background: var(--tag-bg); color: var(--navy); }

        .tab-count {
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.25);
            min-width: 18px; height: 18px; border-radius: 9px;
            font-size: 10px; font-weight: 700; padding: 0 5px;
            margin-left: 5px;
        }

        .filter-tab:not(.active) .tab-count {
            background: var(--border);
            color: var(--muted);
        }

        /* ── NOTIFICATIONS CARD ── */
        .notif-card {
            background: var(--panel);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(15,38,83,0.08);
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
        }

        .empty-state .empty-icon {
            font-size: 56px; margin-bottom: 16px; opacity: 0.5;
            display: block;
        }

        .empty-state h3 {
            font-size: 16px; font-weight: 700; color: var(--navy); margin-bottom: 6px;
        }

        .empty-state p { font-size: 13px; color: var(--muted); }

        /* ── NOTIFICATION ROW ── */
        .notif-row {
            display: flex; gap: 16px; align-items: flex-start;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
            position: relative;
        }

        .notif-row:last-child { border-bottom: none; }

        .notif-row:hover { background: #fafbfd; }

        .notif-row.unread {
            background: var(--unread-bg);
        }

        .notif-row.unread:hover { background: #e4ecff; }

        /* Left accent bar for unread */
        .notif-row.unread::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--accent), var(--navy-light));
            border-radius: 0 2px 2px 0;
        }

        .notif-row-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
            background: var(--tag-bg);
        }

        .notif-row.unread .notif-row-icon {
            background: linear-gradient(135deg, var(--navy-light), var(--accent));
        }

        .notif-row-body { flex: 1; min-width: 0; }

        .notif-row-top {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 10px; margin-bottom: 5px;
        }

        .notif-row-title {
            font-size: 14px; font-weight: 700; color: var(--navy);
            line-height: 1.3;
        }

        .notif-row.unread .notif-row-title { color: var(--navy); }

        .notif-row-meta {
            display: flex; align-items: center; gap: 8px; flex-shrink: 0;
        }

        .notif-row-time {
            font-size: 11.5px; color: var(--muted); font-weight: 500; white-space: nowrap;
        }

        .unread-badge {
            background: var(--accent);
            color: white; font-size: 9px; font-weight: 700;
            padding: 2px 7px; border-radius: 10px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .notif-row-msg {
            font-size: 13px; color: #556; line-height: 1.65;
        }

        /* ── DATE SEPARATOR ── */
        .date-sep {
            padding: 10px 22px 6px;
            font-size: 11px; font-weight: 700;
            color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px;
            background: var(--tag-bg);
            border-bottom: 1px solid var(--border);
        }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 28px; right: 28px;
            background: var(--navy);
            color: white;
            font-size: 13px; font-weight: 600;
            padding: 13px 20px; border-radius: 12px;
            box-shadow: 0 8px 32px rgba(15,38,83,0.35);
            display: flex; align-items: center; gap: 8px;
            z-index: 999;
            transform: translateY(80px); opacity: 0;
            transition: transform 0.3s cubic-bezier(.34,1.56,.64,1), opacity 0.3s;
            pointer-events: none;
        }

        .toast.show { transform: translateY(0); opacity: 1; }

        @media (max-width: 640px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .notif-row { padding: 14px 16px; gap: 12px; }
            .notif-row-icon { width: 36px; height: 36px; font-size: 17px; border-radius: 9px; }
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
        <a href="/SYSARCH/user/user_home.php">Home</a>
        <a href="/SYSARCH/user/user_edit_profile.php">Edit Profile</a>
        <a href="/SYSARCH/user/history.php">History</a>
        <a href="/SYSARCH/user/reservation.php">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </div>
</div>

<!-- PAGE -->
<div class="page-wrap">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="breadcrumb">
                <a href="user_home.php">Home</a>
                <span class="sep">/</span>
                <span>Notifications</span>
            </div>
            <div class="page-title">
                <div class="title-icon">🔔</div>
                Notifications
            </div>
            <div class="page-subtitle">
                All messages and updates for your account
            </div>
        </div>

        <div class="action-bar">
            <?php if ($unread_count > 0): ?>
            <div class="stat-pill">
                <span class="dot dot-unread"></span>
                <?= $unread_count ?> unread
            </div>
            <?php endif; ?>
            <div class="stat-pill">
                <span class="dot dot-total"></span>
                <?= $total_count ?> total
            </div>
            <?php if (!empty($notifications)): ?>
            <button class="btn-action" onclick="markAllRead()">✔ Mark all read</button>
            <button class="btn-action danger" onclick="confirmClearAll()">🗑 Clear all</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER TABS -->
    <?php
        $unread_count_raw = count(array_filter($notifications, fn($n) => empty($n['is_read'])));
        $read_count       = $total_count - $unread_count_raw;
    ?>
    <div class="filter-tabs" id="filterTabs">
        <div class="filter-tab active" data-filter="all" onclick="filterNotifs('all', this)">
            All <span class="tab-count"><?= $total_count ?></span>
        </div>
        <div class="filter-tab" data-filter="unread" onclick="filterNotifs('unread', this)">
            Unread <span class="tab-count"><?= $unread_count_raw ?></span>
        </div>
        <div class="filter-tab" data-filter="read" onclick="filterNotifs('read', this)">
            Read <span class="tab-count"><?= $read_count ?></span>
        </div>
    </div>

    <!-- NOTIFICATIONS CARD -->
    <div class="notif-card" id="notifCard">

        <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <span class="empty-icon">🔕</span>
            <h3>No notifications yet</h3>
            <p>You're all caught up! We'll notify you here when something comes in.</p>
        </div>

        <?php else: ?>
            <?php
            // Group by relative date label
            $grouped = [];
            foreach ($notifications as $n) {
                $ts    = strtotime($n['created_at']);
                $today = strtotime('today');
                $yest  = strtotime('yesterday');

                if ($ts >= $today)          $label = 'Today';
                elseif ($ts >= $yest)       $label = 'Yesterday';
                elseif ($ts >= $today - 6*86400) $label = 'This Week';
                else                        $label = date('F Y', $ts);

                $grouped[$label][] = $n;
            }
            ?>

            <?php foreach ($grouped as $label => $items): ?>
            <div class="date-sep notif-group-header"><?= htmlspecialchars($label) ?></div>

            <?php foreach ($items as $n):
                $is_unread = empty($n['is_read']);
                $ts   = strtotime($n['created_at']);
                $diff = time() - $ts;

                if ($diff < 60)          $time_ago = 'just now';
                elseif ($diff < 3600)    $time_ago = floor($diff/60) . 'm ago';
                elseif ($diff < 86400)   $time_ago = floor($diff/3600) . 'h ago';
                elseif ($diff < 172800)  $time_ago = 'Yesterday, ' . date('g:i A', $ts);
                else                     $time_ago = date('M j, Y · g:i A', $ts);
            ?>
            <div class="notif-row <?= $is_unread ? 'unread' : 'read' ?>"
                 data-id="<?= (int)($n['id'] ?? 0) ?>"
                 data-read="<?= $is_unread ? '0' : '1' ?>">

                <div class="notif-row-icon">
                    <?= $is_unread ? '📬' : '📭' ?>
                </div>

                <div class="notif-row-body">
                    <div class="notif-row-top">
                        <div class="notif-row-title">
                            <?= htmlspecialchars($n['title'] ?? 'Notification') ?>
                        </div>
                        <div class="notif-row-meta">
                            <?php if ($is_unread): ?>
                            <span class="unread-badge">New</span>
                            <?php endif; ?>
                            <span class="notif-row-time"><?= $time_ago ?></span>
                        </div>
                    </div>
                    <div class="notif-row-msg">
                        <?= htmlspecialchars($n['message'] ?? '') ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
    // ── Filter tabs ──
    function filterNotifs(filter, tabEl) {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        tabEl.classList.add('active');

        document.querySelectorAll('.notif-row').forEach(row => {
            const isUnread = row.classList.contains('unread');
            if (filter === 'all')    row.style.display = '';
            else if (filter === 'unread') row.style.display = isUnread ? '' : 'none';
            else if (filter === 'read')   row.style.display = !isUnread ? '' : 'none';
        });

        // Hide date separators that have no visible rows after them
        document.querySelectorAll('.date-sep').forEach(sep => {
            let next = sep.nextElementSibling;
            let hasVisible = false;
            while (next && !next.classList.contains('date-sep')) {
                if (next.classList.contains('notif-row') && next.style.display !== 'none') {
                    hasVisible = true; break;
                }
                next = next.nextElementSibling;
            }
            sep.style.display = hasVisible ? '' : 'none';
        });
    }

    // ── Mark all read ──
    function markAllRead() {
        document.querySelectorAll('.notif-row.unread').forEach(row => {
            row.classList.remove('unread');
            row.classList.add('read');
            row.dataset.read = '1';
            const icon = row.querySelector('.notif-row-icon');
            if (icon) icon.textContent = '📭';
            const badge = row.querySelector('.unread-badge');
            if (badge) badge.remove();
        });

        // AJAX
        fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: '<?= htmlspecialchars($_SESSION['student_id']) ?>' })
        }).catch(() => {});

        showToast('✔ All notifications marked as read');

        // Update unread tab count
        document.querySelector('[data-filter="unread"] .tab-count').textContent = '0';
    }

    // ── Clear all (visual + server) ──
    function confirmClearAll() {
        if (!confirm('Clear all notifications? This cannot be undone.')) return;

        const card = document.getElementById('notifCard');
        card.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon">🔕</span>
                <h3>No notifications yet</h3>
                <p>You're all caught up! We'll notify you here when something comes in.</p>
            </div>`;

        fetch('clear_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: '<?= htmlspecialchars($_SESSION['student_id']) ?>' })
        }).catch(() => {});

        showToast('🗑 All notifications cleared');

        // Reset counts
        document.querySelectorAll('.tab-count').forEach(el => el.textContent = '0');
        document.querySelector('.stat-pill .dot-total')?.closest('.stat-pill')
            && (document.querySelector('.stat-pill .dot-total').closest('.stat-pill').innerHTML =
                '<span class="dot dot-total"></span> 0 total');
    }

    // ── Toast ──
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
</script>

</body>
</html>