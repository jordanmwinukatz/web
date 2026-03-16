<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Filter
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "us.submission_status = ?";
    $params[] = $statusFilter;
}
if (!empty($search)) {
    $where[] = "(us.order_number LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.name')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.email')) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// Counts
$countsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN submission_status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN submission_status = 'reviewed' THEN 1 ELSE 0 END) AS reviewed,
        SUM(CASE WHEN submission_status = 'completed' THEN 1 ELSE 0 END) AS completed
    FROM user_submissions
");
$counts = $countsStmt->fetch(PDO::FETCH_ASSOC);

// Submissions list
$sql = "SELECT us.id, us.order_number, us.submission_type, us.submission_status,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.amount')) AS amount,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.order_type')) AS order_type,
               JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.name')) AS user_name,
               JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.email')) AS user_email,
               us.created_at, us.updated_at, us.admin_viewed
        FROM user_submissions us
        $whereClause
        ORDER BY us.created_at DESC
        LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function timeAgoSub($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Submissions - jordanmwinukatz P2P Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --border-subtle: rgba(255,255,255,0.08);
            --text-muted: rgba(100,116,139,1);
            --text-secondary: rgba(148,163,184,1);
            --accent-yellow: #facc15;
            --accent-cyan: #22d3ee;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0b1225; color: #f1f5f9; min-height: 100vh; }
        .admin-layout { display: grid; grid-template-columns: var(--sidebar-width) 1fr; min-height: 100vh; }
        .admin-sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-width); height: 100vh;
            background: linear-gradient(180deg, #0c1427, #080e1e);
            border-right: 1px solid var(--border-subtle);
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
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
            line-height: 1.2;
        }
        .sidebar-brand-sub { font-size: 11px; color: var(--text-muted); letter-spacing: 0.04em; }
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-section-label {
            font-size: 10px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--text-muted); padding: 16px 12px 8px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; color: var(--text-secondary);
            text-decoration: none; transition: all 0.2s; position: relative;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.05); color: #f1f5f9; }
        .sidebar-link.active { background: rgba(250,204,21,0.08); color: #fde68a; font-weight: 600; }
        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 6px; bottom: 6px; width: 3px;
            border-radius: 0 3px 3px 0;
            background: linear-gradient(180deg, var(--accent-yellow), var(--accent-cyan));
        }
        .sidebar-link i { width: 20px; text-align: center; font-size: 15px; }
        .sidebar-footer {
            padding: 16px 12px; border-top: 1px solid var(--border-subtle);
            display: flex; flex-direction: column; gap: 4px;
        }
        .sidebar-footer-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 10px;
            font-size: 13px; font-weight: 500; color: var(--text-secondary);
            text-decoration: none; transition: all 0.2s;
        }
        .sidebar-footer-link:hover { background: rgba(255,255,255,0.05); color: #f1f5f9; }
        .sidebar-footer-link.logout { color: #fca5a5; }
        .sidebar-footer-link.logout:hover { background: rgba(239,68,68,0.1); color: #fecaca; }
        .admin-main { grid-column: 2; min-height: 100vh; display: flex; flex-direction: column; }
        .admin-topbar {
            background: rgba(15,23,42,0.85); border-bottom: 1px solid var(--border-subtle);
            backdrop-filter: blur(24px); position: sticky; top: 0; z-index: 50;
            padding: 0 48px; height: 60px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-muted); }
        .topbar-breadcrumb a { color: var(--text-secondary); text-decoration: none; }
        .topbar-breadcrumb a:hover { color: #f1f5f9; }
        .topbar-breadcrumb .sep { font-size: 12px; }
        .topbar-breadcrumb .current { color: #f1f5f9; font-weight: 600; }
        .topbar-actions { display: flex; align-items: center; gap: 10px; }
        .sidebar-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 200;
            background: rgba(30,41,59,0.6); border: 1px solid var(--border-subtle);
            border-radius: 10px; width: 44px; height: 44px;
            align-items: center; justify-content: center; cursor: pointer; color: #f1f5f9; font-size: 18px;
        }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 90; }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-tab {
            padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.08); color: var(--text-secondary);
        }
        .filter-tab:hover { background: rgba(255,255,255,0.05); color: #f1f5f9; }
        .filter-tab.active { background: rgba(250,204,21,0.1); border-color: rgba(250,204,21,0.3); color: #fde68a; }
        .filter-tab .count {
            display: inline-block; background: rgba(255,255,255,0.1); border-radius: 6px;
            padding: 1px 7px; margin-left: 6px; font-size: 11px; font-weight: 700;
        }
        .filter-tab.active .count { background: rgba(250,204,21,0.2); }

        /* Search */
        .search-box {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 8px 14px;
        }
        .search-box i { color: var(--text-muted); font-size: 14px; }
        .search-box input {
            background: transparent; border: none; outline: none; color: #f1f5f9;
            font-size: 14px; font-family: inherit; width: 200px;
        }
        .search-box input::placeholder { color: var(--text-muted); }

        /* Submission cards */
        .submission-card {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 20px 24px; transition: all 0.2s;
        }
        .submission-card:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); }
        .submission-card.unread { border-left: 3px solid #fbbf24; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .card-order { font-weight: 700; font-size: 15px; color: #f1f5f9; }
        .card-status {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .status-pending { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .status-reviewed { background: rgba(96,165,250,0.15); color: #60a5fa; }
        .status-completed { background: rgba(52,211,153,0.15); color: #34d399; }
        .card-body { display: flex; align-items: center; justify-content: space-between; }
        .card-user { display: flex; flex-direction: column; gap: 2px; }
        .card-name { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        .card-email { font-size: 12px; color: var(--text-muted); }
        .card-meta { display: flex; align-items: center; gap: 20px; }
        .card-amount { font-size: 18px; font-weight: 700; color: #34d399; }
        .card-time { font-size: 12px; color: var(--text-muted); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; }

        @media (max-width: 1024px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .admin-sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .sidebar-toggle { display: flex; }
            .admin-main { grid-column: 1; }
            .admin-topbar { padding: 0 20px 0 72px; }
            .card-body { flex-direction: column; align-items: flex-start; gap: 8px; }
            .card-meta { gap: 12px; }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('open');"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" onclick="document.querySelector('.admin-sidebar').classList.remove('open');this.classList.remove('open');"></div>

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
                <a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
                <a href="dashboard_real.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Analytics</a>
                <a href="submissions_dashboard.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> Submissions</a>
                <a href="completed_orders.php" class="sidebar-link"><i class="fas fa-clipboard-check"></i> Completed Orders</a>
                <div class="sidebar-section-label">Management</div>
                <a href="#" class="sidebar-link" style="opacity:0.4;cursor:default;"><i class="fas fa-user-cog"></i> Users</a>
                <a href="index.php#settings-section" class="sidebar-link"><i class="fas fa-cog"></i> Settings</a>
            </nav>
            <div class="sidebar-footer">
                <a href="../index.html" class="sidebar-footer-link"><i class="fas fa-arrow-left"></i> Back to Site</a>
                <a href="logout.php" class="sidebar-footer-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="topbar-breadcrumb">
                    <a href="index.php"><i class="fas fa-home" style="font-size:13px;"></i></a>
                    <span class="sep">/</span>
                    <span class="current">Submissions</span>
                </div>
                <div class="topbar-actions">
                    <form method="GET" class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search orders, names..." value="<?= htmlspecialchars($search) ?>">
                        <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
                    </form>
                </div>
            </div>

            <main style="padding: 32px 48px; flex: 1;">
                <!-- Filter Tabs -->
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
                    <div class="filter-tabs">
                        <a href="?status=all<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
                            All <span class="count"><?= $counts['total'] ?></span>
                        </a>
                        <a href="?status=pending<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                            Pending <span class="count"><?= $counts['pending'] ?></span>
                        </a>
                        <a href="?status=reviewed<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'reviewed' ? 'active' : '' ?>">
                            Reviewed <span class="count"><?= $counts['reviewed'] ?></span>
                        </a>
                        <a href="?status=completed<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'completed' ? 'active' : '' ?>">
                            Completed <span class="count"><?= $counts['completed'] ?></span>
                        </a>
                    </div>
                    <div style="font-size:13px; color:var(--text-muted);">
                        Showing <?= count($submissions) ?> submissions
                    </div>
                </div>

                <!-- Submissions List -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if (empty($submissions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3 style="font-size:18px; font-weight:600; margin-bottom:8px;">No submissions found</h3>
                            <p>Try changing the filter or search criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub):
                            $name = htmlspecialchars($sub['user_name'] ?? 'Unknown');
                            $email = htmlspecialchars($sub['user_email'] ?? '');
                            $order = htmlspecialchars($sub['order_number'] ?? '#' . $sub['id']);
                            $status = $sub['submission_status'];
                            $amt = $sub['amount'] ? 'TZS ' . number_format((float)$sub['amount'], 0) : '';
                            $type = $sub['order_type'] ? ucfirst($sub['order_type']) : ucfirst(str_replace('_', ' ', $sub['submission_type']));
                            $time = timeAgoSub($sub['created_at']);
                            $unread = !$sub['admin_viewed'];
                        ?>
                        <div class="submission-card <?= $unread ? 'unread' : '' ?>">
                            <div class="card-header">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <span class="card-order"><?= $order ?></span>
                                    <span style="font-size:12px; color:var(--text-muted);"><?= $type ?></span>
                                </div>
                                <span class="card-status status-<?= $status ?>"><?= $status ?></span>
                            </div>
                            <div class="card-body">
                                <div class="card-user">
                                    <span class="card-name"><?= $name ?></span>
                                    <span class="card-email"><?= $email ?></span>
                                </div>
                                <div class="card-meta">
                                    <?php if ($amt): ?>
                                        <span class="card-amount"><?= $amt ?></span>
                                    <?php endif; ?>
                                    <span class="card-time"><i class="far fa-clock" style="margin-right:4px;"></i><?= $time ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
