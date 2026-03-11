<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();

// Fetch stats
$totalUsers = $conn->query("SELECT COUNT(*) as c FROM tbl_user")->fetch_assoc()['c'];
$totalFarmers = $conn->query("SELECT COUNT(*) as c FROM tbl_user WHERE user_type_id = 1")->fetch_assoc()['c'];
$totalTechs = $conn->query("SELECT COUNT(*) as c FROM tbl_user WHERE user_type_id = 2")->fetch_assoc()['c'];
$totalReports = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report")->fetch_assoc()['c'];
$pendingReports = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report WHERE status='Pending'")->fetch_assoc()['c'];
$verifiedReports = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report WHERE status='Verified'")->fetch_assoc()['c'];
$totalDiagnoses = $conn->query("SELECT COUNT(*) as c FROM tbl_ai_diagnosis")->fetch_assoc()['c'];
$totalClaims = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim")->fetch_assoc()['c'];
$pendingClaims = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Pending'")->fetch_assoc()['c'];
$paidClaims = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Paid'")->fetch_assoc()['c'];
$totalVerifications = $conn->query("SELECT COUNT(*) as c FROM tbl_verification")->fetch_assoc()['c'];

// 1. Disease Distribution (from verifications)
$diseaseDist = $conn->query(
    "SELECT d.disease_name, COUNT(v.verification_id) as cnt
     FROM tbl_verification v JOIN tbl_disease d ON v.disease_id = d.disease_id
     GROUP BY v.disease_id ORDER BY cnt DESC"
);
$diseaseLabels = []; $diseaseCounts = [];
while($row = $diseaseDist->fetch_assoc()) {
    $diseaseLabels[] = $row['disease_name'];
    $diseaseCounts[] = (int)$row['cnt'];
}

// 2. Reporting Trends (last 14 days)
$trends = $conn->query(
    "SELECT DATE(reported_at) as d, COUNT(*) as cnt
     FROM tbl_disease_report
     WHERE reported_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(reported_at) ORDER BY d ASC"
);
$trendLabels = []; $trendCounts = [];
// Fill gaps with 0
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M d', strtotime($date));
    $trendCounts[$date] = 0;
}
while($row = $trends->fetch_assoc()) {
    $trendCounts[$row['d']] = (int)$row['cnt'];
}
$trendValues = array_values($trendCounts);

// 3. Claim Status Distribution
$claimDist = $conn->query("SELECT status, COUNT(*) as cnt FROM tbl_insurance_claim GROUP BY status");
$claimLabels = []; $claimCounts = [];
while($row = $claimDist->fetch_assoc()) {
    $claimLabels[] = $row['status'];
    $claimCounts[] = (int)$row['cnt'];
}

// 4. KPI: Verification Rate
$vRate = ($totalReports > 0) ? round(($totalVerifications / $totalReports) * 100, 1) : 0;
// 5. KPI: Average AI Confidence
$avgConf = $conn->query("SELECT AVG(confidence_score) as a FROM tbl_ai_diagnosis")->fetch_assoc()['a'];
$avgConf = $avgConf ? round($avgConf, 1) : 0;

// Recent reports
$recentReports = $conn->query(
    "SELECT r.report_id, r.status, r.reported_at, r.field_location, u.fname, u.lname
     FROM tbl_disease_report r JOIN tbl_user u ON r.user_id = u.user_id
     ORDER BY r.reported_at DESC LIMIT 6"
);

// Recent claims
$recentClaims = $conn->query(
    "SELECT ic.claim_id, ic.status, ic.damage_type, ic.created_at,
            u.fname, u.lname, r.field_location
     FROM tbl_insurance_claim ic
     JOIN tbl_user u ON ic.user_id = u.user_id
     JOIN tbl_disease_report r ON ic.report_id = r.report_id
     ORDER BY ic.created_at DESC LIMIT 6"
);

$conn->close();

// Greeting based on time
$hour = (int)date('G');
if ($hour < 12) $greeting = '☀️ Good Morning';
elseif ($hour < 18) $greeting = '🌤️ Good Afternoon';
else $greeting = '🌙 Good Evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }

        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h1 { font-size: 26px; font-weight: 800; color: #1B5E20; }
        .page-header p { color: #666; font-size: 14px; margin-top: 4px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: white; border-radius: 16px; padding: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(0,0,0,0.02);
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-bottom: 14px;
        }
        .stat-value { font-size: 28px; font-weight: 800; color: #1A1A2E; }
        .stat-label { font-size: 13px; color: #666; margin-top: 4px; font-weight: 600; }
        .stat-sub { font-size: 11px; color: #999; margin-top: 2px; }

        .kpi-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: #2E7D32; border-radius: 16px; padding: 20px; color: white; display: flex; align-items: center; gap: 16px; }
        .kpi-icon { font-size: 32px; opacity: 0.8; }
        .kpi-data h4 { font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-data div { font-size: 24px; font-weight: 800; }

        .grid-charts { 
            display: grid; 
            grid-template-columns: 1.5fr 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 24px; 
        }
        @media (max-width: 1200px) {
            .grid-charts { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        
        .grid-tables { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .card { 
            background: white; border-radius: 16px; padding: 24px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            border: 1px solid rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
        }
        .chart-container { position: relative; height: 220px; width: 100%; }
        .card-title { font-size: 15px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; color: #333; }
        .card-title i { color: #2E7D32; font-size: 18px; }
        .card-title a { font-size: 12px; color: #2E7D32; text-decoration: none; margin-left: auto; font-weight: 600; }

        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; font-size: 11px; color: #999; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f9f9f9; }
        .table tr:hover td { background: #fcfcfc; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-pending { background: #FFF3E0; color: #BF360C; }
        .status-verified { background: #E3F2FD; color: #0D47A1; }
        .status-treated { background: #E8F5E9; color: #1B5E20; }
        .status-paid { background: #F3E5F5; color: #4A148C; }

        canvas { width: 100% !important; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><?= $greeting ?>, <?= htmlspecialchars($adminName) ?>!</h1>
                <p>Monitoring the health of our rice fields today.</p>
            </div>
            <div style="text-align: right; color: #999; font-size: 12px;">
                <i class="ri-calendar-line"></i> <?= date('F d, Y') ?>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-row">
            <div class="kpi-card" style="background: linear-gradient(135deg, #1B5E20, #2E7D32);">
                <div class="kpi-icon"><i class="ri-shield-check-line"></i></div>
                <div class="kpi-data">
                    <h4>Verification Rate</h4>
                    <div><?= $vRate ?>%</div>
                </div>
            </div>
            <div class="kpi-card" style="background: linear-gradient(135deg, #0D47A1, #1976D2);">
                <div class="kpi-icon"><i class="ri-robot-line"></i></div>
                <div class="kpi-data">
                    <h4>Avg. AI Confidence</h4>
                    <div><?= $avgConf ?>%</div>
                </div>
            </div>
        </div>

        <!-- Primary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#E8F5E9; color:#2E7D32;"><i class="ri-team-line"></i></div>
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-sub"><?= $totalFarmers ?> farmers · <?= $totalTechs ?> techs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#FFF3E0; color:#E65100;"><i class="ri-file-search-line"></i></div>
                <div class="stat-value"><?= $totalReports ?></div>
                <div class="stat-label">Disease Reports</div>
                <div class="stat-sub"><?= $pendingReports ?> pending · <?= $verifiedReports ?> verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#E3F2FD; color:#1565C0;"><i class="ri-brain-line"></i></div>
                <div class="stat-value"><?= $totalDiagnoses ?></div>
                <div class="stat-label">AI Diagnoses</div>
                <div class="stat-sub"><?= $totalVerifications ?> verified by techs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#FCE4EC; color:#C62828;"><i class="ri-heart-pulse-line"></i></div>
                <div class="stat-value"><?= $totalClaims ?></div>
                <div class="stat-label">Claims Issued</div>
                <div class="stat-sub"><?= $pendingClaims ?> pending · <?= $paidClaims ?> paid</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid-charts">
            <div class="card">
                <div class="card-title"><i class="ri-line-chart-line"></i> Reporting Trends</div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="ri-pie-chart-line"></i> Disease Dist.</div>
                <div class="chart-container">
                    <canvas id="diseaseChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="ri-shield-star-line"></i> Claims Status</div>
                <div class="chart-container">
                    <canvas id="claimChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="grid-tables">
            <div class="card">
                <div class="card-title"><i class="ri-history-line"></i> Recent Reports <a href="reports.php">View All →</a></div>
                <table class="table">
                    <thead><tr><th>Farmer</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while ($r = $recentReports->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></div>
                                <div style="font-size:11px; color:#999"><?= htmlspecialchars($r['field_location'] ?: 'Unknown') ?></div>
                            </td>
                            <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
                            <td><?= date('M d', strtotime($r['reported_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-title"><i class="ri-refund-2-line"></i> Insurance Claims <a href="claims.php">View All →</a></div>
                <table class="table">
                    <thead><tr><th>Farmer</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while ($c = $recentClaims->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($c['fname'] . ' ' . $c['lname']) ?></div>
                                <div style="font-size:11px; color:#999"><?= htmlspecialchars($c['damage_type']) ?></div>
                            </td>
                            <td><span class="status-badge status-<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span></td>
                            <td><?= date('M d', strtotime($c['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // 1. Trend Chart
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Reports',
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#2E7D32',
                backgroundColor: 'rgba(46, 125, 50, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#2E7D32'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Disease Chart
    new Chart(document.getElementById('diseaseChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($diseaseLabels) ?>,
            datasets: [{
                data: <?= json_encode($diseaseCounts) ?>,
                backgroundColor: ['#2E7D32', '#4CAF50', '#81C784', '#A5D6A7', '#C8E6C9'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
            },
            cutout: '70%'
        }
    });

    // 3. Claim Chart
    new Chart(document.getElementById('claimChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($claimLabels) ?>,
            datasets: [{
                data: <?= json_encode($claimCounts) ?>,
                backgroundColor: ['#BF360C', '#1B5E20', '#0D47A1', '#4A148C'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
            }
        }
    });
    </script>
</body>
</html>
