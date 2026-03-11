<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();

$sql = "SELECT v.*, d.disease_name, 
               u_tech.fname as tech_fname, u_tech.lname as tech_lname,
               u_farmer.fname as farmer_fname, u_farmer.lname as farmer_lname,
               u_farmer.contact_number as farmer_contact,
               r.field_location, r.status as report_status,
               COALESCE(a.barangay,'') as barangay
        FROM tbl_verification v
        JOIN tbl_disease d ON v.disease_id = d.disease_id
        JOIN tbl_user u_tech ON v.user_id = u_tech.user_id
        JOIN tbl_disease_report r ON v.report_id = r.report_id
        JOIN tbl_user u_farmer ON r.user_id = u_farmer.user_id
        LEFT JOIN tbl_address a ON u_farmer.user_id = a.user_id
        ORDER BY v.verified_at DESC";
$verifications = $conn->query($sql);

$totalCount = $conn->query("SELECT COUNT(*) as c FROM tbl_verification")->fetch_assoc()['c'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifications — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 26px; font-weight: 800; }
        .page-header .badge { background: #E3F2FD; color: #1565C0; padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: 700; }
        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; font-size: 11px; color: #999; text-transform: uppercase; border-bottom: 2px solid #eee; }
        .table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
        .table tr:hover { background: #fafafa; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-verified { background: #E3F2FD; color: #1565C0; }
        .status-treated { background: #E8F5E9; color: #2E7D32; }
        .status-pending { background: #FFF3E0; color: #E65100; }

        .method-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600;
            background: #E8F5E9; color: #2E7D32;
        }
        .disease-tag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 10px; font-size: 12px; font-weight: 700;
            background: #FCE4EC; color: #C62828;
        }
        .notes-text { font-size: 12px; color: #666; font-style: italic; margin-top: 4px; }
        .empty { text-align: center; padding: 50px; color: #999; }
        .empty i { font-size: 48px; display: block; margin-bottom: 12px; color: #ddd; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>✅ Verification Oversight</h1>
            <div class="badge"><?= $totalCount ?> total verifications</div>
        </div>

        <div class="card">
            <?php if ($totalCount > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Report</th>
                        <th>Farmer</th>
                        <th>Disease Confirmed</th>
                        <th>Verified By</th>
                        <th>Method</th>
                        <th>Report Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($v = $verifications->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $v['verification_id'] ?></strong></td>
                        <td>
                            <div>#<?= $v['report_id'] ?></div>
                            <div style="font-size:11px; color:#999"><?= htmlspecialchars($v['field_location'] ?: '—') ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($v['farmer_fname'] . ' ' . $v['farmer_lname']) ?></div>
                            <div style="font-size:11px; color:#999"><?= htmlspecialchars($v['farmer_contact']) ?><?= $v['barangay'] ? ' • ' . htmlspecialchars($v['barangay']) : '' ?></div>
                        </td>
                        <td><span class="disease-tag">🦠 <?= htmlspecialchars($v['disease_name']) ?></span></td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($v['tech_fname'] . ' ' . $v['tech_lname']) ?></div>
                        </td>
                        <td><span class="method-badge"><i class="ri-checkbox-circle-line"></i> <?= htmlspecialchars($v['verification_method']) ?></span></td>
                        <td><span class="status-badge status-<?= strtolower($v['report_status']) ?>"><?= $v['report_status'] ?></span></td>
                        <td>
                            <div><?= date('M d, Y', strtotime($v['verified_at'])) ?></div>
                            <div style="font-size:11px; color:#999"><?= date('h:i A', strtotime($v['verified_at'])) ?></div>
                            <?php if ($v['notes']): ?>
                                <div class="notes-text">📝 <?= htmlspecialchars(substr($v['notes'], 0, 60)) ?><?= strlen($v['notes']) > 60 ? '...' : '' ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <i class="ri-checkbox-circle-line"></i>
                <div>No verifications have been submitted yet.</div>
                <div style="margin-top:4px; font-size:12px">Technologists will verify farmer reports from the mobile app.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
