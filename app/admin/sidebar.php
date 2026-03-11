<!-- Admin Sidebar Component -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
<style>
    .sidebar {
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #1B5E20 0%, #2E7D32 100%);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 100;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s;
    }
    .sidebar-header {
        padding: 28px 24px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .sidebar-logo-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sidebar-logo-icon i { font-size: 22px; color: #81C784; }
    .sidebar-logo-text { color: white; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
    .sidebar-logo-sub { color: rgba(255,255,255,0.5); font-size: 11px; }

    .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
    .nav-section { padding: 0 12px; margin-bottom: 8px; font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        margin-bottom: 2px;
    }
    .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
    .nav-item.active {
        background: rgba(255,255,255,0.15);
        color: white;
        font-weight: 600;
    }
    .nav-item i { font-size: 20px; width: 24px; text-align: center; }

    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .admin-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
    }
    .admin-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .admin-avatar i { font-size: 18px; color: white; }
    .admin-name { color: white; font-size: 13px; font-weight: 600; }
    .admin-role { color: rgba(255,255,255,0.5); font-size: 11px; }

    .main-content {
        margin-left: 260px;
        min-height: 100vh;
        background: #F5F7F5;
        padding: 30px;
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon"><i class="ri-leaf-fill"></i></div>
            <div>
                <div class="sidebar-logo-text">RiceGuard</div>
                <div class="sidebar-logo-sub">Admin Panel</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="ri-dashboard-3-fill"></i> Dashboard
        </a>

        <div class="nav-section" style="margin-top:20px">Management</div>
        <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
            <i class="ri-user-3-fill"></i> Users
        </a>
        <a href="reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
            <i class="ri-file-text-fill"></i> Reports
        </a>
        <a href="verifications.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'verifications.php' ? 'active' : '' ?>">
            <i class="ri-checkbox-circle-fill"></i> Verifications
        </a>
        <a href="diseases.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'diseases.php' ? 'active' : '' ?>">
            <i class="ri-bug-fill"></i> Diseases
        </a>
        <a href="claims.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'claims.php' ? 'active' : '' ?>">
            <i class="ri-shield-check-fill"></i> Insurance Claims
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><i class="ri-admin-fill"></i></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="nav-item" style="color:#FF8A80; margin-top:8px">
            <i class="ri-logout-box-r-fill"></i> Logout
        </a>
    </div>
</div>
