<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();
$diseases = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM tbl_ai_diagnosis ad WHERE ad.disease_id = d.disease_id) as diag_count, (SELECT COUNT(*) FROM tbl_treatment t WHERE t.disease_id = d.disease_id) as treatment_count FROM tbl_disease d ORDER BY d.disease_name");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diseases — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 26px; font-weight: 800; }
        .diseases-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .disease-card {
            background: white; border-radius: 16px; padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s;
        }
        .disease-card:hover { transform: translateY(-3px); }
        .disease-name { font-size: 18px; font-weight: 700; color: #1A1A2E; margin-bottom: 4px; }
        .disease-sci { font-size: 12px; color: #999; font-style: italic; margin-bottom: 12px; }
        .disease-symptoms { font-size: 13px; color: #555; line-height: 1.5; margin-bottom: 16px; }
        .disease-stats { display: flex; gap: 12px; }
        .disease-stat {
            display: flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 10px; background: #f5f5f5;
            font-size: 12px; font-weight: 600; color: #666;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header"><h1>🦠 Disease & Treatment Management</h1></div>

        <div class="diseases-grid">
            <?php while ($d = $diseases->fetch_assoc()): ?>
            <div class="disease-card">
                <div class="disease-name"><?= htmlspecialchars($d['disease_name']) ?></div>
                <div class="disease-sci"><?= htmlspecialchars($d['scientific_name'] ?: 'N/A') ?></div>
                <div class="disease-symptoms"><?= htmlspecialchars(substr($d['common_symptoms'], 0, 150)) ?>...</div>
                <div class="disease-stats">
                    <div class="disease-stat">🤖 <?= $d['diag_count'] ?> AI diagnoses</div>
                    <div class="disease-stat">💊 <?= $d['treatment_count'] ?> treatments</div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
