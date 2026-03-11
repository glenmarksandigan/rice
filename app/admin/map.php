<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['fname'] ?? 'Admin';
$conn = getConnection();

// Fetch reports with coordinates
$sql = "SELECT r.*, u.fname, u.lname, d.disease_name 
        FROM tbl_disease_report r
        JOIN tbl_user u ON r.user_id = u.user_id
        LEFT JOIN tbl_ai_diagnosis ad ON r.report_id = ad.report_id
        LEFT JOIN tbl_disease d ON ad.disease_id = d.disease_id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL";
$result = $conn->query($sql);
$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitation Map | RiceGuard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; }
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        #map { height: 75vh; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 4px solid white; }
        .map-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .page-header { margin-bottom: 25px; }
        .page-title { font-size: 24px; font-weight: 800; color: #1B5E20; }
        .legend { margin-top: 20px; display: flex; gap: 20px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #4E5D4E; }
        .dot { width: 12px; height: 12px; border-radius: 50%; }
        
        .custom-popup .leaflet-popup-content-wrapper { border-radius: 12px; padding: 5px; }
        .popup-title { font-weight: 800; color: #1B5E20; margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .popup-info { font-size: 12px; color: #666; margin-bottom: 2px; }
        .popup-status { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; margin-top: 8px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title"><i class="ri-map-pin-2-fill me-2"></i>Field Visitation Map</h1>
                <p class="text-secondary mb-0">Visualizing disease hotspots for targeted rice farm visitation.</p>
            </div>
            <div class="text-end">
                <span class="badge bg-success shadow-sm p-2 px-3"><?= count($reports) ?> Reports Mapped</span>
            </div>
        </div>

        <div class="map-card">
            <div id="map"></div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="dot" style="background: #FFC107;"></div> Pending
                </div>
                <div class="legend-item">
                    <div class="dot" style="background: #2E7D32;"></div> Verified
                </div>
                <div class="legend-item">
                    <div class="dot" style="background: #1976D2;"></div> Treated
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize Map (Default centered on Philippines/Bohol context)
        const map = L.map('map').setView([9.8510, 124.1435], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const reports = <?= json_encode($reports) ?>;
        
        reports.forEach(report => {
            const color = report.status === 'Pending' ? '#FFC107' : (report.status === 'Verified' ? '#2E7D32' : '#1976D2');
            
            const marker = L.circleMarker([report.latitude, report.longitude], {
                radius: 8,
                fillColor: color,
                color: "#fff",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);

            const popupContent = `
                <div class="custom-popup">
                    <div class="popup-title">Report #${report.report_id}</div>
                    <div class="popup-info"><b>Farmer:</b> ${report.fname} ${report.lname}</div>
                    <div class="popup-info"><b>Detection:</b> ${report.disease_name || 'In Progress'}</div>
                    <div class="popup-info"><b>Date:</b> ${new Date(report.reported_at).toLocaleDateString()}</div>
                    <div class="popup-status" style="background: ${color}20; color: ${color}; border: 1px solid ${color}">
                        ${report.status}
                    </div>
                    <div class="mt-2 text-center">
                        <a href="reports.php?id=${report.report_id}" class="btn btn-sm btn-outline-success" style="font-size:10px">Details</a>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
        });

        // Auto-fit bounds if reports exist
        if (reports.length > 0) {
            const group = new L.featureGroup(reports.map(r => L.marker([r.latitude, r.longitude])));
            map.fitBounds(group.getBounds().pad(0.1));
        }
    </script>
</body>
</html>
