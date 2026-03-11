<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_id'], $_POST['new_status'])) {
    $stmt = $conn->prepare("UPDATE tbl_insurance_claim SET status = ? WHERE claim_id = ?");
    $stmt->bind_param("si", $_POST['new_status'], $_POST['claim_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: claims.php?msg=updated');
    exit;
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT ic.*, u.fname, u.lname, u.contact_number,
               COALESCE(a.barangay,'') as barangay, COALESCE(a.municipality,'') as municipality,
               r.field_location, r.status as report_status, r.description as report_desc,
               r.reported_at,
               (SELECT GROUP_CONCAT(di.image_path) FROM tbl_disease_image di WHERE di.report_id = r.report_id) as images,
               (SELECT d2.disease_name FROM tbl_ai_diagnosis ad2 JOIN tbl_disease d2 ON ad2.disease_id = d2.disease_id WHERE ad2.report_id = ic.report_id ORDER BY ad2.confidence_score DESC LIMIT 1) as ai_disease
        FROM tbl_insurance_claim ic
        JOIN tbl_user u ON ic.user_id = u.user_id
        JOIN tbl_disease_report r ON ic.report_id = r.report_id
        LEFT JOIN tbl_address a ON u.user_id = a.user_id
        WHERE 1=1";
if ($filter) $sql .= " AND ic.status = '" . $conn->real_escape_string($filter) . "'";
$sql .= " ORDER BY ic.created_at DESC";
$claims = $conn->query($sql);

// Counts
$countAll = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim")->fetch_assoc()['c'];
$countPending = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Pending'")->fetch_assoc()['c'];
$countVerified = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Verified'")->fetch_assoc()['c'];
$countPaid = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Paid'")->fetch_assoc()['c'];
$countRejected = $conn->query("SELECT COUNT(*) as c FROM tbl_insurance_claim WHERE status='Rejected'")->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 26px; font-weight: 800; }
        .header-stats { display: flex; gap: 12px; }
        .header-stat { background: white; border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .header-stat span { display: block; font-size: 20px; font-weight: 800; }

        .toolbar { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-btn {
            padding: 8px 16px; border-radius: 20px; border: 1px solid #E0E5E0;
            background: white; font-size: 12px; font-weight: 600;
            text-decoration: none; color: #555; transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active { background: #2E7D32; color: white; border-color: #2E7D32; }
        .filter-count { background: rgba(255,255,255,0.25); border-radius: 10px; padding: 1px 7px; margin-left: 4px; font-size: 11px; }

        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; font-size: 11px; color: #999; text-transform: uppercase; border-bottom: 2px solid #eee; }
        .table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
        .table tbody tr { cursor: pointer; transition: background 0.15s; }
        .table tbody tr:hover { background: #f0f7f0; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #FFF3E0; color: #E65100; }
        .status-verified { background: #E3F2FD; color: #1565C0; }
        .status-paid { background: #F3E5F5; color: #6A1B9A; }
        .status-rejected { background: #FFEBEE; color: #C62828; }

        select.status-select { padding: 5px 8px; border-radius: 8px; border: 1px solid #ddd; font-size: 12px; font-family: inherit; }
        .btn-update { padding: 5px 12px; border-radius: 8px; border: none; background: #2E7D32; color: white; font-size: 12px; cursor: pointer; font-weight: 600; }
        .btn-update:hover { background: #1B5E20; }
        .msg { background: #E8F5E9; color: #2E7D32; padding: 10px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; display: flex; align-items: center; gap: 6px; }

        /* ── Modal ─────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 200;
            justify-content: center; align-items: flex-start; padding: 40px 20px; overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: white; border-radius: 20px; width: 100%; max-width: 700px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2); animation: modalIn 0.3s ease-out; margin: auto;
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 24px 28px; border-bottom: 1px solid #eee; }
        .modal-header h2 { font-size: 20px; font-weight: 800; }
        .modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: #f0f0f0; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { background: #ddd; }
        .modal-body { padding: 24px 28px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .info-item { background: #f9f9f9; border-radius: 10px; padding: 12px 16px; }
        .info-label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .info-value { font-size: 14px; font-weight: 600; color: #333; }

        .section-title { font-size: 14px; font-weight: 700; color: #333; margin: 20px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #eee; }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 4px; bottom: 4px; width: 2px; background: #e0e0e0; }
        .timeline-item { position: relative; margin-bottom: 16px; }
        .timeline-dot {
            position: absolute; left: -24px; top: 2px; width: 16px; height: 16px;
            border-radius: 50%; background: #ccc; border: 3px solid white;
        }
        .timeline-dot.active { background: #2E7D32; }
        .timeline-dot.rejected { background: #C62828; }
        .timeline-label { font-size: 13px; font-weight: 600; color: #333; }
        .timeline-date { font-size: 11px; color: #999; margin-top: 2px; }

        .image-row { display: flex; gap: 8px; margin-top: 10px; }
        .image-row img { width: 80px; height: 60px; border-radius: 8px; object-fit: cover; cursor: pointer; }
        .image-row img:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>🛡️ Insurance Claims</h1>
            <div class="header-stats">
                <div class="header-stat" style="color:#E65100"><span><?= $countPending ?></span>Pending</div>
                <div class="header-stat" style="color:#1565C0"><span><?= $countVerified ?></span>Verified</div>
                <div class="header-stat" style="color:#6A1B9A"><span><?= $countPaid ?></span>Paid</div>
                <div class="header-stat" style="color:#C62828"><span><?= $countRejected ?></span>Rejected</div>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="msg"><i class="ri-check-line"></i> Claim status updated successfully.</div>
        <?php endif; ?>

        <div class="toolbar">
            <a href="claims.php" class="filter-btn <?= !$filter ? 'active' : '' ?>">All <span class="filter-count"><?= $countAll ?></span></a>
            <a href="claims.php?status=Pending" class="filter-btn <?= $filter === 'Pending' ? 'active' : '' ?>">Pending</a>
            <a href="claims.php?status=Verified" class="filter-btn <?= $filter === 'Verified' ? 'active' : '' ?>">Verified</a>
            <a href="claims.php?status=Paid" class="filter-btn <?= $filter === 'Paid' ? 'active' : '' ?>">Paid</a>
            <a href="claims.php?status=Rejected" class="filter-btn <?= $filter === 'Rejected' ? 'active' : '' ?>">Rejected</a>
        </div>

        <div class="card">
            <table class="table">
                <thead><tr><th>Claim</th><th>Farmer</th><th>Report</th><th>Disease / Damage</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php while ($c = $claims->fetch_assoc()):
                        $claimData = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr onclick='openClaimModal(<?= $claimData ?>)'>
                        <td><strong>#<?= $c['claim_id'] ?></strong></td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($c['fname'] . ' ' . $c['lname']) ?></div>
                            <div style="font-size:11px; color:#999"><?= htmlspecialchars($c['contact_number']) ?><?= $c['barangay'] ? ' • ' . htmlspecialchars($c['barangay']) : '' ?></div>
                        </td>
                        <td>
                            <div>#<?= $c['report_id'] ?></div>
                            <div style="font-size:11px; color:#999"><?= htmlspecialchars($c['field_location'] ?: '—') ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($c['damage_type']) ?></div>
                            <?php if ($c['ai_disease']): ?>
                                <div style="font-size:11px; color:#1565C0">🤖 AI: <?= htmlspecialchars($c['ai_disease']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-badge status-<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span></td>
                        <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="POST" style="display:flex; gap:4px; align-items:center">
                                <input type="hidden" name="claim_id" value="<?= $c['claim_id'] ?>">
                                <select name="new_status" class="status-select">
                                    <option value="Pending" <?= $c['status']==='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Verified" <?= $c['status']==='Verified'?'selected':'' ?>>Verified</option>
                                    <option value="Paid" <?= $c['status']==='Paid'?'selected':'' ?>>Paid</option>
                                    <option value="Rejected" <?= $c['status']==='Rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($countAll == 0): ?>
                    <tr><td colspan="7" style="text-align:center; color:#999; padding:40px">No claims filed yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Claim Detail Modal ────────────────────── -->
    <div class="modal-overlay" id="claimModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="claimModalTitle">Claim #—</h2>
                <button class="modal-close" onclick="closeClaimModal()"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body" id="claimModalBody"></div>
        </div>
    </div>

    <script>
    function closeClaimModal() {
        document.getElementById('claimModal').classList.remove('active');
    }

    function openClaimModal(c) {
        document.getElementById('claimModal').classList.add('active');
        document.getElementById('claimModalTitle').textContent = 'Claim #' + c.claim_id;

        const images = c.images ? c.images.split(',') : [];
        const isActive = (s) => ['Verified','Paid'].includes(c.status) || c.status === s;
        const isPaid = c.status === 'Paid';
        const isRejected = c.status === 'Rejected';

        let html = `
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Farmer</div><div class="info-value">${c.fname} ${c.lname}</div></div>
                <div class="info-item"><div class="info-label">Contact</div><div class="info-value">${c.contact_number || '—'}</div></div>
                <div class="info-item"><div class="info-label">Barangay</div><div class="info-value">${c.barangay || '—'}</div></div>
                <div class="info-item"><div class="info-label">Municipality</div><div class="info-value">${c.municipality || '—'}</div></div>
                <div class="info-item"><div class="info-label">RSBSA Number</div><div class="info-value">${c.rsbsa_num || '—'}</div></div>
                <div class="info-item"><div class="info-label">Policy Number</div><div class="info-value">${c.policy_num || '—'}</div></div>
                <div class="info-item"><div class="info-label">Damage Type</div><div class="info-value">${c.damage_type}</div></div>
                <div class="info-item"><div class="info-label">Claim Status</div><div class="info-value"><span class="status-badge status-${c.status.toLowerCase()}">${c.status}</span></div></div>
            </div>

            <div class="section-title">📋 Linked Report #${c.report_id}</div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Field Location</div><div class="info-value">${c.field_location || '—'}</div></div>
                <div class="info-item"><div class="info-label">Report Status</div><div class="info-value"><span class="status-badge status-${c.report_status.toLowerCase()}">${c.report_status}</span></div></div>
                <div class="info-item" style="grid-column:span 2"><div class="info-label">Description</div><div class="info-value">${c.report_desc || '—'}</div></div>
            </div>
        `;

        if (images.length > 0) {
            html += `<div class="image-row">${images.map(img => `<img src="../${img}" onclick="window.open('../${img}','_blank')" alt="Report">`).join('')}</div>`;
        }

        if (c.ai_disease) {
            html += `<div style="margin-top:16px; padding:10px 14px; background:#E3F2FD; border-radius:10px; font-size:13px; color:#1565C0">🤖 AI Detected Disease: <strong>${c.ai_disease}</strong></div>`;
        }

        html += `
            <div class="section-title">📅 Claim Timeline</div>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot active"></div>
                    <div class="timeline-label">Claim Filed</div>
                    <div class="timeline-date">${new Date(c.created_at).toLocaleString()}</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot ${isActive('Verified') ? 'active' : ''}"></div>
                    <div class="timeline-label">Under Review</div>
                    <div class="timeline-date">${isActive('Verified') ? 'Reviewed by admin' : 'Pending review'}</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot ${isPaid ? 'active' : ''} ${isRejected ? 'rejected' : ''}"></div>
                    <div class="timeline-label">${isRejected ? 'Rejected' : 'Payment'}</div>
                    <div class="timeline-date">${isPaid ? 'Payment completed' : isRejected ? 'Claim was rejected' : 'Awaiting payment'}</div>
                </div>
            </div>
        `;

        document.getElementById('claimModalBody').innerHTML = html;
    }

    document.getElementById('claimModal').addEventListener('click', function(e) {
        if (e.target === this) closeClaimModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeClaimModal();
    });
    </script>
</body>
</html>
