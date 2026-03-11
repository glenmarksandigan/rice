<?php
require_once 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
$conn = getConnection();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $stmt = $conn->prepare("DELETE FROM tbl_user WHERE user_id = ?");
    $stmt->bind_param("i", $_POST['delete_user_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: users.php?msg=deleted');
    exit;
}

// Handle add technologist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tech'])) {
    $fname = trim($_POST['tech_fname'] ?? '');
    $lname = trim($_POST['tech_lname'] ?? '');
    $mname = trim($_POST['tech_mname'] ?? '');
    $contact = trim($_POST['tech_contact'] ?? '');
    $password = $_POST['tech_password'] ?? '';

    if ($fname && $lname && $contact && $password) {
        // Check if contact exists
        $check = $conn->prepare("SELECT user_id FROM tbl_user WHERE contact_number = ?");
        $check->bind_param("s", $contact);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            header('Location: users.php?msg=contact_exists');
            exit;
        }
        $check->close();

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $typeId = 2; // Technologist
        $stmt = $conn->prepare(
            "INSERT INTO tbl_user (user_type_id, fname, lname, mname, contact_number, password, has_seen_demo)
             VALUES (?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->bind_param("isssss", $typeId, $fname, $lname, $mname, $contact, $hashed);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: users.php?msg=tech_added');
            exit;
        }
        $stmt->close();
    }
    header('Location: users.php?msg=tech_error');
    exit;
}

$filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT u.*, ut.type_name FROM tbl_user u JOIN tbl_user_type ut ON u.user_type_id = ut.user_type_id WHERE 1=1";
if ($filter) $sql .= " AND u.user_type_id = " . intval($filter);
if ($search) $sql .= " AND (u.fname LIKE '%" . $conn->real_escape_string($search) . "%' OR u.lname LIKE '%" . $conn->real_escape_string($search) . "%' OR u.contact_number LIKE '%" . $conn->real_escape_string($search) . "%')";
$sql .= " ORDER BY u.created_at DESC";
$users = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — RiceGuard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7F5; color: #1A1A2E; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 26px; font-weight: 800; }
        .toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box {
            display: flex; align-items: center; background: white; border: 1px solid #E0E5E0;
            border-radius: 10px; padding: 0 14px; height: 42px; min-width: 260px;
        }
        .search-box input { border: none; outline: none; font-size: 13px; flex: 1; font-family: inherit; }
        .filter-btn {
            padding: 8px 16px; border-radius: 20px; border: 1px solid #E0E5E0;
            background: white; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s;
            text-decoration: none; color: #555;
        }
        .filter-btn:hover, .filter-btn.active { background: #2E7D32; color: white; border-color: #2E7D32; }
        .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #eee; }
        .table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
        .table tr:hover { background: #fafafa; }
        .role-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-farmer { background: #E8F5E9; color: #2E7D32; }
        .role-technologist { background: #E3F2FD; color: #1565C0; }
        .role-admin { background: #FCE4EC; color: #C62828; }
        .btn-delete {
            background: none; border: none; color: #E53935; cursor: pointer; font-size: 18px;
            padding: 4px 8px; border-radius: 6px; transition: background 0.2s;
        }
        .btn-delete:hover { background: #FFEBEE; }
        .msg { padding: 10px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        .msg-success { background: #E8F5E9; color: #2E7D32; }
        .msg-error { background: #FFEBEE; color: #C62828; }

        /* Add Tech Button */
        .btn-add-tech {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, #1565C0, #1E88E5);
            color: white; font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; box-shadow: 0 2px 8px rgba(21,101,192,0.3);
        }
        .btn-add-tech:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(21,101,192,0.4); }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white; border-radius: 20px; padding: 32px; width: 100%; max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal h2 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
        .modal .subtitle { font-size: 13px; color: #999; margin-bottom: 24px; }
        .modal label { display: block; font-size: 12px; font-weight: 600; color: #777; margin-bottom: 4px; margin-top: 14px; }
        .modal input {
            width: 100%; padding: 10px 14px; border: 1px solid #E0E5E0; border-radius: 10px;
            font-size: 13px; font-family: inherit; transition: border-color 0.2s;
        }
        .modal input:focus { outline: none; border-color: #1565C0; }
        .modal-actions { display: flex; gap: 10px; margin-top: 24px; }
        .btn-cancel {
            flex: 1; padding: 12px; border: 1px solid #E0E5E0; border-radius: 10px;
            background: white; font-size: 13px; font-weight: 600; cursor: pointer;
            color: #555; transition: all 0.2s;
        }
        .btn-cancel:hover { background: #f5f5f5; }
        .btn-submit {
            flex: 1; padding: 12px; border: none; border-radius: 10px; font-size: 13px;
            font-weight: 600; cursor: pointer; color: white;
            background: linear-gradient(135deg, #1565C0, #1E88E5);
            transition: all 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        .info-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #E3F2FD; color: #1565C0; padding: 6px 12px;
            border-radius: 8px; font-size: 12px; margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>👥 User Management</h1>
            <button class="btn-add-tech" onclick="document.getElementById('techModal').classList.add('active')">
                ➕ Add Technologist
            </button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'deleted'): ?>
                <div class="msg msg-success">✅ User deleted successfully.</div>
            <?php elseif ($_GET['msg'] === 'tech_added'): ?>
                <div class="msg msg-success">✅ Technologist account created successfully! They can now log in via the mobile app.</div>
            <?php elseif ($_GET['msg'] === 'contact_exists'): ?>
                <div class="msg msg-error">❌ Contact number already registered. Please use a different number.</div>
            <?php elseif ($_GET['msg'] === 'tech_error'): ?>
                <div class="msg msg-error">❌ Failed to create technologist. Please fill in all required fields.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="toolbar">
            <form class="search-box" method="GET">
                <input type="hidden" name="type" value="<?= htmlspecialchars($filter) ?>">
                <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" style="background:none; border:none; cursor:pointer; font-size:18px;">🔍</button>
            </form>
            <a href="users.php" class="filter-btn <?= !$filter ? 'active' : '' ?>">All</a>
            <a href="users.php?type=1" class="filter-btn <?= $filter == '1' ? 'active' : '' ?>">Farmers</a>
            <a href="users.php?type=2" class="filter-btn <?= $filter == '2' ? 'active' : '' ?>">Technologists</a>
            <a href="users.php?type=3" class="filter-btn <?= $filter == '3' ? 'active' : '' ?>">Admins</a>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Contact</th><th>Role</th><th>Joined</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $u['user_id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></strong></td>
                        <td><?= htmlspecialchars($u['contact_number']) ?></td>
                        <td><span class="role-badge role-<?= strtolower($u['type_name']) ?>"><?= $u['type_name'] ?></span></td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="delete_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" class="btn-delete" title="Delete">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Technologist Modal -->
    <div class="modal-overlay" id="techModal">
        <div class="modal">
            <h2>🔬 Add Technologist</h2>
            <p class="subtitle">Create a new expert/technologist account</p>

            <div class="info-badge">
                ℹ️ Technologists can verify farmer disease reports and recommend treatments
            </div>

            <form method="POST">
                <input type="hidden" name="add_tech" value="1">

                <label>First Name *</label>
                <input type="text" name="tech_fname" required placeholder="Enter first name">

                <label>Last Name *</label>
                <input type="text" name="tech_lname" required placeholder="Enter last name">

                <label>Middle Name</label>
                <input type="text" name="tech_mname" placeholder="Enter middle name (optional)">

                <label>Contact Number *</label>
                <input type="text" name="tech_contact" required placeholder="e.g. 09171234567">

                <label>Password *</label>
                <input type="password" name="tech_password" required placeholder="Minimum 6 characters" minlength="6">

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('techModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn-submit">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close modal on overlay click
        document.getElementById('techModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    </script>
</body>
</html>
