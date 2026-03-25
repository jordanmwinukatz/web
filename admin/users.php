<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// --- Stats Queries ---
// Total users
$totalUsersStmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
$totalUsers = (int)($totalUsersStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// Verified users
$verifiedUsersStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE email_verified = 1");
$verifiedUsers = (int)($verifiedUsersStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// New users today
$newTodayStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE DATE(created_at) = CURDATE()");
$newUsersToday = (int)($newTodayStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// --- Fetch All Users ---
$search = $_GET['search'] ?? '';

$query = "SELECT id, name, email, profile_picture, email_verified, created_at FROM users";
$params = [];

if ($search !== '') {
    $query .= " WHERE name LIKE ? OR email LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function timeAgo($datetime) {
    if (!$datetime) return '';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) {
        if ($diff->d > 30) return $ago->format('M j, Y');
        return $diff->d . 'd ago';
    }
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Jordan P2P</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-primary: #0f172a; --bg-secondary: #0b1225;
            --bg-surface: rgba(30, 41, 59, 0.6); --bg-surface-hover: rgba(30, 41, 59, 0.85);
            --border-subtle: rgba(255, 255, 255, 0.08); --border-medium: rgba(255, 255, 255, 0.12);
            --accent-emerald: #10b981; --accent-emerald-dim: rgba(16, 185, 129, 0.15);
            --accent-blue: #2563eb; --accent-blue-dim: rgba(37, 99, 235, 0.15);
            --accent-amber: #f59e0b; --accent-amber-dim: rgba(245, 158, 11, 0.15);
            --accent-cyan: #22d3ee; --accent-cyan-dim: rgba(34, 211, 238, 0.12);
            --accent-yellow: #facc15;
            --text-primary: #f1f5f9; --text-secondary: rgba(148, 163, 184, 1); --text-muted: rgba(100, 116, 139, 1);
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Inter', sans-serif; background: var(--bg-secondary);
            color: var(--text-primary); min-height: 100vh;
        }

        .admin-layout { display: grid; grid-template-columns: var(--sidebar-width) 1fr; min-height: 100vh; }
        .admin-sidebar {
            position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh;
            background: linear-gradient(180deg, #0c1427, #080e1e); border-right: 1px solid var(--border-subtle);
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand {
            padding: 24px 20px 20px; border-bottom: 1px solid var(--border-subtle);
            display: flex; align-items: center; gap: 12px; text-decoration: none;
        }
        .sidebar-brand img { height: 30px; width: auto; }
        .sidebar-brand-text {
            font-size: 16px; font-weight: 700;
            background: linear-gradient(135deg, #facc15, #fbbf24, #22d3ee);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .sidebar-brand-sub { font-size: 11px; color: var(--text-muted); }
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-section-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); padding: 16px 12px 8px; }
        .sidebar-link {
            display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; color: var(--text-secondary); text-decoration: none; position: relative;
        }
        .sidebar-link:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }
        .sidebar-link.active { background: rgba(250, 204, 21, 0.08); color: #fde68a; font-weight: 600; }
        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 6px; bottom: 6px; width: 3px;
            border-radius: 0 3px 3px 0; background: linear-gradient(180deg, var(--accent-yellow), var(--accent-cyan));
        }
        .sidebar-link i { width: 20px; text-align: center; font-size: 15px; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid var(--border-subtle); }
        .sidebar-footer-link {
            display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px;
            font-size: 13px; font-weight: 500; color: var(--text-secondary); text-decoration: none;
        }
        .sidebar-footer-link:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }

        .admin-main { grid-column: 2; display: flex; flex-direction: column; }
        .admin-topbar {
            background: rgba(15, 23, 42, 0.85); border-bottom: 1px solid var(--border-subtle);
            backdrop-filter: blur(24px); position: sticky; top: 0; z-index: 50;
            padding: 0 48px; height: 60px; display: flex; align-items: center;
        }
        .topbar-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-muted); }
        .topbar-breadcrumb a { color: var(--text-secondary); text-decoration: none; }
        .topbar-breadcrumb .current { color: var(--text-primary); font-weight: 600; }

        .main-content { padding: 40px 48px; flex: 1; }
        
        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .page-title { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .page-desc { color: var(--text-secondary); font-size: 14px; }
        
        .search-form { display: flex; gap: 8px; }
        .search-input {
            background: rgba(0, 0, 0, 0.3); border: 1px solid var(--border-medium);
            padding: 10px 16px; border-radius: 10px; color: #fff; font-size: 14px;
            outline: none; width: 250px; transition: border-color 0.2s;
        }
        .search-input:focus { border-color: var(--accent-cyan); }
        .search-btn {
            background: var(--bg-surface); border: 1px solid var(--border-medium);
            color: #fff; padding: 0 16px; border-radius: 10px; cursor: pointer; transition: 0.2s;
        }
        .search-btn:hover { background: rgba(255,255,255,0.1); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 36px; }
        .glass-card {
            background: var(--bg-surface); border: 1px solid var(--border-subtle);
            backdrop-filter: blur(20px); border-radius: 16px; padding: 24px;
        }
        .stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; margin-bottom: 8px; }
        .stat-value { font-size: 30px; font-weight: 800; color: #fff; }
        .stat-icon {
            float: right; width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }

        .table-container {
            background: var(--bg-card); border: 1px solid var(--border-subtle);
            border-radius: 16px; overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background: rgba(255, 255, 255, 0.03); padding: 16px 20px;
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            color: var(--text-muted); letter-spacing: 0.05em; border-bottom: 1px solid var(--border-medium);
        }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); font-size: 14px; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
            background: rgba(255,255,255,0.1); display: flex; align-items: center;
            justify-content: center; font-weight: 700; color: var(--text-secondary);
        }
        .user-name { font-weight: 600; color: #fff; }
        .user-email { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; text-transform: uppercase;
        }
        .status-verified { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .status-unverified { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }

        .empty-state { padding: 60px 20px; text-align: center; color: var(--text-muted); }
        .empty-state i { font-size: 40px; margin-bottom: 16px; opacity: 0.5; }
    </style>
    <script src="/js/csrf_interceptor.js?v=2026.03.25.1"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a href="../index.html" class="sidebar-brand">
                <img src="../logo.png" alt="Logo" onerror="this.style.display='none'">
                <div>
                    <div class="sidebar-brand-text">jordanmwinukatz P2P</div>
                    <div class="sidebar-brand-sub">Admin Panel</div>
                </div>
            </a>
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Main</div>
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="dashboard_real.php" class="sidebar-link">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
                <a href="submissions_dashboard.php" class="sidebar-link">
                    <i class="fas fa-clipboard-list"></i> Submissions
                </a>
                <a href="completed_orders.php" class="sidebar-link">
                    <i class="fas fa-clipboard-check"></i> Completed Orders
                </a>
                <div class="sidebar-section-label">Management</div>
                <a href="users.php" class="sidebar-link active">
                    <i class="fas fa-user-cog"></i> Users
                </a>
                <a href="index.php#settings-section" class="sidebar-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../index.html" class="sidebar-footer-link">
                    <i class="fas fa-arrow-left"></i> Back to Site
                </a>
                <a href="logout.php" class="sidebar-footer-link" style="color:#fca5a5;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="sep">/</span>
                    <span class="current">Users</span>
                </div>
            </div>

            <main class="main-content">
                <div class="header-actions">
                    <div>
                        <h1 class="page-title">User Management</h1>
                        <p class="page-desc">View and manage registered customers.</p>
                    </div>
                    <form class="search-form" method="GET" action="users.php">
                        <input type="text" name="search" class="search-input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="users.php" class="search-btn" style="display:flex;align-items:center;text-decoration:none;"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="stats-grid">
                    <div class="glass-card">
                        <div class="stat-icon" style="background: var(--accent-blue-dim); color: #60a5fa;"><i class="fas fa-users"></i></div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value" style="color: #60a5fa;"><?php echo number_format($totalUsers); ?></div>
                    </div>
                    <div class="glass-card">
                        <div class="stat-icon" style="background: var(--accent-emerald-dim); color: #34d399;"><i class="fas fa-user-check"></i></div>
                        <div class="stat-label">Verified Emails</div>
                        <div class="stat-value" style="color: #34d399;"><?php echo number_format($verifiedUsers); ?></div>
                    </div>
                    <div class="glass-card">
                        <div class="stat-icon" style="background: var(--accent-amber-dim); color: #fbbf24;"><i class="fas fa-user-plus"></i></div>
                        <div class="stat-label">New Users Today</div>
                        <div class="stat-value" style="color: #fbbf24;"><?php echo number_format($newUsersToday); ?></div>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h3>No users found</h3>
                            <p><?php echo $search !== '' ? 'Try adjusting your search query.' : 'No customers have registered yet.'; ?></p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <!-- <th>Actions</th> placeholder for future actions -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <?php if (!empty($user['profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" class="user-avatar" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr(htmlspecialchars($user['name'] ?? 'U'), 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['email_verified'] == 1): ?>
                                                <span class="status-badge status-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="status-badge status-unverified"><i class="fas fa-envelope-open"></i> Unverified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 13px;">
                                            <?php echo timeAgo($user['created_at']); ?>
                                        </td>
                                        <!--
                                        <td>
                                            <button style="background:rgba(255,255,255,0.1);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Edit</button>
                                        </td>
                                        -->
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
