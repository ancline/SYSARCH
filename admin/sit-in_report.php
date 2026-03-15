<?php
// Simulated data - replace with real DB queries
$students_registered = 11;
$currently_sitin = 0;
$total_sitin = 14;

$announcements = [
    [
        'author' => 'CCS Admin',
        'date' => '2026-Feb-11',
        'message' => ''
    ],
    [
        'author' => 'CCS Admin',
        'date' => '2024-May-08',
        'message' => 'Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!'
    ]
];

// Language distribution data for chart
$lang_data = [
    'C#'      => ['count' => 5, 'color' => '#3B9BDC'],
    'C'       => ['count' => 1, 'color' => '#E84B6A'],
    'Java'    => ['count' => 3, 'color' => '#F47C30'],
    'ASP.Net' => ['count' => 4, 'color' => '#F5C842'],
    'Php'     => ['count' => 1, 'color' => '#3EC9A0'],
];
$total_lang = array_sum(array_column($lang_data, 'count'));

// Handle new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['announcement'])) {
    $new_announcement = [
        'author' => 'CCS Admin',
        'date' => date('Y-M-d'),
        'message' => $_POST['announcement']
    ];
    array_unshift($announcements, $new_announcement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Sit-in Report</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'IBM Plex Sans', sans-serif; background: #F0F4FA; color: #1E293B; margin: 0; padding: 0; }
        .top-nav { background: #9757d6; padding: 0 24px; display: flex; justify-content: space-between; align-items: center; height: 56px; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
        .top-nav .brand { color: #fff; font-weight: 600; font-size: 15px; }
        .top-nav nav a { color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 13.5px; transition: background .2s; }
        .top-nav nav a:hover { background: rgba(255,255,255,.15); }
        .top-nav nav a.active { background: rgba(255,255,255,.12); font-weight: 600; }
        .btn-logout { background: #f0a500; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .btn-logout:hover { background: #d4920a; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        .card { background: #fff; border: 1px solid #CBD5E1; border-radius: 10px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .card-header { font-weight: 700; font-size: .9rem; color: #1A3A5C; margin-bottom: 1rem; padding-bottom: .6rem; border-bottom: 2px solid #2563EB; }
        .stat-row { margin-bottom: .45rem; }
        .stat-label { font-weight: 600; }
        .chart-wrap { display: flex; align-items: center; gap: 1.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .pie-svg { width: 180px; height: 180px; }
        .legend { display: flex; flex-direction: column; gap: .45rem; }
        .legend-item { display: flex; align-items: center; gap: .5rem; font-size: .8rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 2px; }
        .announce-textarea { width: 100%; min-height: 80px; border: 1px solid #CBD5E1; border-radius: 6px; padding: .65rem .85rem; font-family: inherit; resize: vertical; }
        .announce-textarea:focus { border-color: #2563EB; }
        .btn-submit { margin-top: .75rem; background: #16A34A; color: #fff; border: none; padding: .5rem 1.4rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #15803D; }
        .posted-title { font-size: 1.25rem; font-weight: 700; margin: 1.25rem 0 .75rem; }
        .announce-item { padding: .75rem 0; border-bottom: 1px solid #CBD5E1; }
        .announce-item:last-child { border-bottom: none; }
        .announce-meta { font-weight: 700; font-size: .82rem; color: #1A3A5C; margin-bottom: .3rem; }
        .announce-text { font-size: .85rem; line-height: 1.5; }
    </style>
</head>
<body>
<header class="top-nav">
    <div style="display:flex;align-items:center;gap:10px;">
        <img src="/SYSARCH/uclogo-removebg-preview.png" alt="UC Logo" style="height:36px;">
        <span class="brand">College of Computer Studies Admin</span>
    </div>
    <nav>
        <a href="#" class="active">Home</a>
        <a href="#">Search</a>
        <a href="#">Students</a>
        <a href="#">Sit-in</a>
        <a href="#">View Sit-in Records</a>
        <a href="#">Sit-in Reports</a>
        <a href="#">Feedback Reports</a>
        <a href="#">Reservation</a>
        <a href="/SYSARCH/landingpage.php" class="btn-logout">Log out</a>
    </nav>
</header>

<main class="container">
    <div class="grid">
        <div class="card">
            <div class="card-header">Statistics</div>
            <div class="stat-row">
                <span class="stat-label">Students Registered:</span>
                <span class="stat-value"><?php echo $students_registered; ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Currently Sit-in:</span>
                <span class="stat-value"><?php echo $currently_sitin; ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Total Sit-in:</span>
                <span class="stat-value"><?php echo $total_sitin; ?></span>
            </div>
            <div class="chart-wrap">
                <svg class="pie-svg" viewBox="-1 -1 2 2" style="transform:rotate(-90deg)">
                    <?php
                    $cumulative = 0;
                    foreach ($lang_data as $lang => $info) {
                        $ratio = $info['count'] / $total_lang;
                        $startX = cos(2 * M_PI * $cumulative);
                        $startY = sin(2 * M_PI * $cumulative);
                        $cumulative += $ratio;
                        $endX = cos(2 * M_PI * $cumulative);
                        $endY = sin(2 * M_PI * $cumulative);
                        $largeArc = $ratio > 0.5 ? 1 : 0;
                        echo "<path d=\"M 0 0 L {$startX} {$startY} A 1 1 0 {$largeArc} 1 {$endX} {$endY} Z\" fill=\"{$info['color']}\" stroke=\"#fff\" stroke-width=\"0.025\"/>";
                    }
                    ?>
                </svg>
                <div class="legend">
                    <?php foreach ($lang_data as $lang => $info): ?>
                    <div class="legend-item">
                        <span class="legend-dot" style="background:<?php echo $info['color']; ?>"></span>
                        <span><?php echo htmlspecialchars($lang); ?> (<?php echo $info['count']; ?>)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Announcement</div>
            <form method="POST">
                <textarea class="announce-textarea" name="announcement" placeholder="New Announcement"><?php echo isset($_POST['announcement']) ? htmlspecialchars($_POST['announcement']) : ''; ?></textarea>
                <button type="submit" class="btn-submit">Submit</button>
            </form>
            <div class="posted-title">Posted Announcements</div>
            <?php foreach ($announcements as $ann): ?>
            <div class="announce-item">
                <div class="announce-meta"><?php echo htmlspecialchars($ann['author']); ?> | <?php echo htmlspecialchars($ann['date']); ?></div>
                <?php if (!empty($ann['message'])): ?>
                <div class="announce-text"><?php echo htmlspecialchars($ann['message']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
</body>
</html>