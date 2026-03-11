<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['new_status'])) {
    $stmt = $conn->prepare("UPDATE tbl_disease_report SET status = ? WHERE report_id = ?");
    $stmt->bind_param("si", $_POST['new_status'], $_POST['report_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: reports.php?msg=updated');
    exit;
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT r.*, u.fname, u.lname, u.contact_number,
               COALESCE(a.barangay,'') as barangay, COALESCE(a.municipality,'') as municipality,
               (SELECT COUNT(*) FROM tbl_disease_image di WHERE di.report_id = r.report_id) as img_count,
               (SELECT COUNT(*) FROM tbl_ai_diagnosis ad WHERE ad.report_id = r.report_id) as diag_count,
               (SELECT COUNT(*) FROM tbl_verification v WHERE v.report_id = r.report_id) as verify_count
        FROM tbl_disease_report r
        JOIN tbl_user u ON r.user_id = u.user_id
        LEFT JOIN tbl_address a ON u.user_id = a.user_id";
$sql .= " WHERE 1=1";
if ($filter) $sql .= " AND r.status = '" . $conn->real_escape_string($filter) . "'";

// Advanced filters
$timeRange = $_GET['range'] ?? '';
if ($timeRange === 'today') $sql .= " AND DATE(r.reported_at) = CURDATE()";
elseif ($timeRange === 'week') $sql .= " AND YEARWEEK(r.reported_at, 1) = YEARWEEK(CURDATE(), 1)";
elseif ($timeRange === 'month') $sql .= " AND MONTH(r.reported_at) = MONTH(CURDATE()) AND YEAR(r.reported_at) = YEAR(CURDATE())";
elseif ($timeRange === 'year') $sql .= " AND YEAR(r.reported_at) = YEAR(CURDATE())";

$diseaseFilter = $_GET['disease_id'] ?? '';
if ($diseaseFilter) {
    $sql .= " AND EXISTS (SELECT 1 FROM tbl_verification v WHERE v.report_id = r.report_id AND v.disease_id = " . intval($diseaseFilter) . ")";
}

$sql .= " ORDER BY r.reported_at DESC";
$reports = $conn->query($sql);

// Counts for header
$countAll = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report")->fetch_assoc()['c'];
$countPending = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report WHERE status='Pending'")->fetch_assoc()['c'];
$countVerified = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report WHERE status='Verified'")->fetch_assoc()['c'];
$countTreated = $conn->query("SELECT COUNT(*) as c FROM tbl_disease_report WHERE status='Treated'")->fetch_assoc()['c'];

// Fetch all diseases for the filter
$diseaseList = $conn->query("SELECT disease_id, disease_name FROM tbl_disease ORDER BY disease_name ASC");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 26px; font-weight: 800; }
        .header-stats { display: flex; gap: 12px; }
        .header-stat {
            background: white; border-radius: 10px; padding: 8px 16px;
            font-size: 13px; font-weight: 600; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .header-stat span { display: block; font-size: 20px; font-weight: 800; }

        .toolbar { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter-btn {
            padding: 8px 16px; border-radius: 20px; border: 1px solid #E0E5E0;
            background: white; font-size: 12px; font-weight: 600;
            text-decoration: none; color: #555; transition: all 0.2s; cursor: pointer;
        }
        .filter-btn:hover, .filter-btn.active { background: #2E7D32; color: white; border-color: #2E7D32; }
        .filter-count { background: rgba(255,255,255,0.25); border-radius: 10px; padding: 1px 7px; margin-left: 4px; font-size: 11px; }

        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; font-size: 11px; color: #999; text-transform: uppercase; border-bottom: 2px solid #eee; }
        .table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
        .table tr { cursor: pointer; transition: background 0.15s; }
        .table tbody tr:hover { background: #f0f7f0; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #FFF3E0; color: #E65100; }
        .status-verified { background: #E3F2FD; color: #1565C0; }
        .status-treated { background: #E8F5E9; color: #2E7D32; }

        .info-pill { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; background: #f0f0f0; color: #666; margin-right: 4px; }
        .info-pill i { font-size: 12px; }

        select.status-select { padding: 5px 8px; border-radius: 8px; border: 1px solid #ddd; font-size: 12px; font-family: inherit; cursor: pointer; }
        .btn-update {
            padding: 5px 12px; border-radius: 8px; border: none;
            background: #2E7D32; color: white; font-size: 12px; cursor: pointer; font-weight: 600; transition: background 0.2s;
        }
        .btn-update:hover { background: #1B5E20; }
        .msg { background: #E8F5E9; color: #2E7D32; padding: 10px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; display: flex; align-items: center; gap: 6px; }

        /* ── Modal ────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 200;
            justify-content: center; align-items: flex-start; padding: 40px 20px;
            overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: white; border-radius: 20px; width: 100%; max-width: 800px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2); animation: modalIn 0.3s ease-out;
            margin: auto;
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(20px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 28px; border-bottom: 1px solid #eee;
        }
        .modal-header h2 { font-size: 20px; font-weight: 800; }
        .modal-close {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: #f0f0f0; cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center; transition: background 0.2s;
        }
        .modal-close:hover { background: #ddd; }
        .modal-body { padding: 24px 28px; }

        /* Tabs */
        .modal-tabs { display: flex; gap: 0; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        .modal-tab {
            padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer;
            color: #999; border-bottom: 2px solid transparent; transition: all 0.2s;
        }
        .modal-tab:hover { color: #555; }
        .modal-tab.active { color: #2E7D32; border-bottom-color: #2E7D32; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .info-item { background: #f9f9f9; border-radius: 10px; padding: 12px 16px; }
        .info-label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .info-value { font-size: 14px; font-weight: 600; color: #333; }

        /* Image gallery */
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
        .gallery-img {
            border-radius: 12px; overflow: hidden; aspect-ratio: 4/3; cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s;
        }
        .gallery-img:hover { transform: scale(1.03); }
        .gallery-img img { width: 100%; height: 100%; object-fit: cover; }

        /* Detail cards */
        .detail-card {
            background: #f9faf9; border-radius: 12px; padding: 16px;
            margin-bottom: 12px; border-left: 3px solid #4CAF50;
        }
        .detail-card.ai { border-left-color: #1E88E5; }
        .detail-card.treatment { border-left-color: #FB8C00; }
        .detail-card-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 6px; }
        .detail-card-meta { font-size: 12px; color: #888; }
        .detail-card-body { font-size: 13px; color: #555; margin-top: 8px; line-height: 1.5; }

        .confidence-bar { height: 8px; background: #E8F5E9; border-radius: 4px; margin-top: 6px; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 4px; transition: width 0.6s; }

        .empty-state { text-align: center; padding: 30px; color: #999; font-size: 13px; }
        .empty-state i { font-size: 36px; display: block; margin-bottom: 8px; color: #ccc; }

        .loading-spinner { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📋 Disease Reports</h1>
            <div class="header-stats">
                <div class="header-stat" style="color:#E65100"><span><?= $countPending ?></span>Pending</div>
                <div class="header-stat" style="color:#1565C0"><span><?= $countVerified ?></span>Verified</div>
                <div class="header-stat" style="color:#2E7D32"><span><?= $countTreated ?></span>Treated</div>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="msg"><i class="ri-check-line"></i> Report status updated successfully.</div>
        <?php endif; ?>

        <div class="toolbar" style="flex-wrap: wrap;">
            <div style="display:flex; gap:10px">
                <a href="reports.php" class="filter-btn <?= !$filter ? 'active' : '' ?>">All <span class="filter-count"><?= $countAll ?></span></a>
                <a href="reports.php?status=Pending" class="filter-btn <?= $filter === 'Pending' ? 'active' : '' ?>">Pending <span class="filter-count"><?= $countPending ?></span></a>
                <a href="reports.php?status=Verified" class="filter-btn <?= $filter === 'Verified' ? 'active' : '' ?>">Verified <span class="filter-count"><?= $countVerified ?></span></a>
                <a href="reports.php?status=Treated" class="filter-btn <?= $filter === 'Treated' ? 'active' : '' ?>">Treated <span class="filter-count"><?= $countTreated ?></span></a>
            </div>
            
            <div style="flex:1"></div>

            <div style="display:flex; gap:10px; align-items:center">
                <select onchange="location.href='reports.php?range='+this.value + '<?= $filter ? "&status=$filter" : "" ?>'" class="status-select" style="padding: 8px 12px; border-radius: 20px;">
                    <option value="">Any Time</option>
                    <option value="today" <?= ($timeRange === 'today') ? 'selected' : '' ?>>Reported Today</option>
                    <option value="week" <?= ($timeRange === 'week') ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= ($timeRange === 'month') ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= ($timeRange === 'year') ? 'selected' : '' ?>>This Year</option>
                </select>

                <select onchange="location.href='reports.php?disease_id='+this.value + '<?= $filter ? "&status=$filter" : "" ?>'" class="status-select" style="padding: 8px 12px; border-radius: 20px;">
                    <option value="">All Diseases</option>
                    <?php while($d = $diseaseList->fetch_assoc()): ?>
                        <option value="<?= $d['disease_id'] ?>" <?= (isset($diseaseFilter) && $diseaseFilter == $d['disease_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['disease_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="card">
            <table class="table">
                <thead><tr><th>ID</th><th>Farmer</th><th>Location</th><th>Info</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php while ($r = $reports->fetch_assoc()): ?>
                    <tr onclick="openReportModal(<?= $r['report_id'] ?>)" style="cursor:pointer">
                        <td><strong>#<?= $r['report_id'] ?></strong></td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></div>
                            <div style="font-size:11px; color:#999"><?= htmlspecialchars($r['contact_number']) ?><?= $r['barangay'] ? ' • ' . htmlspecialchars($r['barangay']) : '' ?></div>
                        </td>
                        <td><?= htmlspecialchars($r['field_location'] ?: '—') ?></td>
                        <td>
                            <span class="info-pill"><i class="ri-camera-fill"></i> <?= $r['img_count'] ?></span>
                            <span class="info-pill"><i class="ri-robot-fill"></i> <?= $r['diag_count'] ?></span>
                            <span class="info-pill"><i class="ri-checkbox-circle-fill"></i> <?= $r['verify_count'] ?></span>
                        </td>
                        <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
                        <td><?= date('M d, Y', strtotime($r['reported_at'])) ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="POST" style="display:flex; gap:4px; align-items:center">
                                <input type="hidden" name="report_id" value="<?= $r['report_id'] ?>">
                                <select name="new_status" class="status-select">
                                    <option value="Pending" <?= $r['status']==='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Verified" <?= $r['status']==='Verified'?'selected':'' ?>>Verified</option>
                                    <option value="Treated" <?= $r['status']==='Treated'?'selected':'' ?>>Treated</option>
                                </select>
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($countAll == 0): ?>
                    <tr><td colspan="7" style="text-align:center; color:#999; padding:40px">No reports yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Report Detail Modal ────────────────────────── -->
    <div class="modal-overlay" id="reportModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 id="modalTitle">Report #—</h2>
                <button class="modal-close" onclick="closeModal()"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <div class="modal-tab active" onclick="switchTab('overview', event)"><i class="ri-information-line"></i> Overview</div>
                    <div class="modal-tab" onclick="switchTab('images', event)"><i class="ri-image-line"></i> Images</div>
                    <div class="modal-tab" onclick="switchTab('diagnosis', event)"><i class="ri-robot-line"></i> AI Diagnosis</div>
                    <div class="modal-tab" onclick="switchTab('verification', event)"><i class="ri-checkbox-circle-line"></i> Verification</div>
                    <div class="modal-tab" onclick="switchTab('treatments', event)"><i class="ri-medicine-bottle-line"></i> Treatments</div>
                </div>

                <div id="tab-overview" class="tab-pane active"></div>
                <div id="tab-images" class="tab-pane"></div>
                <div id="tab-diagnosis" class="tab-pane"></div>
                <div id="tab-verification" class="tab-pane"></div>
                <div id="tab-treatments" class="tab-pane"></div>
            </div>
        </div>
    </div>

    <script>
    const API_BASE = '../api';

    function closeModal() {
        document.getElementById('reportModal').classList.remove('active');
    }

    function switchTab(name, e) {
        // Reset all tabs
        document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');

        if (e && e.target) {
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            const tabBtn = e.target.closest('.modal-tab');
            if (tabBtn) tabBtn.classList.add('active');
        }

        // Handle Map refresh
        if (name === 'overview' && window.currentReportCoords) {
            setTimeout(() => {
                const mapEl = document.getElementById('reportMap');
                if (mapEl) {
                    initDetailMap(window.currentReportCoords);
                    if (detailMapObj) detailMapObj.invalidateSize();
                }
            }, 100);
        }
    }

    async function openReportModal(reportId) {
        const modal = document.getElementById('reportModal');
        modal.classList.add('active');
        document.getElementById('modalTitle').textContent = 'Report #' + reportId;

        // Loading state
        document.querySelectorAll('.tab-pane').forEach(p => p.innerHTML = '<div class="loading-spinner">Loading...</div>');
        // Set first tab as active
        document.querySelectorAll('.modal-tab').forEach((t, i) => t.classList.toggle('active', i === 0));
        document.querySelectorAll('.tab-pane').forEach((t, i) => t.classList.toggle('active', i === 0));

        try {
            // Fetch everything we need
            const [reportRes, diagRes, verRes, treatRes] = await Promise.all([
                fetch(`${API_BASE}/reports.php?report_id=${reportId}`),
                fetch(`${API_BASE}/diagnosis.php?report_id=${reportId}`),
                fetch(`${API_BASE}/verification.php?report_id=${reportId}`),
                fetch(`${API_BASE}/treatments.php?action=recommendations&report_id=${reportId}`)
            ]);

            const report = await reportRes.json();
            const diagnoses = await diagRes.json();
            const verifications = await verRes.json();
            const treatments = await treatRes.json();

            // 1. Overview Tab
            const bestDiag = diagnoses && diagnoses.length > 0 ? diagnoses[0] : null;
            let overviewHtml = `
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Farmer</div><div class="info-value">${report.fname} ${report.lname}</div></div>
                    <div class="info-item"><div class="info-label">Contact</div><div class="info-value">${report.contact_number || '—'}</div></div>
                    <div class="info-item"><div class="info-label">Field Location</div><div class="info-value">${report.field_location || '—'}</div></div>
                    <div class="info-item"><div class="info-label">Report Method</div><div class="info-value">${report.report_method}</div></div>
                    <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-badge status-${report.status.toLowerCase()}">${report.status}</span></div></div>
                    <div class="info-item"><div class="info-label">Date Reported</div><div class="info-value">${new Date(report.reported_at).toLocaleString()}</div></div>
                    <div class="info-item" style="grid-column:span 2"><div class="info-label">Description</div><div class="info-value">${report.description || '—'}</div></div>
                </div>
            `;

            if (bestDiag) {
                const score = parseFloat(bestDiag.confidence_score) || 0;
                const color = score >= 85 ? '#2E7D32' : score >= 70 ? '#E65100' : '#C62828';
                overviewHtml += `
                    <div class="detail-card ai" style="margin-top: 15px;">
                        <div class="detail-card-title">🤖 AI Prediction Summary</div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px">
                            <div style="font-size:16px; font-weight:700; color:${color}">${bestDiag.disease_name}</div>
                            <div style="font-size:14px; font-weight:700; color:${color}">${score}% Confidence</div>
                        </div>
                        <div class="confidence-bar"><div class="confidence-fill" style="width:${score}%; background:${color}"></div></div>
                        <div style="font-size:11px; color:#999; margin-top:6px">Model: ${bestDiag.model_version || '—'}</div>
                    </div>
                `;
            } else {
                overviewHtml += `
                    <div class="detail-card" style="margin-top: 15px; border-left-color: #eee; background: #fafafa;">
                        <div class="detail-card-title" style="color:#999"><i class="ri-robot-line"></i> AI Prediction</div>
                        <div style="font-size:13px; color:#999; margin-top:4px">No AI diagnosis recorded for this report.</div>
                    </div>
                `;
            }

            overviewHtml += `
                ${report.latitude && report.longitude ? `
                    <div style="margin-top: 20px;">
                        <div id="reportMap" style="height: 250px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #ddd; background: #f0f0f0;"></div>
                        <div style="font-size: 11px; color: #999; text-align: center;">📍 Latitude: ${report.latitude}, Longitude: ${report.longitude}</div>
                    </div>
                ` : '<div class="empty-state" style="padding:20px; border-top:1px solid #eee; margin-top:20px;"><i class="ri-map-pin-2-line"></i>No location data for this report</div>'}
            `;
            document.getElementById('tab-overview').innerHTML = overviewHtml;

            // Trigger map if coordinates exist
            if (report.latitude && report.longitude) {
                window.currentReportCoords = [report.latitude, report.longitude];
                setTimeout(() => initDetailMap(window.currentReportCoords), 100);
            }

            // 2. Images Tab
            const images = report.images || [];
            if (images.length > 0) {
                document.getElementById('tab-images').innerHTML = `
                    <div class="image-gallery">
                        ${images.map(img => `
                            <div class="gallery-img" onclick="window.open('../../${img}', '_blank')">
                                <img src="../../${img}" alt="Report Photo" loading="lazy">
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                document.getElementById('tab-images').innerHTML = '<div class="empty-state"><i class="ri-image-line"></i>No images uploaded</div>';
            }

            // 3. AI Diagnosis Tab
            if (diagnoses && diagnoses.length > 0) {
                document.getElementById('tab-diagnosis').innerHTML = diagnoses.map(d => {
                    const score = parseFloat(d.confidence_score) || 0;
                    const color = score >= 85 ? '#2E7D32' : score >= 70 ? '#E65100' : '#C62828';
                    return `
                        <div class="detail-card ai">
                            <div class="detail-card-title">🤖 ${d.disease_name || 'AI Prediction'}</div>
                            <div class="detail-card-meta">${d.model_version || '—'} • ${d.inference_time_ms ? d.inference_time_ms + 'ms' : '—'}</div>
                            <div style="margin-top:8px; display:flex; align-items:center; gap:10px">
                                <span style="font-size:13px; color:#666">Confidence:</span>
                                <div class="confidence-bar" style="flex:1">
                                    <div class="confidence-fill" style="width:${score}%; background:${color}"></div>
                                </div>
                                <strong style="color:${color}; font-size:14px">${score}%</strong>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                document.getElementById('tab-diagnosis').innerHTML = '<div class="empty-state"><i class="ri-robot-line"></i>No AI diagnosis recorded</div>';
            }

            // 4. Verifications Tab
            if (verifications && verifications.length > 0) {
                let vHtml = verifications.map(v => `
                    <div class="detail-card" style="border-left: 4px solid #1565C0">
                        <div class="detail-card-title">✅ ${v.disease_name}</div>
                        <div class="detail-card-meta">By ${v.fname} ${v.lname} • ${v.verification_method} • ${new Date(v.verified_at).toLocaleString()}</div>
                        ${v.notes ? `<div class="detail-card-body">📝 ${v.notes}</div>` : ''}
                    </div>
                `).join('');
                document.getElementById('tab-verification').innerHTML = vHtml;
            } else {
                document.getElementById('tab-verification').innerHTML = '<div class="empty-state"><i class="ri-checkbox-circle-line"></i>Not yet verified</div>';
            }

            // 5. Treatments Tab
            if (treatments && treatments.length > 0) {
                document.getElementById('tab-treatments').innerHTML = treatments.map(t => `
                    <div class="detail-card treatment">
                        <div class="detail-card-title">💊 ${t.pesticide_name || t.treatment_type || 'Treatment'}</div>
                        <div class="detail-card-meta">
                            Recommended by ${t.fname} ${t.lname} • ${new Date(t.recommended_at).toLocaleString()}
                        </div>
                        <div class="detail-card-body">
                            ${t.dosage ? `<div><strong>Dosage:</strong> ${t.dosage}</div>` : ''}
                            ${t.application_method ? `<div><strong>Method:</strong> ${t.application_method}</div>` : ''}
                            ${t.estimated_cost > 0 ? `<div><strong>Est. Cost:</strong> ₱${parseFloat(t.estimated_cost).toLocaleString()}</div>` : ''}
                            ${t.additional_notes ? `<div style="margin-top:5px; font-style:italic">"${t.additional_notes}"</div>` : ''}
                        </div>
                    </div>
                `).join('');
            } else {
                document.getElementById('tab-treatments').innerHTML = '<div class="empty-state"><i class="ri-medicine-bottle-line"></i>No recommendations yet</div>';
            }

        } catch (err) {
            console.error('Error in openReportModal:', err);
            document.querySelectorAll('.tab-pane.active').forEach(p => {
                p.innerHTML = '<div class="empty-state"><i class="ri-error-warning-line"></i>Failed to load details</div>';
            });
        }
    }

    // Modal behavior
    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Map logic
    let detailMapObj = null;
    function initDetailMap(coords) {
        if (typeof L === 'undefined') return;
        if (!detailMapObj) {
            detailMapObj = L.map('reportMap').setView(coords, 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(detailMapObj);
            L.marker(coords).addTo(detailMapObj);
        } else {
            detailMapObj.setView(coords, 15);
            detailMapObj.eachLayer((layer) => {
                if (layer instanceof L.Marker) detailMapObj.removeLayer(layer);
            });
            L.marker(coords).addTo(detailMapObj);
            detailMapObj.invalidateSize();
        }
    }
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>
