<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCS Sit-in Monitoring System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display&display=swap" rel="stylesheet">

<?php
// ── Fetch top 10 from DB ──────────────────────────────────────────────
$host = 'localhost';
$db   = 'students';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);

$top10 = [];
if (!$conn->connect_error) {
    $result = $conn->query("
        SELECT student_id, student_name,
               SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out)) AS total_minutes
        FROM sitin
        WHERE time_in IS NOT NULL AND time_out IS NOT NULL
        GROUP BY student_id, student_name
        ORDER BY total_minutes DESC
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $top10[] = $row;
    }
    $conn->close();
}

$av_colors = ['av-gold','av-blue','av-teal','av-red','av-green','av-purple','av-bronze','av-pink','av-orange','av-cyan'];

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= strtoupper(mb_substr($p, 0, 1));
    return $out;
}

function shortName($name) {
    $parts = explode(' ', trim($name));
    $first = $parts[0];
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) . '.' : '';
    return $last ? "$first $last" : $first;
}

// Format total minutes → "Xh Ym", "Xh", or "Ym"
function formatDuration($minutes) {
    $minutes = (int)$minutes;
    if ($minutes <= 0) return '0m';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0 && $m > 0) return "{$h}h {$m}m";
    if ($h > 0) return "{$h}h";
    return "{$m}m";
}
?>

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
body { font-family: 'DM Sans', sans-serif; background-color: var(--bg); min-height: 100vh; color: var(--text); }

/* NAVBAR */
.navbar { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%); padding: 0 28px; display: flex; justify-content: space-between; align-items: center; height: 60px; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(15,38,83,0.35); }
.nav-left { display: flex; align-items: center; gap: 12px; }
.nav-left img { height: 38px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
.nav-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); margin: 0 6px; }
.nav-title { font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,0.92); letter-spacing: 0.3px; line-height: 1.3; }
.nav-links { display: flex; align-items: center; gap: 2px; }
.nav-links a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; font-weight: 500; padding: 7px 13px; border-radius: 6px; transition: background 0.18s, color 0.18s; }
.nav-links a:hover { background: rgba(255,255,255,0.12); color: white; }
.nav-links a.active { background: rgba(255,255,255,0.18); color: white; font-weight: 700; }
.btn-register { background: linear-gradient(135deg, var(--gold), var(--gold-light)) !important; color: #7a4800 !important; font-weight: 700 !important; border-radius: 8px !important; padding: 7px 18px !important; margin-left: 6px; box-shadow: 0 2px 8px rgba(240,165,0,0.4); }

/* MAIN */
.main { display: flex; justify-content: center; align-items: stretch; min-height: calc(100vh - 60px); padding: 48px 24px; gap: 24px; flex-wrap: wrap; }

/* SHARED CARD */
.welcome-card, .leaderboard-card { background: var(--panel); border-radius: 20px; box-shadow: 0 4px 24px rgba(15,38,83,0.10); border: 1px solid var(--border); width: 100%; max-width: 560px; overflow: hidden; display: flex; flex-direction: column; animation: fadeUp 0.5s ease both; }
.leaderboard-card { animation-delay: 0.1s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

/* WELCOME CARD */
.card-top { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 36px 40px 28px; display: flex; flex-direction: column; align-items: center; }
.card-top img { width: 110px; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3)); margin-bottom: 18px; }
.card-top h2 { font-family: 'DM Serif Display', serif; font-size: 22px; color: white; text-align: center; line-height: 1.35; }
.card-body { padding: 28px 36px 32px; text-align: center; flex: 1; display: flex; flex-direction: column; justify-content: center; }
.card-body p { font-size: 14px; color: var(--muted); line-height: 1.7; margin-bottom: 28px; }
.features { display: flex; justify-content: center; gap: 12px; margin-bottom: 28px; flex-wrap: wrap; }
.feature-pill { background: var(--tag-bg); color: var(--navy-light); font-size: 11.5px; font-weight: 600; padding: 5px 13px; border-radius: 20px; border: 1px solid var(--border); }
.btn-start { display: inline-block; padding: 12px 36px; background: linear-gradient(135deg, var(--navy), var(--navy-light)); color: white; text-decoration: none; border-radius: 10px; font-size: 14px; font-weight: 700; box-shadow: 0 3px 12px rgba(15,38,83,0.25); transition: transform 0.15s, box-shadow 0.15s; }
.btn-start:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,38,83,0.35); }
.card-footer { padding: 0 36px 24px; text-align: center; font-size: 13px; color: var(--muted); }
.card-footer a { color: var(--accent); font-weight: 600; text-decoration: none; }
.card-footer a:hover { text-decoration: underline; }
.divider { height: 1px; background: var(--border); margin: 0 36px 20px; }

/* LEADERBOARD */
.lb-header { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 24px 28px 22px; display: flex; align-items: center; gap: 14px; }
.lb-icon { width: 46px; height: 46px; border-radius: 10px; background: rgba(240,165,0,0.2); border: 1px solid rgba(240,165,0,0.4); display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.lb-title-wrap h3 { font-family: 'DM Serif Display', serif; font-size: 18px; color: white; line-height: 1.2; }
.lb-title-wrap p { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 3px; }
.lb-body { padding: 20px 22px 4px; flex: 1; display: flex; flex-direction: column; }

/* PODIUM */
.podium { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin-bottom: 20px; padding: 0 8px; }
.podium-item { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; min-width: 0; }
.podium-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; position: relative; flex-shrink: 0; }
.podium-crown { position: absolute; top: -14px; font-size: 14px; line-height: 1; }
.podium-name { font-size: 11.5px; font-weight: 600; color: var(--text); text-align: center; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; }
.podium-duration { font-size: 10.5px; color: var(--muted); text-align: center; }
.podium-bar { width: 100%; border-radius: 8px 8px 0 0; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; }
.p1-bar { height: 74px; background: linear-gradient(180deg, var(--gold) 0%, var(--gold-light) 100%); color: #7a4800; }
.p2-bar { height: 54px; background: linear-gradient(180deg, #c8d0e0 0%, #dde3ee 100%); color: var(--muted); }
.p3-bar { height: 40px; background: linear-gradient(180deg, #d4a574 0%, #e8c9a0 100%); color: #7a5a30; }

.lb-footer { border-top: 1px solid var(--border); padding: 14px 22px; text-align: center; margin-top: auto; }
.lb-footer button { background: none; border: none; font-size: 13px; color: var(--accent); font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; padding: 0; }
.lb-footer button:hover { text-decoration: underline; }

.empty-state { text-align: center; padding: 40px 20px; color: var(--muted); font-size: 14px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; }
.empty-state .empty-icon { font-size: 36px; }

/* AVATAR COLORS */
.av-gold   { background: #fef3d0; color: #7a4800; }
.av-blue   { background: #e0eaff; color: #1a3a80; }
.av-teal   { background: #d8f5f0; color: #0f5a50; }
.av-red    { background: #ffe8e8; color: #801a1a; }
.av-green  { background: #e2f5e0; color: #1a6010; }
.av-purple { background: #ede8ff; color: #3a1a80; }
.av-bronze { background: #f5e8d8; color: #7a5a30; }
.av-pink   { background: #ffe0f0; color: #801a50; }
.av-orange { background: #fff0e0; color: #804000; }
.av-cyan   { background: #e0f8ff; color: #006080; }

/* MODAL */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(10,20,50,0.55); backdrop-filter: blur(3px); z-index: 500; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.open { display: flex; }
.modal { background: var(--panel); border-radius: 20px; width: 100%; max-width: 520px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(15,38,83,0.35); animation: modalIn 0.25s ease both; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.modal-header { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); padding: 22px 28px; display: flex; align-items: center; justify-content: space-between; }
.modal-header-left { display: flex; align-items: center; gap: 12px; }
.modal-header-icon { font-size: 22px; }
.modal-header h3 { font-family: 'DM Serif Display', serif; font-size: 18px; color: white; }
.modal-header p { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 2px; }
.modal-close { background: rgba(255,255,255,0.15); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
.modal-close:hover { background: rgba(255,255,255,0.28); }
.modal-body { padding: 20px 24px 24px; overflow-y: auto; }
.modal-rank-list { display: flex; flex-direction: column; gap: 8px; }
.modal-rank-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); transition: background 0.15s; }
.modal-rank-item:hover { background: #e4e9f5; }
.modal-rank-item.top-1 { background: #fffbf0; border-color: #f0d080; }
.modal-rank-item.top-2 { background: #f6f8ff; border-color: #c8d0e0; }
.modal-rank-item.top-3 { background: #fdf6f0; border-color: #ddb890; }
.modal-rank-num { font-size: 20px; min-width: 28px; text-align: center; }
.rank-plain { font-size: 13px; font-weight: 700; color: var(--muted); }
.modal-rank-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.modal-rank-info { flex: 1; min-width: 0; }
.modal-rank-name { font-size: 13.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.modal-rank-id { font-size: 11px; color: var(--muted); margin-top: 2px; }
.modal-rank-duration { text-align: right; flex-shrink: 0; }
.modal-rank-duration .num { font-size: 15px; font-weight: 700; color: var(--navy); display: block; }
.modal-rank-duration .lbl { font-size: 10px; color: var(--muted); }
.modal-empty { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; }
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
        <a href="landingpage.php" class="active">Home</a>
        <a href="community.php">Community</a>
        <a href="about.php">About</a>
        <a href="login.php">Login</a>
        <a href="register.php" class="btn-register">Register</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- LEADERBOARD CARD (left) -->
    <div class="leaderboard-card">
        <div class="lb-header">
            <div class="lb-icon">🏆</div>
            <div class="lb-title-wrap">
                <h3>Top Sit-in Students</h3>
                <p>Longest total lab time this semester</p>
            </div>
        </div>

        <div class="lb-body">
            <?php if (count($top10) >= 3):
                $p1 = $top10[0]; $p2 = $top10[1]; $p3 = $top10[2];
            ?>
            <div class="podium">
                <!-- 2nd place -->
                <div class="podium-item">
                    <div class="podium-avatar av-blue"><?= htmlspecialchars(getInitials($p2['student_name'])) ?></div>
                    <div class="podium-name"><?= htmlspecialchars(shortName($p2['student_name'])) ?></div>
                    <div class="podium-duration"><?= formatDuration($p2['total_minutes']) ?></div>
                    <div class="podium-bar p2-bar">2</div>
                </div>
                <!-- 1st place -->
                <div class="podium-item">
                    <div class="podium-avatar av-gold" style="width:50px;height:50px;font-size:15px;position:relative;">
                        <span class="podium-crown">👑</span>
                        <?= htmlspecialchars(getInitials($p1['student_name'])) ?>
                    </div>
                    <div class="podium-name"><?= htmlspecialchars(shortName($p1['student_name'])) ?></div>
                    <div class="podium-duration"><?= formatDuration($p1['total_minutes']) ?></div>
                    <div class="podium-bar p1-bar">1</div>
                </div>
                <!-- 3rd place -->
                <div class="podium-item">
                    <div class="podium-avatar av-bronze"><?= htmlspecialchars(getInitials($p3['student_name'])) ?></div>
                    <div class="podium-name"><?= htmlspecialchars(shortName($p3['student_name'])) ?></div>
                    <div class="podium-duration"><?= formatDuration($p3['total_minutes']) ?></div>
                    <div class="podium-bar p3-bar">3</div>
                </div>
            </div>


            <?php if (count($top10) > 3): ?>
            <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;">
                <?php foreach (array_slice($top10, 3) as $i => $s):
                    $rank = $i + 4;
                    $avClass = $av_colors[$rank % count($av_colors)];
                ?>
                <div style="display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:10px; background:var(--bg); border:1px solid var(--border);">
                    <div style="font-size:12px; font-weight:700; color:var(--muted); min-width:20px; text-align:center;"><?= $rank ?></div>
                    <div class="modal-rank-avatar <?= $avClass ?>" style="width:32px;height:32px;font-size:11px;flex-shrink:0;"><?= htmlspecialchars(getInitials($s['student_name'])) ?></div>
                    <div style="flex:1; min-width:0; font-size:12.5px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(shortName($s['student_name'])) ?></div>
                    <div style="font-size:12px; font-weight:700; color:var(--navy); flex-shrink:0;"><?= formatDuration($s['total_minutes']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php elseif (count($top10) > 0): ?>
            <div style="padding: 10px 0; display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($top10 as $i => $s): ?>
                <div class="modal-rank-item">
                    <div class="modal-rank-num rank-plain"><?= $i+1 ?></div>
                    <div class="modal-rank-avatar <?= $av_colors[$i % count($av_colors)] ?>"><?= htmlspecialchars(getInitials($s['student_name'])) ?></div>
                    <div class="modal-rank-info">
                        <div class="modal-rank-name"><?= htmlspecialchars($s['student_name']) ?></div>
                        <div class="modal-rank-id"><?= htmlspecialchars($s['student_id']) ?></div>
                    </div>
                    <div class="modal-rank-duration">
                        <span class="num"><?= formatDuration($s['total_minutes']) ?></span>
                        <span class="lbl">total time</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">📋</span>
                <p>No sit-in records yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="lb-footer">
            <button onclick="document.getElementById('lbModal').classList.add('open')">View full leaderboard →</button>
        </div>
    </div>

    <!-- WELCOME CARD (right) -->
    <div class="welcome-card">
        <div class="card-top">
            <img src="ucmainccslogo.png" alt="CCS Logo">
            <h2>Welcome to the Sit-in Monitoring System</h2>
        </div>
        <div class="card-body">
            <p>Monitor student laboratory sit-ins, manage sessions, and track activity inside the College of Computer Studies laboratories.</p>
            <div class="features">
                <span class="feature-pill">📋 Session Tracking</span>
                <span class="feature-pill">🖥️ Lab Monitoring</span>
                <span class="feature-pill">📅 Reservations</span>
            </div>
            <a href="login.php" class="btn-start">Get Started</a>
        </div>
        <div class="divider"></div>
        <div class="card-footer">
            Already have an account? <a href="login.php">Log in here</a>
        </div>
    </div>

</div>

<!-- TOP 10 MODAL -->
<div class="modal-overlay" id="lbModal" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon">🏆</div>
                <div>
                    <h3>Full Leaderboard</h3>
                    <p>Top 10 students by total lab time</p>
                </div>
            </div>
            <button class="modal-close" onclick="document.getElementById('lbModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
            <?php if (count($top10) > 0): ?>
            <div class="modal-rank-list">
                <?php foreach ($top10 as $i => $s):
                    $rank    = $i + 1;
                    $avClass = $av_colors[$i % count($av_colors)];
                    $topCls  = $rank <= 3 ? "top-{$rank}" : '';
                    $medal   = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : "<span class='rank-plain'>{$rank}</span>"));
                ?>
                <div class="modal-rank-item <?= $topCls ?>">
                    <div class="modal-rank-num"><?= $medal ?></div>
                    <div class="modal-rank-avatar <?= $avClass ?>"><?= htmlspecialchars(getInitials($s['student_name'])) ?></div>
                    <div class="modal-rank-info">
                        <div class="modal-rank-name"><?= htmlspecialchars($s['student_name']) ?></div>
                        <div class="modal-rank-id"><?= htmlspecialchars($s['student_id']) ?></div>
                    </div>
                    <div class="modal-rank-duration">
                        <span class="num"><?= formatDuration($s['total_minutes']) ?></span>
                        <span class="lbl">total time</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="modal-empty">No records found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('lbModal').classList.remove('open');
});
</script>

</body>
</html>