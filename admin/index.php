<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Periods
$now = date('Y-m-d H:i:s');

// Optimized: Combine multiple queries into fewer database calls
// Unique visitors (last 30 days) and Page views (last 7 days)
$analyticsStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS visitors,
        (SELECT COUNT(*) FROM analytics_page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS page_views
");
$analytics = $analyticsStmt->fetch(PDO::FETCH_ASSOC);
$totalVisitors = (int)($analytics['visitors'] ?? 0);
$pageViews7d = (int)($analytics['page_views'] ?? 0);

// Optimized: Get all submission counts in one query
$submissionsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN submission_status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN submission_status = 'reviewed' THEN 1 ELSE 0 END) AS reviewed,
        SUM(CASE WHEN submission_status = 'completed' THEN 1 ELSE 0 END) AS completed
    FROM user_submissions
");
$submissions = $submissionsStmt->fetch(PDO::FETCH_ASSOC);
$totalSubmissions = (int)($submissions['total'] ?? 0);
$pendingCount = (int)($submissions['pending'] ?? 0);
$reviewedCount = (int)($submissions['reviewed'] ?? 0);
$completedCount = (int)($submissions['completed'] ?? 0);

// Active orders = pending + reviewed
$activeOrders = $pendingCount + $reviewedCount;

// Success rate = completed / total submissions
$successRate = $totalSubmissions > 0 ? round(($completedCount / $totalSubmissions) * 100, 1) : 0;

// Revenue (sum of numeric 'amount' field in form_data JSON for completed submissions)
$revenueStmt = $pdo->query("SELECT SUM(CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.amount')), '') AS DECIMAL(18,2))) AS total_amount FROM user_submissions WHERE submission_status='completed'");
$revenueTotal = (float)($revenueStmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0);

// Completed today
$completedTodayStmt = $pdo->query("SELECT COUNT(*) AS c FROM user_submissions WHERE submission_status='completed' AND DATE(updated_at) = CURDATE()");
$completedToday = (int)($completedTodayStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

// Average response time (hours) from created to first 'completed' status entry
$avgRespStmt = $pdo->query("SELECT AVG(TIMESTAMPDIFF(SECOND, us.created_at, ssh.created_at))/3600 AS hrs
    FROM user_submissions us
    JOIN submission_status_history ssh ON ssh.submission_id = us.id AND ssh.new_status = 'completed'
");
$avgRespHours = (float)($avgRespStmt->fetch(PDO::FETCH_ASSOC)['hrs'] ?? 0);
$avgRespDisplay = $avgRespHours > 0 ? round($avgRespHours, 1) . 'h' : '0h';

// Conversion rate (completed submissions / unique visitors last 30 days)
$conversionRate = $totalVisitors > 0 ? round(($completedCount / $totalVisitors) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jordan P2P</title>
    
    <!-- Google Fonts - Inter for premium feel -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Resource hints for faster loading -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <style>
        /* ===== Base Reset & Typography ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            /* Main website dark navy palette */
            --bg-primary: #0f172a;
            --bg-secondary: #0b1225;
            --bg-surface: rgba(30, 41, 59, 0.6);
            --bg-surface-hover: rgba(30, 41, 59, 0.85);
            --bg-card: rgba(15, 23, 42, 0.8);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-medium: rgba(255, 255, 255, 0.12);
            
            /* Accent colors matching main site */
            --accent-emerald: #10b981;
            --accent-emerald-dim: rgba(16, 185, 129, 0.15);
            --accent-blue: #2563eb;
            --accent-blue-dim: rgba(37, 99, 235, 0.15);
            --accent-amber: #f59e0b;
            --accent-amber-dim: rgba(245, 158, 11, 0.15);
            --accent-cyan: #22d3ee;
            --accent-cyan-dim: rgba(34, 211, 238, 0.12);
            --accent-red: #ef4444;
            --accent-red-dim: rgba(239, 68, 68, 0.15);
            
            /* Text colors */
            --text-primary: #f1f5f9;
            --text-secondary: rgba(148, 163, 184, 1);
            --text-muted: rgba(100, 116, 139, 1);
        }
        
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ===== Glass Card System ===== */
        .glass-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            background: var(--bg-surface-hover);
            border-color: var(--border-medium);
            transform: translateY(-2px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.4);
        }
        
        .glass-card-static {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
        }
        
        /* ===== Header ===== */
        .admin-header {
            background: rgba(15, 23, 42, 0.9);
            border-bottom: 1px solid var(--border-subtle);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .header-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-emerald-dim), var(--accent-blue-dim));
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .brand-icon i { color: var(--accent-emerald); font-size: 16px; }
        .brand-title {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-emerald), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .brand-subtitle { font-size: 12px; color: var(--text-secondary); }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-pill {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .header-pill .label { color: var(--text-muted); }
        .header-pill .value { color: var(--text-primary); font-weight: 600; }
        
        .icon-btn {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s;
            position: relative;
        }
        .icon-btn:hover {
            background: var(--bg-surface-hover);
            color: var(--text-primary);
            border-color: var(--border-medium);
        }
        
        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--accent-red);
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 99px;
            padding: 1px 6px;
            min-width: 18px;
            text-align: center;
            display: none;
        }
        .notif-badge.visible { display: block; }
        
        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            padding: 8px 16px;
            color: #fca5a5;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
        }
        
        /* ===== Notification Dropdown ===== */
        .notif-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 48px;
            width: 360px;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--border-medium);
            backdrop-filter: blur(24px);
            border-radius: 14px;
            padding: 14px;
            z-index: 60;
            box-shadow: 0 20px 60px -12px rgba(0, 0, 0, 0.6);
        }
        .notif-dropdown.open { display: block; }
        .notif-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .notif-dropdown-header span { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        .notif-dropdown-header a { font-size: 12px; color: var(--accent-cyan); text-decoration: none; }
        .notif-dropdown-header a:hover { text-decoration: underline; }
        .notif-list { max-height: 280px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; }
        .notif-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 8px;
            padding: 10px 12px;
        }
        .notif-item-info { font-size: 13px; }
        .notif-item-user { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .notif-view-btn {
            background: var(--accent-emerald);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .notif-view-btn:hover { background: #059669; }
        .order-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .order-badge.buy { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.3); }
        .order-badge.sell { background: rgba(244, 63, 94, 0.15); color: #fda4af; border: 1px solid rgba(244, 63, 94, 0.3); }
        
        /* ===== Main Layout ===== */
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        
        /* ===== Welcome Section ===== */
        .welcome-section { margin-bottom: 32px; }
        .welcome-title {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-emerald), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }
        .welcome-subtitle { color: var(--text-secondary); font-size: 15px; max-width: 600px; }
        
        /* ===== Stats Grid ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            padding: 24px;
        }
        .stat-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; margin-bottom: 8px; }
        .stat-value { font-size: 30px; font-weight: 800; line-height: 1; }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .stat-footer { margin-top: 16px; display: flex; align-items: center; gap: 6px; font-size: 13px; }
        .stat-footer .change { font-weight: 600; }
        .stat-footer .context { color: var(--text-muted); }
        
        .stat-blue .stat-value { color: #60a5fa; }
        .stat-blue .stat-icon { background: var(--accent-blue-dim); color: #60a5fa; }
        .stat-blue .change { color: #60a5fa; }
        
        .stat-emerald .stat-value { color: #34d399; }
        .stat-emerald .stat-icon { background: var(--accent-emerald-dim); color: #34d399; }
        .stat-emerald .change { color: #34d399; }
        
        .stat-amber .stat-value { color: #fbbf24; }
        .stat-amber .stat-icon { background: var(--accent-amber-dim); color: #fbbf24; }
        .stat-amber .change { color: #fbbf24; }
        
        .stat-cyan .stat-value { color: #22d3ee; }
        .stat-cyan .stat-icon { background: var(--accent-cyan-dim); color: #22d3ee; }
        .stat-cyan .change { color: #22d3ee; }
        
        /* ===== Dashboard Cards Grid ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        .dash-card { padding: 32px; }
        .dash-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        .dash-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .dash-card-title { font-size: 22px; font-weight: 700; }
        .dash-card-desc { font-size: 14px; color: var(--text-secondary); margin-top: 2px; }
        
        .dash-card-stats { display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px; }
        .dash-stat-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }
        .dash-stat-row .label { color: var(--text-secondary); }
        .dash-stat-row .value { font-weight: 600; }
        
        .dash-action-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: white;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dash-action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.3);
        }
        
        .btn-blue {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        .btn-blue:hover { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        
        .btn-emerald {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-emerald:hover { background: linear-gradient(135deg, #34d399, #10b981); }
        
        /* ===== P2P Config Section ===== */
        .config-section { padding: 32px; margin-bottom: 32px; }
        .config-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .config-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .config-label { display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500; }
        .config-input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        .config-input:focus {
            outline: none;
            border-color: var(--accent-emerald);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        .config-input::placeholder { color: var(--text-muted); }
        .config-save-btn {
            margin-top: 16px;
            padding: 10px 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .config-save-btn:hover { background: linear-gradient(135deg, #34d399, #10b981); transform: translateY(-1px); }
        .config-save-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .config-note { font-size: 12px; color: var(--text-muted); margin-top: 12px; }
        
        /* ===== Additional Tools ===== */
        .tools-section { padding: 32px; margin-bottom: 32px; }
        .tools-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .tool-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 24px;
            transition: all 0.3s;
        }
        .tool-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border-medium);
            transform: translateY(-2px);
        }
        .tool-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .tool-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .tool-card h4 { font-size: 16px; font-weight: 600; }
        .tool-card p { font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; }
        .tool-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: white;
            transition: all 0.2s;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
        }
        .tool-btn:hover { transform: translateY(-1px); }
        .tool-btn.disabled {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .tool-btn.disabled:hover { transform: none; }
        
        /* ===== Recent Activity ===== */
        .activity-section { padding: 32px; }
        .activity-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .activity-list { display: flex; flex-direction: column; gap: 10px; }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            transition: background 0.2s;
        }
        .activity-item:hover { background: rgba(255, 255, 255, 0.06); }
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .activity-info { flex: 1; }
        .activity-info .title { font-size: 14px; font-weight: 500; }
        .activity-info .desc { font-size: 13px; color: var(--text-secondary); }
        .activity-time { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        
        /* ===== Sound Prompt ===== */
        .sound-prompt {
            position: fixed;
            top: 80px;
            right: 16px;
            z-index: 60;
            background: rgba(245, 158, 11, 0.9);
            color: #000;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3);
        }
        
        /* ===== Responsive ===== */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .tools-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .config-grid { grid-template-columns: 1fr; }
            .tools-grid { grid-template-columns: 1fr; }
            .header-pill { display: none; }
            .welcome-title { font-size: 26px; }
            .main-content { padding: 20px 16px; }
            .header-inner { padding: 0 16px; }
            .notif-dropdown { width: 300px; right: -60px; }
        }
        @media (max-width: 480px) {
            .header-actions { gap: 8px; }
            .logout-btn span { display: none; }
        }
    </style>
</head>
<body>
    <audio id="notifSound" src="assets/audio/mixkit-urgent-simple-tone-loop-2976.wav" preload="none"></audio>
    
    <!-- Header -->
    <header class="admin-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <div class="brand-title">Admin Dashboard</div>
                    <div class="brand-subtitle">Jordan P2P Management Center</div>
                </div>
            </div>
            <div class="header-actions">
                <div class="header-pill">
                    <span class="label">Last login:</span>
                    <span class="value" id="lastLogin">Just now</span>
                </div>
                <div style="position: relative;">
                    <button id="notifBtn" title="View new submissions" class="icon-btn">
                        <i class="fas fa-bell"></i>
                        <span id="notifBadge" class="notif-badge">0</span>
                    </button>
                    <div id="notifDropdown" class="notif-dropdown">
                        <div class="notif-dropdown-header">
                            <span>New Submissions</span>
                            <a href="submissions_dashboard.php">Open all</a>
                        </div>
                        <div id="notifList" class="notif-list"></div>
                    </div>
                </div>
                <button onclick="refreshData()" class="icon-btn" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2 class="welcome-title">Welcome Back, Admin</h2>
            <p class="welcome-subtitle">Manage your P2P trading platform with powerful analytics and submission tracking tools.</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="glass-card stat-card stat-blue">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value" id="totalUsers"><?php echo number_format($totalVisitors); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-footer">
                    <span class="change">+12%</span>
                    <span class="context">vs last month</span>
                </div>
            </div>

            <div class="glass-card stat-card stat-amber">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-value" id="activeOrders"><?php echo number_format($activeOrders); ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
                <div class="stat-footer">
                    <span class="change">5 new</span>
                    <span class="context">today</span>
                </div>
            </div>

            <div class="glass-card stat-card stat-emerald">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Revenue</div>
                        <div class="stat-value" id="revenue">$0</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="stat-footer">
                    <span class="context">No revenue data</span>
                </div>
            </div>

            <div class="glass-card stat-card stat-cyan">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Success Rate</div>
                        <div class="stat-value" id="successRate"><?php echo $successRate; ?>%</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="stat-footer">
                    <span class="change">+2.1%</span>
                    <span class="context">improvement</span>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Cards -->
        <div class="dashboard-grid">
            <!-- Analytics Dashboard Card -->
            <div class="glass-card dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-icon" style="background: var(--accent-blue-dim); color: #60a5fa;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <div class="dash-card-title">Analytics Dashboard</div>
                        <div class="dash-card-desc">Track website performance and user behavior</div>
                    </div>
                </div>
                <div class="dash-card-stats">
                    <div class="dash-stat-row">
                        <span class="label">Page Views</span>
                        <span class="value"><?php echo number_format($pageViews7d); ?></span>
                    </div>
                    <div class="dash-stat-row">
                        <span class="label">Unique Visitors</span>
                        <span class="value"><?php echo number_format($totalVisitors); ?></span>
                    </div>
                    <div class="dash-stat-row">
                        <span class="label">Conversion Rate</span>
                        <span class="value"><?php echo $conversionRate; ?>%</span>
                    </div>
                </div>
                <button onclick="openAnalytics()" class="dash-action-btn btn-blue">
                    <i class="fas fa-chart-line"></i>
                    <span>Open Analytics Dashboard</span>
                </button>
            </div>

            <!-- Submissions Dashboard Card -->
            <div class="glass-card dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-icon" style="background: var(--accent-emerald-dim); color: #34d399;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <div class="dash-card-title">Submissions Dashboard</div>
                        <div class="dash-card-desc">Manage customer orders and inquiries</div>
                    </div>
                </div>
                <div class="dash-card-stats">
                    <div class="dash-stat-row">
                        <span class="label">Pending Reviews</span>
                        <span class="value" style="color: #fbbf24;"><?php echo number_format($pendingCount); ?></span>
                    </div>
                    <div class="dash-stat-row">
                        <span class="label">Completed Today</span>
                        <span class="value" style="color: #34d399;"><?php echo number_format($completedToday); ?></span>
                    </div>
                    <div class="dash-stat-row">
                        <span class="label">Avg Response Time</span>
                        <span class="value"><?php echo $avgRespDisplay; ?></span>
                    </div>
                </div>
                <button onclick="openSubmissions()" class="dash-action-btn btn-emerald">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Open Submissions Dashboard</span>
                </button>
            </div>
        </div>

        <!-- P2P Config Card -->
        <div class="glass-card-static config-section">
            <h3 class="config-title">P2P USDT Ad Codes</h3>
            <form onsubmit="saveP2PConfig(event)">
                <div class="config-grid">
                    <div>
                        <label class="config-label">Buy ad code</label>
                        <input id="p2pBuyCode" type="text" class="config-input" placeholder="e.g. j15stHvu1u4" />
                    </div>
                    <div>
                        <label class="config-label">Sell ad code</label>
                        <input id="p2pSellCode" type="text" class="config-input" placeholder="e.g. HrOzHzvC5uj" />
                    </div>
                </div>
                <button id="p2pSaveBtn" class="config-save-btn">Save</button>
            </form>
            <p class="config-note">Note: Frontend polls every 30s; server caches for 15s.</p>
        </div>

        <!-- Additional Tools Section -->
        <div class="glass-card-static tools-section">
            <h3 class="tools-title">Additional Tools</h3>
            <div class="tools-grid">
                <!-- User Management -->
                <div class="tool-card">
                    <div class="tool-card-header">
                        <div class="tool-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <h4>User Management</h4>
                    </div>
                    <p>Manage user accounts and permissions</p>
                    <button class="tool-btn disabled">Coming Soon</button>
                </div>

                <!-- Order Management -->
                <div class="tool-card">
                    <div class="tool-card-header">
                        <div class="tool-icon" style="background: rgba(249, 115, 22, 0.15); color: #fb923c;">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h4>Order Management</h4>
                    </div>
                    <p>Track and manage all orders</p>
                    <button class="tool-btn disabled">Coming Soon</button>
                </div>

                <!-- Completed Orders -->
                <div class="tool-card">
                    <div class="tool-card-header">
                        <div class="tool-icon" style="background: var(--accent-emerald-dim); color: #34d399;">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h4>Completed Orders</h4>
                    </div>
                    <p>Review finalized trades with attached proofs</p>
                    <a href="completed_orders.php" class="tool-btn" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-eye"></i>
                        View Archive
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="glass-card-static activity-section">
            <h3 class="activity-title">Recent Activity</h3>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon" style="background: var(--accent-emerald-dim); color: #34d399;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="activity-info">
                        <div class="title">New order completed</div>
                        <div class="desc">Order #ORD-123456 - $150 USDT</div>
                    </div>
                    <span class="activity-time">2 minutes ago</span>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: var(--accent-blue-dim); color: #60a5fa;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="activity-info">
                        <div class="title">New user registered</div>
                        <div class="desc">john.doe@example.com</div>
                    </div>
                    <span class="activity-time">5 minutes ago</span>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: var(--accent-amber-dim); color: #fbbf24;">
                        <i class="fas fa-exclamation"></i>
                    </div>
                    <div class="activity-info">
                        <div class="title">Pending review</div>
                        <div class="desc">Order #ORD-123457 needs attention</div>
                    </div>
                    <span class="activity-time">10 minutes ago</span>
                </div>
            </div>
        </div>
    </main>

    <script defer>
        const notifSound = document.getElementById('notifSound');
        let previousPendingCount = null;
        let notifSoundPrimed = false;
        let soundPromptEl = null;

        function hideSoundPrompt() {
            if (soundPromptEl) {
                soundPromptEl.remove();
                soundPromptEl = null;
            }
        }

        function showSoundPrompt() {
            if (soundPromptEl || notifSoundPrimed) return;
            soundPromptEl = document.createElement('div');
            soundPromptEl.className = 'sound-prompt';
            soundPromptEl.innerHTML = '<i class="fas fa-volume-up"></i><span>Enable notification sound</span>';
            soundPromptEl.addEventListener('click', () => {
                primeNotifSound(true);
            });
            document.body.appendChild(soundPromptEl);
        }

        function primeNotifSound(forceAttempt = false) {
            if (!notifSound || notifSoundPrimed) {
                hideSoundPrompt();
                return;
            }
            if (!forceAttempt && document.visibilityState !== 'visible') {
                return;
            }
            const attempt = notifSound.play();
            if (attempt && typeof attempt.then === 'function') {
                attempt.then(() => {
                    notifSound.pause();
                    notifSound.currentTime = 0;
                    notifSoundPrimed = true;
                    hideSoundPrompt();
                }).catch(() => {
                    notifSound.pause();
                    notifSound.currentTime = 0;
                    showSoundPrompt();
                });
            } else {
                notifSoundPrimed = true;
                hideSoundPrompt();
            }
        }

        function openAnalytics() {
            window.open('dashboard_real.php', '_blank');
        }

        function openSubmissions() {
            window.open('submissions_dashboard.php', '_blank');
        }

        async function updateNotifBadge() {
            try {
                const res = await fetch('../api/submissions.php?action=pending_count');
                const j = await res.json();
                const badge = document.getElementById('notifBadge');
                if (j.success && typeof j.pending === 'number') {
                    if (previousPendingCount !== null && j.pending > previousPendingCount && notifSound) {
                        try {
                            notifSound.currentTime = 0;
                            const playPromise = notifSound.play();
                            if (playPromise && typeof playPromise.then === 'function') {
                                playPromise.then(() => {
                                    notifSoundPrimed = true;
                                    hideSoundPrompt();
                                }).catch(() => {
                                    showSoundPrompt();
                                });
                            } else {
                                notifSoundPrimed = true;
                            }
                        } catch (err) {
                            showSoundPrompt();
                        }
                    }
                    if (j.pending > 0) {
                        badge.textContent = j.pending > 99 ? '99+' : String(j.pending);
                        badge.classList.add('visible');
                    } else {
                        badge.classList.remove('visible');
                    }
                    previousPendingCount = j.pending;
                } else if (previousPendingCount === null) {
                    previousPendingCount = 0;
                }
            } catch (e) {}
        }

        async function loadNotifications() {
            try {
                const res = await fetch('../api/submissions.php?action=notifications_list&limit=10');
                const j = await res.json();
                const list = document.getElementById('notifList');
                list.innerHTML = '';
                if (j.success && Array.isArray(j.notifications) && j.notifications.length) {
                    j.notifications.forEach(n => {
                        let formData = n.form_data;
                        if (formData && typeof formData === 'string') {
                            try {
                                formData = JSON.parse(formData);
                            } catch (_) {
                                formData = {};
                            }
                        }
                        const user = (n.user_info && (n.user_info.name || n.user_info.email)) || 'Visitor';
                        const amount = formData && (formData.amount || formData.usdt || formData.tzs) || '';
                        const orderTypeRaw = formData && (formData.order_type || formData.trade_type || formData.tradeType || formData.action || formData.mode);
                        const orderType = typeof orderTypeRaw === 'string' ? orderTypeRaw.trim().toLowerCase() : '';
                        const isBuy = orderType === 'buy';
                        const isSell = orderType === 'sell';
                        const badge = isBuy || isSell
                            ? `<span class="order-badge ${isBuy ? 'buy' : 'sell'}">${isBuy ? 'Buy' : 'Sell'}</span>`
                            : '';
                        const titleParts = [
                            `#${n.id}`,
                            n.submission_type ? n.submission_type.replace('_',' ') : 'submission'
                        ];
                        const infoLine = [titleParts.join(' '), badge].filter(Boolean).join(' ');
                        const row = document.createElement('div');
                        row.className = 'notif-item';
                        row.innerHTML = `
                            <div>
                                <div class="notif-item-info" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">${infoLine}${amount ? `<span style="color: var(--text-muted); font-size: 11px;">• ${amount}</span>` : ''}</div>
                                <div class="notif-item-user">${user}</div>
                            </div>
                            <button data-id="${n.id}" data-order-type="${orderType}" data-submission-type="${n.submission_type || ''}" class="markRead notif-view-btn">View</button>
                        `;
                        list.appendChild(row);
                    });
                } else {
                    list.innerHTML = '<div style="color: var(--text-muted); font-size: 13px;">No new submissions</div>';
                }

                // wire buttons
                list.querySelectorAll('.markRead').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        const id = e.currentTarget.getAttribute('data-id');
                        const orderTypeAttr = (e.currentTarget.getAttribute('data-order-type') || '').toLowerCase();
                        const submissionTypeAttr = (e.currentTarget.getAttribute('data-submission-type') || '').toLowerCase();
                        const resolvedType = (() => {
                            if (orderTypeAttr === 'buy' || orderTypeAttr === 'sell') {
                                return orderTypeAttr;
                            }
                            if (submissionTypeAttr.includes('buy')) return 'buy';
                            if (submissionTypeAttr.includes('sell')) return 'sell';
                            return '';
                        })();
                        const orderPage = resolvedType === 'buy'
                            ? '../buy order.html'
                            : resolvedType === 'sell'
                                ? '../sell order.html'
                                : 'dashboard_real.php';
                        try {
                            await fetch('../api/submissions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_viewed', submission_id: id }) });
                        } catch {}
                        // refresh badge and list
                        updateNotifBadge();
                        loadNotifications();
                        // hide dropdown and open specific page
                        document.getElementById('notifDropdown').classList.remove('open');
                        try {
                            let targetUrl = orderPage;
                            if (orderPage !== 'dashboard_real.php') {
                                const url = new URL(orderPage, window.location.href);
                                url.searchParams.set('id', id);
                                // cache preview payload for fallback rendering
                                try {
                                    const cacheKey = `submission-preview-${id}`;
                                    localStorage.setItem(cacheKey, JSON.stringify(n));
                                    localStorage.setItem('submission-preview-latest', JSON.stringify({ id, data: n }));
                                } catch (_) {}
                                targetUrl = url.toString();
                            }
                            window.location.href = targetUrl;
                        } catch (err) {
                            console.error('Failed to open order preview', err);
                            window.location.href = 'dashboard_real.php';
                        }
                    });
                });
            } catch (e) {}
        }

        function refreshData() {
            document.getElementById('lastLogin').textContent = new Date().toLocaleTimeString();
            
            // Subtle refresh animation
            const cards = document.querySelectorAll('.glass-card');
            cards.forEach(card => {
                card.style.transform = 'scale(0.98)';
                card.style.opacity = '0.7';
                setTimeout(() => {
                    card.style.transform = '';
                    card.style.opacity = '';
                }, 250);
            });
        }

        // Update last login time
        document.getElementById('lastLogin').textContent = new Date().toLocaleTimeString();

        // Adaptive polling: slower on mobile, faster on desktop
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const NOTIF_POLL_INTERVAL = isMobile ? 10000 : 5000;
        document.addEventListener('click', () => primeNotifSound(true), { passive: true });
        document.addEventListener('touchstart', () => primeNotifSound(true), { passive: true });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                primeNotifSound();
            }
        });

        // Initial notifications and near real-time polling
        updateNotifBadge();
        setInterval(updateNotifBadge, NOTIF_POLL_INTERVAL);

        // Toggle dropdown on bell click
        document.getElementById('notifBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            const dd = document.getElementById('notifDropdown');
            const isOpen = dd.classList.contains('open');
            if (!isOpen) { loadNotifications(); }
            dd.classList.toggle('open');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const btn = document.getElementById('notifBtn');
            const dd = document.getElementById('notifDropdown');
            if (!btn.contains(e.target) && !dd.contains(e.target)) {
                dd.classList.remove('open');
            }
        });

        // P2P config load/save
        async function loadP2PConfig(){
            try {
                const res = await fetch('../api/p2p_config.php');
                const j = await res.json();
                if (j.success) {
                    const d = j.data || {};
                    const buy = document.getElementById('p2pBuyCode');
                    const sell = document.getElementById('p2pSellCode');
                    if (buy) buy.value = d.buyCode || '';
                    if (sell) sell.value = d.sellCode || '';
                }
            } catch(e) { console.error(e); }
        }
        async function saveP2PConfig(ev){
            ev.preventDefault();
            const btn = document.getElementById('p2pSaveBtn');
            if (btn){ btn.disabled = true; btn.textContent = 'Saving...'; }
            try {
                const body = {
                    buyCode: document.getElementById('p2pBuyCode').value.trim(),
                    sellCode: document.getElementById('p2pSellCode').value.trim()
                };
                const res = await fetch('../api/p2p_config.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                const j = await res.json();
                if (!j.success) throw new Error(j.error || 'Failed to save');
                alert('Saved. Frontend will use new links within ~30s.');
            } catch(e) {
                alert('Error: ' + e.message);
            } finally {
                if (btn){ btn.disabled = false; btn.textContent = 'Save'; }
            }
        }

        document.addEventListener('DOMContentLoaded', loadP2PConfig);
    </script>
</body>
</html>
