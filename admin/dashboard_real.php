<?php
require_once 'auth_check.php';
require_once '../config/database.php';

class AdminDashboard {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAnalyticsData($period = '7d') {
        $dateCondition = $this->getDateCondition($period);
        $previousDateCondition = $this->getPreviousDateCondition($period);
        
        // Total visitors
        $query = "SELECT COUNT(DISTINCT session_id) as total_visitors FROM analytics_events WHERE created_at >= $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $visitors = $stmt->fetch(PDO::FETCH_ASSOC);

        // Previous period visitors for comparison
        $query = "SELECT COUNT(DISTINCT session_id) as prev_visitors FROM analytics_events WHERE created_at >= $previousDateCondition AND created_at < $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $prevVisitors = $stmt->fetch(PDO::FETCH_ASSOC);

        // Page views
        $query = "SELECT COUNT(*) as total_page_views FROM analytics_page_views WHERE created_at >= $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $pageViews = $stmt->fetch(PDO::FETCH_ASSOC);

        // Previous period page views
        $query = "SELECT COUNT(*) as prev_page_views FROM analytics_page_views WHERE created_at >= $previousDateCondition AND created_at < $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $prevPageViews = $stmt->fetch(PDO::FETCH_ASSOC);

        // Most popular pages
        $query = "SELECT page_url, COUNT(*) as views FROM analytics_page_views WHERE created_at >= $dateCondition GROUP BY page_url ORDER BY views DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $popularPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Wizard analytics
        $query = "SELECT 
                    COUNT(DISTINCT session_id) as total_starts,
                    COUNT(CASE WHEN wizard_step = 7 THEN 1 END) as completions,
                    COUNT(CASE WHEN action_type = 'Buy' THEN 1 END) as buy_orders,
                    COUNT(CASE WHEN action_type = 'Sell' THEN 1 END) as sell_orders
                  FROM analytics_wizard WHERE created_at >= $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $wizardStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Previous period wizard stats
        $query = "SELECT 
                    COUNT(DISTINCT session_id) as prev_starts,
                    COUNT(CASE WHEN wizard_step = 7 THEN 1 END) as prev_completions
                  FROM analytics_wizard WHERE created_at >= $previousDateCondition AND created_at < $dateCondition";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $prevWizardStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Device breakdown
        $query = "SELECT device_type, COUNT(DISTINCT session_id) as count FROM analytics_events WHERE created_at >= $dateCondition GROUP BY device_type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $deviceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Browser breakdown
        $query = "SELECT browser, COUNT(DISTINCT session_id) as count FROM analytics_events WHERE created_at >= $dateCondition GROUP BY browser ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $browserStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent events
        $query = "SELECT event_name, event_type, created_at, page_url FROM analytics_events WHERE created_at >= $dateCondition ORDER BY created_at DESC LIMIT 20";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate percentage changes
        $visitorChange = $this->calculatePercentageChange($visitors['total_visitors'], $prevVisitors['prev_visitors']);
        $pageViewChange = $this->calculatePercentageChange($pageViews['total_page_views'], $prevPageViews['prev_page_views']);
        $completionChange = $this->calculatePercentageChange($wizardStats['completions'], $prevWizardStats['prev_completions']);
        $startChange = $this->calculatePercentageChange($wizardStats['total_starts'], $prevWizardStats['prev_starts']);

        return array(
            'success' => true,
            'data' => array(
                'visitors' => $visitors['total_visitors'],
                'page_views' => $pageViews['total_page_views'],
                'popular_pages' => $popularPages,
                'wizard_stats' => $wizardStats,
                'device_stats' => $deviceStats,
                'browser_stats' => $browserStats,
                'recent_events' => $recentEvents,
                'changes' => array(
                    'visitors' => $visitorChange,
                    'page_views' => $pageViewChange,
                    'completions' => $completionChange,
                    'starts' => $startChange
                )
            )
        );
    }

    private function calculatePercentageChange($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function getDateCondition($period) {
        switch($period) {
            case '1d': return "DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case '7d': return "DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30d': return "DATE_SUB(NOW(), INTERVAL 30 DAY)";
            default: return "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }

    private function getPreviousDateCondition($period) {
        switch($period) {
            case '1d': return "DATE_SUB(NOW(), INTERVAL 2 DAY)";
            case '7d': return "DATE_SUB(NOW(), INTERVAL 14 DAY)";
            case '30d': return "DATE_SUB(NOW(), INTERVAL 60 DAY)";
            default: return "DATE_SUB(NOW(), INTERVAL 14 DAY)";
        }
    }
}

$dashboard = new AdminDashboard();
$period = $_GET['period'] ?? '7d';
$data = $dashboard->getAnalyticsData($period);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analytics Dashboard - jordanmwinukatz P2P Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fefce8', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
                            400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
                            800: '#92400e', 900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --border-subtle: rgba(255,255,255,0.08);
            --text-muted: rgba(100,116,139,1);
            --text-secondary: rgba(148,163,184,1);
            --accent-yellow: #facc15;
            --accent-cyan: #22d3ee;
        }
        body { font-family: 'Inter', -apple-system, sans-serif; }
        .admin-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-width); height: 100vh;
            background: linear-gradient(180deg, #0c1427, #080e1e);
            border-right: 1px solid var(--border-subtle);
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex; align-items: center; gap: 12px;
            text-decoration: none;
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
        @media (max-width: 1024px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .admin-sidebar.open { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .sidebar-toggle { display: flex; }
            .admin-main { grid-column: 1; }
            .admin-topbar { padding: 0 20px 0 72px; }
        }
    </style>
</head>
<body class="bg-[#0b1225] min-h-screen text-white">
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
                <a href="dashboard_real.php" class="sidebar-link active"><i class="fas fa-chart-line"></i> Analytics</a>
                <a href="index.php#submissions-section" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Submissions</a>
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
                    <span class="current">Analytics</span>
                </div>
                <div class="topbar-actions">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-sm" style="color:#fbbf24"></i>
                        <select id="periodSelect" class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-sm font-medium focus:ring-2 focus:ring-[#22d3ee] focus:border-transparent transition-all">
                            <option value="1d" <?= $period === '1d' ? 'selected' : '' ?>>Last 24 Hours</option>
                            <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <button onclick="location.reload()" class="text-white px-4 py-1.5 rounded-lg font-semibold text-sm flex items-center gap-2 hover:opacity-90 transition" style="background: linear-gradient(90deg, #fbbf24, #22d3ee);">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <main class="px-8 lg:px-12 py-8 flex-1">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <!-- Total Visitors -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10 hover:bg-white/[0.07] transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Visitors</p>
                                <p class="text-3xl font-bold text-white mt-2"><?= number_format($data['data']['visitors']) ?></p>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-arrow-<?= $data['data']['changes']['visitors'] >= 0 ? 'up' : 'down' ?> text-<?= $data['data']['changes']['visitors'] >= 0 ? 'green' : 'red' ?>-400 text-xs"></i>
                                    <span class="text-<?= $data['data']['changes']['visitors'] >= 0 ? 'green' : 'red' ?>-400 text-xs font-medium ml-1">
                                        <?= $data['data']['changes']['visitors'] >= 0 ? '+' : '' ?><?= $data['data']['changes']['visitors'] ?>% from last period
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 bg-blue-500/15 rounded-xl">
                                <i class="fas fa-users text-xl text-blue-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Page Views -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10 hover:bg-white/[0.07] transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Page Views</p>
                                <p class="text-3xl font-bold text-white mt-2"><?= number_format($data['data']['page_views']) ?></p>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-arrow-<?= $data['data']['changes']['page_views'] >= 0 ? 'up' : 'down' ?> text-<?= $data['data']['changes']['page_views'] >= 0 ? 'green' : 'red' ?>-400 text-xs"></i>
                                    <span class="text-<?= $data['data']['changes']['page_views'] >= 0 ? 'green' : 'red' ?>-400 text-xs font-medium ml-1">
                                        <?= $data['data']['changes']['page_views'] >= 0 ? '+' : '' ?><?= $data['data']['changes']['page_views'] ?>% from last period
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 bg-green-500/15 rounded-xl">
                                <i class="fas fa-eye text-xl text-green-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Wizard Completions -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10 hover:bg-white/[0.07] transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Wizard Completions</p>
                                <p class="text-3xl font-bold text-white mt-2"><?= number_format($data['data']['wizard_stats']['completions']) ?></p>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-arrow-<?= $data['data']['changes']['completions'] >= 0 ? 'up' : 'down' ?> text-<?= $data['data']['changes']['completions'] >= 0 ? 'primary' : 'red' ?>-400 text-xs"></i>
                                    <span class="text-<?= $data['data']['changes']['completions'] >= 0 ? 'primary' : 'red' ?>-400 text-xs font-medium ml-1">
                                        <?= $data['data']['changes']['completions'] >= 0 ? '+' : '' ?><?= $data['data']['changes']['completions'] ?>% from last period
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 bg-primary-500/15 rounded-xl">
                                <i class="fas fa-check-circle text-xl text-primary-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Completion Rate -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10 hover:bg-white/[0.07] transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Completion Rate</p>
                                <p class="text-3xl font-bold text-white mt-2">
                                    <?= $data['data']['wizard_stats']['total_starts'] > 0 ? 
                                        round(($data['data']['wizard_stats']['completions'] / $data['data']['wizard_stats']['total_starts']) * 100, 1) : 0 ?>%
                                </p>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-arrow-<?= $data['data']['changes']['starts'] >= 0 ? 'up' : 'down' ?> text-<?= $data['data']['changes']['starts'] >= 0 ? 'purple' : 'red' ?>-400 text-xs"></i>
                                    <span class="text-<?= $data['data']['changes']['starts'] >= 0 ? 'purple' : 'red' ?>-400 text-xs font-medium ml-1">
                                        <?= $data['data']['changes']['starts'] >= 0 ? '+' : '' ?><?= $data['data']['changes']['starts'] ?>% from last period
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 bg-purple-500/15 rounded-xl">
                                <i class="fas fa-chart-line text-xl text-purple-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Two-Column Content -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                    <!-- Popular Pages -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10">
                        <div class="flex items-center mb-6">
                            <div class="p-2.5 bg-blue-500/15 rounded-xl mr-3">
                                <i class="fas fa-globe text-lg text-blue-400"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white">Most Popular Pages</h3>
                        </div>
                        <div class="space-y-3">
                            <?php if(empty($data['data']['popular_pages'])): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-chart-bar text-4xl text-gray-500 mb-4"></i>
                                <p class="text-gray-400">No page data available</p>
                            </div>
                            <?php else: ?>
                            <?php foreach($data['data']['popular_pages'] as $index => $page): ?>
                            <div class="bg-white/5 rounded-xl p-4 hover:bg-white/[0.07] transition-all duration-200">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div>
                                            <p class="text-white font-medium truncate max-w-xs"><?= htmlspecialchars($page['page_url']) ?></p>
                                            <p class="text-gray-400 text-sm">Page URL</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-primary-400"><?= number_format($page['views']) ?></p>
                                        <p class="text-gray-400 text-sm">views</p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Device & Browser Stats -->
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10">
                        <div class="flex items-center mb-6">
                            <div class="p-2.5 bg-green-500/15 rounded-xl mr-3">
                                <i class="fas fa-mobile-alt text-lg text-green-400"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white">Device & Browser Stats</h3>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <div class="flex items-center mb-3">
                                    <i class="fas fa-laptop text-primary-400 mr-2"></i>
                                    <h4 class="text-base font-semibold text-white">Devices</h4>
                                </div>
                                <div class="space-y-2">
                                    <?php if(empty($data['data']['device_stats'])): ?>
                                    <p class="text-gray-400 text-center py-4">No device data available</p>
                                    <?php else: ?>
                                    <?php foreach($data['data']['device_stats'] as $device): ?>
                                    <div class="bg-white/5 rounded-xl p-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-9 h-9 bg-blue-500/15 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-<?= $device['device_type'] === 'mobile' ? 'mobile-alt' : ($device['device_type'] === 'tablet' ? 'tablet-alt' : 'desktop') ?> text-blue-400"></i>
                                                </div>
                                                <span class="text-white font-medium"><?= ucfirst($device['device_type']) ?></span>
                                            </div>
                                            <span class="text-lg font-bold text-blue-400"><?= number_format($device['count']) ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center mb-3">
                                    <i class="fas fa-browser text-green-400 mr-2"></i>
                                    <h4 class="text-base font-semibold text-white">Browsers</h4>
                                </div>
                                <div class="space-y-2">
                                    <?php if(empty($data['data']['browser_stats'])): ?>
                                    <p class="text-gray-400 text-center py-4">No browser data available</p>
                                    <?php else: ?>
                                    <?php foreach($data['data']['browser_stats'] as $browser): ?>
                                    <div class="bg-white/5 rounded-xl p-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-9 h-9 bg-green-500/15 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-globe text-green-400"></i>
                                                </div>
                                                <span class="text-white font-medium"><?= $browser['browser'] ?></span>
                                            </div>
                                            <span class="text-lg font-bold text-green-400"><?= number_format($browser['count']) ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white/5 rounded-2xl p-6 border border-white/10">
                    <div class="flex items-center mb-6">
                        <div class="p-2.5 bg-indigo-500/15 rounded-xl mr-3">
                            <i class="fas fa-clock text-lg text-indigo-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Recent Activity</h3>
                    </div>
                    
                    <?php if(empty($data['data']['recent_events'])): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-chart-line text-5xl text-gray-500 mb-4"></i>
                        <h4 class="text-lg font-semibold text-gray-400 mb-2">No Recent Activity</h4>
                        <p class="text-gray-500">User interactions will appear here once tracking begins</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach($data['data']['recent_events'] as $event): ?>
                        <div class="bg-white/5 rounded-xl p-4 hover:bg-white/[0.07] transition-all duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-indigo-500/15 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-<?= $event['event_type'] === 'page_view' ? 'eye' : ($event['event_type'] === 'user_action' ? 'mouse-pointer' : 'chart-bar') ?> text-indigo-400"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-white"><?= htmlspecialchars($event['event_name']) ?></h4>
                                        <p class="text-gray-400 text-xs"><?= htmlspecialchars($event['event_type']) ?> • <?= htmlspecialchars($event['page_url']) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-primary-400 font-semibold text-sm"><?= date('H:i:s', strtotime($event['created_at'])) ?></p>
                                    <p class="text-gray-500 text-xs"><?= date('M j', strtotime($event['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('periodSelect').addEventListener('change', function() {
            const period = this.value;
            window.location.href = `?period=${period}`;
        });
    </script>
</body>
</html>

