<?php
require_once 'auth_check.php';
require_once '../config/database.php';

// Set timezone to East Africa Time (UTC+3) for accurate time display
date_default_timezone_set('Africa/Dar_es_Salaam');

$db = new Database();
$pdo = $db->getConnection();

// Filter
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$expandId = (int)($_GET['expand'] ?? 0);
$isTrash = ($statusFilter === 'trash');

// If expanding a specific card from notification, show all to ensure it's found
if ($expandId > 0) {
    $statusFilter = 'all';
}

// Build query
$where = [];
$params = [];

// Soft-delete filtering
if ($isTrash) {
    $where[] = "us.deleted_at IS NOT NULL";
} else {
    $where[] = "us.deleted_at IS NULL";
    if ($statusFilter !== 'all') {
        $where[] = "us.submission_status = ?";
        $params[] = $statusFilter;
    }
}
if (!empty($search)) {
    $where[] = "(us.id LIKE ? OR us.order_number LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.name')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(us.user_info, '$.email')) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// Counts (only non-trashed)
$countsStmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS total,
        SUM(CASE WHEN submission_status = 'pending' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN submission_status = 'reviewed' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS reviewed,
        SUM(CASE WHEN submission_status = 'completed' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN submission_status = 'rejected' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed
    FROM user_submissions
");
$counts = $countsStmt->fetch(PDO::FETCH_ASSOC);

// Get max ID for polling
$maxIdStmt = $pdo->query("SELECT MAX(id) FROM user_submissions WHERE deleted_at IS NULL");
$maxId = $maxIdStmt->fetchColumn() ?: 0;

// Submissions list
$sql = "SELECT us.id, us.order_number, us.submission_type, us.submission_status,
               us.form_data,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.amount')) AS amount,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.order_type')) AS order_type,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.currency')) AS currency,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.platform')) AS platform,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.payment_method')) AS payment_method,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.amount_usdt')) AS amount_usdt,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.amount_tzs')) AS amount_tzs,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.platform_uid')) AS platform_uid,
               JSON_UNQUOTE(JSON_EXTRACT(us.form_data, '$.platform_email')) AS platform_email,
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
            border-radius: 10px; padding: 8px 14px; position: relative;
        }
        .search-suggestions {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: rgba(15,23,42,0.95); backdrop-filter: blur(8px);
            border: 1px solid var(--border-subtle); border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000;
            max-height: 300px; overflow-y: auto; display: none; flex-direction: column; text-align: left;
        }
        .search-suggestion-item {
            padding: 10px 14px; font-size: 13px; color: #f1f5f9; cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; flex-direction: column; gap: 2px;
        }
        .search-suggestion-item:last-child { border-bottom: none; }
        .search-suggestion-item:hover { background: rgba(255,255,255,0.05); }
        .search-suggestion-title { font-weight: 600; color: #facc15; }
        .search-suggestion-subtitle { color: var(--text-muted); font-size: 11px; text-transform: capitalize; }
        .search-box i { color: var(--text-muted); font-size: 14px; }
        .search-box input {
            background: transparent; border: none; outline: none; color: #f1f5f9;
            font-size: 14px; font-family: inherit; width: 200px;
        }
        .search-box input::placeholder { color: var(--text-muted); }

        /* Submission cards */
        .submission-card {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; transition: all 0.2s; cursor: pointer;
        }
        .submission-card:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); }
        .submission-card.unread { border-left: 3px solid #fbbf24; }
        .submission-card.expanded { border-color: rgba(250,204,21,0.3); background: rgba(255,255,255,0.06); }
        .card-summary { padding: 20px 24px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .card-order { font-weight: 700; font-size: 15px; color: #f1f5f9; }
        .card-status {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .status-pending { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .status-reviewed { background: rgba(96,165,250,0.15); color: #60a5fa; }
        .status-completed { background: rgba(52,211,153,0.15); color: #34d399; }
        .status-rejected { background: rgba(239,68,68,0.15); color: #f87171; }
        .card-body { display: flex; align-items: center; justify-content: space-between; }
        .card-user { display: flex; flex-direction: column; gap: 2px; }
        .card-name { font-size: 14px; font-weight: 600; color: #e2e8f0; }
        .card-email { font-size: 12px; color: var(--text-muted); }
        .card-meta { display: flex; align-items: center; gap: 20px; }
        .card-amount { font-size: 18px; font-weight: 700; color: #34d399; }
        .card-time { font-size: 12px; color: var(--text-muted); }
        .card-expand-hint { font-size: 11px; color: var(--text-muted); margin-top: 8px; }
        .card-expand-hint i { margin-right: 4px; transition: transform 0.2s; }
        .submission-card.expanded .card-expand-hint i { transform: rotate(180deg); }

        /* Detail panel */
        .card-detail {
            display: none; padding: 0 24px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .submission-card.expanded .card-detail { display: block; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .detail-section { background: rgba(255,255,255,0.03); border-radius: 10px; padding: 16px; border: 1px solid rgba(255,255,255,0.05); }
        .detail-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 10px; }

        /* Action buttons */
        .action-bar { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
        .action-btn {
            padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600;
            border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s; font-family: inherit;
        }
        .action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-review { background: rgba(96,165,250,0.15); color: #60a5fa; }
        .btn-review:hover:not(:disabled) { background: rgba(96,165,250,0.25); }
        .btn-complete { background: rgba(52,211,153,0.15); color: #34d399; }
        .btn-complete:hover:not(:disabled) { background: rgba(52,211,153,0.25); }
        .btn-pending { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .btn-pending:hover:not(:disabled) { background: rgba(251,191,36,0.25); }
        .btn-reject { background: rgba(239,68,68,0.15); color: #f87171; }
        .btn-reject:hover:not(:disabled) { background: rgba(239,68,68,0.25); }
        .btn-delete { background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; margin-left: auto; }
        .btn-delete:hover:not(:disabled) { background: rgba(220,38,38,0.2); border-color: rgba(220,38,38,0.5); color: #fecaca; }
        .btn-restore { background: rgba(52,211,153,0.15); color: #34d399; }
        .btn-restore:hover:not(:disabled) { background: rgba(52,211,153,0.25); }

        /* Bulk select checkbox */
        .card-checkbox { position: relative; z-index: 10; margin-right: 8px; flex-shrink: 0; }
        .card-checkbox input[type=checkbox] {
            width: 18px; height: 18px; cursor: pointer; accent-color: #facc15;
            border-radius: 4px; border: 2px solid rgba(255,255,255,0.2);
        }
        .card-header-left { display: flex; align-items: center; gap: 12px; flex: 1; }

        /* Bulk action bar */
        .bulk-bar {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(100px);
            background: linear-gradient(135deg, #1e293b, #0f172a); border: 1px solid rgba(250,204,21,0.3);
            border-radius: 16px; padding: 12px 24px; display: flex; align-items: center; gap: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6); z-index: 9000;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); min-width: 320px;
        }
        .bulk-bar.visible { transform: translateX(-50%) translateY(0); }
        .bulk-bar .bulk-count { font-size: 14px; font-weight: 700; color: #fde68a; white-space: nowrap; }
        .bulk-bar .bulk-btn {
            padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
            border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s; font-family: inherit; white-space: nowrap;
        }
        .bulk-bar .bulk-btn-trash { background: rgba(239,68,68,0.15); color: #f87171; }
        .bulk-bar .bulk-btn-trash:hover { background: rgba(239,68,68,0.25); }
        .bulk-bar .bulk-btn-restore { background: rgba(52,211,153,0.15); color: #34d399; }
        .bulk-bar .bulk-btn-restore:hover { background: rgba(52,211,153,0.25); }
        .bulk-bar .bulk-btn-permadelete { background: rgba(220,38,38,0.15); color: #fca5a5; }
        .bulk-bar .bulk-btn-permadelete:hover { background: rgba(220,38,38,0.3); }
        .bulk-bar .bulk-btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-secondary); }
        .bulk-bar .bulk-btn-cancel:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
        .select-all-row {
            display: flex; align-items: center; gap: 12px; padding: 10px 16px; margin-bottom: 8px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px; font-size: 13px; color: var(--text-secondary);
        }
        .select-all-row label { cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .status-trashed { background: rgba(100,116,139,0.2); color: #94a3b8; }

        /* Notes */
        .note-input-row { display: flex; gap: 8px; margin-top: 12px; }
        .note-input {
            flex: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 10px 14px; color: #f1f5f9; font-family: inherit;
            font-size: 13px; outline: none; resize: none;
        }
        .note-input::placeholder { color: var(--text-muted); }
        .note-input:focus { border-color: rgba(250,204,21,0.3); }
        .btn-note { background: rgba(250,204,21,0.15); color: #fbbf24; padding: 10px 16px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 13px; font-family: inherit; }
        .btn-note:hover { background: rgba(250,204,21,0.25); }

        /* Info rows */
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13px; }
        .info-row:last-child { border-bottom: none; }
        .info-key { color: var(--text-muted); }
        .info-val { color: #e2e8f0; font-weight: 500; }

        /* History timeline */
        .history-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; font-size: 12px; }
        .history-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
        .history-text { color: var(--text-secondary); }
        .history-time { color: var(--text-muted); font-size: 11px; }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px; padding: 14px 24px; border-radius: 12px;
            font-size: 14px; font-weight: 600; z-index: 9999;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
        }
        .toast-success { background: rgba(52,211,153,0.2); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
        .toast-error { background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(-10px); } }

        /* Receipt styles */
        .receipt-grid { display: flex; gap: 12px; flex-wrap: wrap; }
        .receipt-thumb {
            position: relative; width: 200px; height: 250px; border-radius: 10px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: all 0.2s;
        }
        .receipt-thumb:hover { border-color: rgba(250,204,21,0.4); transform: scale(1.02); }
        .receipt-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .receipt-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.6);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; opacity: 0; transition: opacity 0.2s;
            font-size: 13px; font-weight: 600; color: #fde68a;
        }
        .receipt-thumb:hover .receipt-overlay { opacity: 1; }
        .receipt-overlay i { font-size: 24px; }
        .receipt-error {
            width: 100%; height: 100%; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 8px;
            background: rgba(255,255,255,0.03); color: var(--text-muted); font-size: 12px;
        }
        .receipt-error i { font-size: 32px; }

        /* Lightbox */
        .lightbox {
            display: none; position: fixed; inset: 0; z-index: 10000;
            background: rgba(0,0,0,0.92); align-items: center; justify-content: center;
        }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 90vh; object-fit: contain; border-radius: 8px; }
        .lightbox-close {
            position: absolute; top: 20px; right: 20px; width: 44px; height: 44px;
            border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: #f1f5f9; font-size: 18px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; transition: all 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.2); }

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
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script src="/js/csrf_interceptor.js?v=2026.03.25.1"></script>
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
                <a href="submissions_dashboard.php" class="sidebar-link <?= ($statusFilter !== 'completed') ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Submissions</a>
                <a href="completed_orders.php" class="sidebar-link <?= ($statusFilter === 'completed') ? 'active' : '' ?>"><i class="fas fa-clipboard-check"></i> Completed Orders</a>
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
                        <input type="text" id="main-search-input" name="search" placeholder="Search orders, names..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
                        <div id="main-search-suggestions" class="search-suggestions"></div>
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
                        <a href="?status=rejected<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'rejected' ? 'active' : '' ?>">
                            Rejected <span class="count"><?= $counts['rejected'] ?></span>
                        </a>
                        <a href="?status=trash<?= $search ? '&search='.urlencode($search) : '' ?>" class="filter-tab <?= $statusFilter === 'trash' ? 'active' : '' ?>" style="<?= (int)$counts['trashed'] > 0 ? '' : 'opacity:0.5;' ?>">
                            <i class="fas fa-trash-alt" style="margin-right:4px;font-size:11px;"></i> Trash <span class="count"><?= $counts['trashed'] ?></span>
                        </a>
                    </div>
                    <div style="font-size:13px; color:var(--text-muted);">
                        Showing <?= count($submissions) ?> <?= $isTrash ? 'trashed' : '' ?> submissions
                    </div>
                </div>

                <!-- Submissions List -->
                <!-- Select All Row -->
                <?php if (!empty($submissions)): ?>
                <div class="select-all-row">
                    <label><input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this)"> <strong>Select All</strong></label>
                    <span id="bulk-select-info" style="color:var(--text-muted);"></span>
                </div>
                <?php endif; ?>

                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if (empty($submissions)): ?>
                        <div class="empty-state">
                            <?php if ($isTrash): ?>
                                <i class="fas fa-trash-alt"></i>
                                <h3 style="font-size:18px; font-weight:600; margin-bottom:8px;">Trash is empty</h3>
                                <p>Deleted submissions will appear here and can be restored.</p>
                            <?php else: ?>
                                <i class="fas fa-inbox"></i>
                                <h3 style="font-size:18px; font-weight:600; margin-bottom:8px;">No submissions found</h3>
                                <p>Try changing the filter or search criteria.</p>
                            <?php endif; ?>
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
                            $subId = $sub['id'];

                            // Extract receipts
                            $formData = json_decode($sub['form_data'], true) ?: [];
                            $receipts = [];
                            if (!empty($formData['receipts']) && is_array($formData['receipts'])) {
                                $receipts = array_filter($formData['receipts']);
                            } elseif (!empty($formData['receipt_url'])) {
                                $receipts = [$formData['receipt_url']];
                            }

                            $paymentMethod = htmlspecialchars($sub['payment_method'] ?? '');
                            $platform = htmlspecialchars($sub['platform'] ?? '');
                            $amountUsdt = $sub['amount_usdt'] ? number_format((float)$sub['amount_usdt'], 2) . ' USDT' : '';
                            $amountTzs = $sub['amount_tzs'] ? 'TZS ' . number_format((float)$sub['amount_tzs'], 0) : '';
                            $platformUid = htmlspecialchars($sub['platform_uid'] ?? '');
                            $platformEmail = htmlspecialchars($sub['platform_email'] ?? '');
                        ?>
                        <div class="submission-card <?= $unread ? 'unread' : '' ?>" id="card-<?= $subId ?>" data-id="<?= $subId ?>" data-status="<?= $status ?>">
                            <div class="card-summary">
                                <div class="card-header">
                                    <div class="card-header-left">
                                        <div class="card-checkbox" onclick="event.stopPropagation()">
                                            <input type="checkbox" class="bulk-checkbox" value="<?= $subId ?>" onchange="updateBulkBar()">
                                        </div>
                                        <div onclick="toggleCard(<?= $subId ?>)" style="display:flex;align-items:center;gap:12px;cursor:pointer;flex:1;">
                                            <span class="card-order"><?= $order ?></span>
                                            <span style="font-size:12px; color:var(--text-muted);"><?= $type ?></span>
                                        </div>
                                    </div>
                                    <span class="card-status status-<?= $isTrash ? 'trashed' : $status ?>" id="badge-<?= $subId ?>"><?= $isTrash ? 'trashed' : $status ?></span>
                                </div>
                                <div class="card-body" onclick="toggleCard(<?= $subId ?>)" style="cursor:pointer;">
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
                                <div class="card-expand-hint" onclick="toggleCard(<?= $subId ?>)" style="cursor:pointer;"><i class="fas fa-chevron-down"></i> Click to view details & take action</div>
                            </div>

                            <div class="card-detail" id="detail-<?= $subId ?>">
                                <!-- Action Buttons -->
                                <div class="action-bar">
                                    <?php if ($isTrash): ?>
                                    <button class="action-btn btn-restore" onclick="restoreSubmission(<?= $subId ?>)">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                    <button class="action-btn btn-delete" onclick="permanentDeleteSubmission(<?= $subId ?>)">
                                        <i class="fas fa-trash-alt"></i> Delete Permanently
                                    </button>
                                    <?php else: ?>
                                    <button class="action-btn btn-review" onclick="updateStatus(<?= $subId ?>, 'reviewed')" id="btn-review-<?= $subId ?>" <?= $status === 'reviewed' ? 'disabled' : '' ?>>
                                        <i class="fas fa-eye"></i> Mark Reviewed
                                    </button>
                                    <button class="action-btn btn-complete" onclick="updateStatus(<?= $subId ?>, 'completed')" id="btn-complete-<?= $subId ?>" <?= $status === 'completed' ? 'disabled' : '' ?>>
                                        <i class="fas fa-check-circle"></i> Mark Complete
                                    </button>
                                    <button class="action-btn btn-reject" onclick="rejectSubmission(<?= $subId ?>)" id="btn-rejected-<?= $subId ?>" <?= $status === 'rejected' ? 'disabled' : '' ?>>
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                    <button class="action-btn btn-pending" onclick="updateStatus(<?= $subId ?>, 'pending')" id="btn-pending-<?= $subId ?>" <?= $status === 'pending' ? 'disabled' : '' ?>>
                                        <i class="fas fa-undo"></i> Reopen
                                    </button>
                                    <button class="action-btn btn-delete" onclick="trashSubmission(<?= $subId ?>)" id="btn-delete-<?= $subId ?>">
                                        <i class="fas fa-trash-alt"></i> Move to Trash
                                    </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Add Note -->
                                <div class="note-input-row">
                                    <input type="text" class="note-input" id="note-<?= $subId ?>" placeholder="Add admin note...">
                                    <button class="btn-note" onclick="addNote(<?= $subId ?>)"><i class="fas fa-paper-plane"></i></button>
                                </div>

                                <!-- Detail Info -->
                                <div class="detail-grid" id="info-<?= $subId ?>">
                                    <div class="detail-section">
                                        <div class="detail-label"><i class="fas fa-user" style="margin-right:6px;"></i>Customer Info</div>
                                        <div class="info-row"><span class="info-key">Name</span><span class="info-val"><?= $name ?></span></div>
                                        <div class="info-row"><span class="info-key">Email</span><span class="info-val"><?= $email ?></span></div>
                                        <div class="info-row"><span class="info-key">Order</span><span class="info-val"><?= $order ?></span></div>
                                        <div class="info-row"><span class="info-key">Amount</span><span class="info-val"><?= $amt ?: 'N/A' ?></span></div>
                                        <?php if ($amountUsdt): ?>
                                            <div class="info-row"><span class="info-key">USDT</span><span class="info-val"><?= $amountUsdt ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($amountTzs): ?>
                                            <div class="info-row"><span class="info-key">TZS</span><span class="info-val"><?= $amountTzs ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($platform): ?>
                                            <div class="info-row"><span class="info-key">Platform</span><span class="info-val"><?= $platform ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($platformUid): ?>
                                            <div class="info-row"><span class="info-key">Platform UID</span><span class="info-val"><?= $platformUid ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($paymentMethod): ?>
                                            <div class="info-row"><span class="info-key">Payment</span><span class="info-val"><?= $paymentMethod ?></span></div>
                                        <?php endif; ?>
                                        <div class="info-row"><span class="info-key">Created</span><span class="info-val"><?= date('M j, Y H:i', strtotime($sub['created_at'])) ?></span></div>
                                    </div>
                                    <div class="detail-section">
                                        <div class="detail-label"><i class="fas fa-history" style="margin-right:6px;"></i>Status History</div>
                                        <div id="history-<?= $subId ?>">
                                            <div style="color:var(--text-muted); font-size:12px;">Loading...</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Receipt -->
                                <?php if (!empty($receipts)): ?>
                                <div class="detail-section" style="margin-top:12px;">
                                    <div class="detail-label"><i class="fas fa-receipt" style="margin-right:6px;"></i>Payment Receipt (<?= count($receipts) ?>)</div>
                                    <div class="receipt-grid">
                                        <?php foreach ($receipts as $idx => $receiptPath):
                                            $cleanPath = str_replace('\\/', '/', $receiptPath);
                                            $imgUrl = '../' . ltrim($cleanPath, '/');
                                        ?>
                                        <div class="receipt-thumb" onclick="openLightbox('<?= htmlspecialchars($imgUrl) ?>')">
                                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Receipt <?= $idx + 1 ?>" onerror="this.parentElement.innerHTML='<div class=receipt-error><i class=fas fa-image></i><span>Image not available</span></div>'">
                                            <div class="receipt-overlay"><i class="fas fa-search-plus"></i> View Full Size</div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="detail-section" style="margin-top:12px;">
                                    <div class="detail-label"><i class="fas fa-receipt" style="margin-right:6px;"></i>Payment Receipt</div>
                                    <div style="color:var(--text-muted); font-size:13px; padding:8px 0;"><i class="fas fa-exclamation-triangle" style="margin-right:6px; color:#fbbf24;"></i>No receipt uploaded</div>
                                </div>
                                <?php endif; ?>

                                <!-- Admin Notes -->
                                <div class="detail-section" style="margin-top:12px;">
                                    <div class="detail-label"><i class="fas fa-sticky-note" style="margin-right:6px;"></i>Admin Notes</div>
                                    <div id="notes-<?= $subId ?>" style="font-size:13px; color:var(--text-secondary); white-space:pre-line;">Loading...</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulk-bar" id="bulk-bar">
        <span class="bulk-count" id="bulk-count">0 selected</span>
        <?php if ($isTrash): ?>
        <button class="bulk-btn bulk-btn-restore" onclick="bulkRestore()"><i class="fas fa-undo"></i> Restore All</button>
        <button class="bulk-btn bulk-btn-permadelete" onclick="bulkPermanentDelete()"><i class="fas fa-skull-crossbones"></i> Delete Forever</button>
        <?php else: ?>
        <button class="bulk-btn bulk-btn-trash" onclick="bulkTrash()"><i class="fas fa-trash-alt"></i> Move to Trash</button>
        <?php endif; ?>
        <button class="bulk-btn bulk-btn-cancel" onclick="clearBulkSelection()"><i class="fas fa-times"></i> Cancel</button>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
        <img id="lightbox-img" src="" alt="Receipt full view" onclick="event.stopPropagation()">
    </div>
    <script>
    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    async function apiCall(data) {
        const res = await fetch('submission_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    }

    function toggleCard(id) {
        const card = document.getElementById('card-' + id);
        const wasExpanded = card.classList.contains('expanded');
        
        // Close all others
        document.querySelectorAll('.submission-card.expanded').forEach(c => c.classList.remove('expanded'));

        if (!wasExpanded) {
            card.classList.add('expanded');
            card.classList.remove('unread');
            loadDetails(id);
        }
    }

    async function loadDetails(id) {
        const result = await apiCall({ action: 'get_details', id });
        if (!result.success) return;

        const sub = result.submission;
        const history = result.history;

        // Status history
        const histEl = document.getElementById('history-' + id);
        if (history.length === 0) {
            histEl.innerHTML = '<div style="color:var(--text-muted); font-size:12px;">No status changes yet</div>';
        } else {
            histEl.innerHTML = history.map(h => {
                const colors = { completed: '#34d399', reviewed: '#60a5fa', pending: '#fbbf24', rejected: '#f87171' };
                const color = colors[h.new_status] || '#94a3b8';
                const dt = new Date(h.created_at);
                const timeStr = dt.toLocaleDateString('en', { month:'short', day:'numeric' }) + ' ' + dt.toLocaleTimeString('en', { hour:'2-digit', minute:'2-digit' });
                return `<div class="history-item">
                    <div class="history-dot" style="background:${color}"></div>
                    <div>
                        <div class="history-text">${h.old_status || '—'} → <strong>${h.new_status}</strong>${h.notes ? ' — ' + h.notes : ''}</div>
                        <div class="history-time">${timeStr}</div>
                    </div>
                </div>`;
            }).join('');
        }

        // Admin notes
        const notesEl = document.getElementById('notes-' + id);
        notesEl.textContent = sub.admin_notes || 'No notes yet.';
    }

    async function updateStatus(id, newStatus) {
        const noteEl = document.getElementById('note-' + id);
        const notes = noteEl ? noteEl.value.trim() : '';
        
        const result = await apiCall({ action: 'update_status', id, status: newStatus, notes });
        if (result.success) {
            showToast('Status updated to ' + newStatus);
            // Update badge
            const badge = document.getElementById('badge-' + id);
            badge.className = 'card-status status-' + newStatus;
            badge.textContent = newStatus;
            // Update card data
            const card = document.getElementById('card-' + id);
            card.dataset.status = newStatus;
            // Update buttons
            ['pending', 'reviewed', 'completed', 'rejected'].forEach(s => {
                const btn = document.getElementById('btn-' + s + '-' + id);
                if (btn) btn.disabled = (s === newStatus);
            });
            // Clear note and reload details
            if (noteEl) noteEl.value = '';
            loadDetails(id);
        } else {
            showToast(result.error || 'Failed to update', 'error');
        }
    }

    async function rejectSubmission(id) {
        const reason = prompt('⚠️ Reject this submission?\n\nPlease enter the rejection reason (e.g. "Receipt doesn\'t match amount", "Fake receipt", "Wrong payment method"):');
        if (reason === null) return; // cancelled
        if (!reason.trim()) {
            showToast('Please provide a rejection reason', 'error');
            return;
        }
        // Put reason in the note field so it gets logged
        const noteEl = document.getElementById('note-' + id);
        if (noteEl) noteEl.value = reason.trim();
        await updateStatus(id, 'rejected');
    }

    async function addNote(id) {
        const noteEl = document.getElementById('note-' + id);
        const notes = noteEl.value.trim();
        if (!notes) { showToast('Please enter a note', 'error'); return; }

        const result = await apiCall({ action: 'add_note', id, notes });
        if (result.success) {
            showToast('Note added');
            noteEl.value = '';
            loadDetails(id);
        } else {
            showToast(result.error || 'Failed to add note', 'error');
        }
    }

    // Move single submission to trash (soft delete)
    async function trashSubmission(id) {
        if (!confirm('Move this submission to Trash?\n\nYou can restore it later from the Trash tab.')) return;
        const result = await apiCall({ action: 'bulk_trash', ids: [id] });
        if (result.success) {
            showToast('Moved to Trash', 'success');
            fadeOutCard(id);
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }

    // Restore single submission from trash
    async function restoreSubmission(id) {
        const result = await apiCall({ action: 'bulk_restore', ids: [id] });
        if (result.success) {
            showToast('Submission restored', 'success');
            fadeOutCard(id);
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }

    // Permanently delete single submission
    async function permanentDeleteSubmission(id) {
        if (!confirm('🚨 PERMANENTLY delete this submission?\n\nThis cannot be undone!')) return;
        const result = await apiCall({ action: 'permanent_delete', ids: [id] });
        if (result.success) {
            showToast('Deleted permanently', 'success');
            fadeOutCard(id);
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }

    function fadeOutCard(id) {
        const card = document.getElementById('card-' + id);
        if (!card) return;
        card.style.transition = 'all 0.4s';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => card.remove(), 400);
    }

    // ========== BULK SELECTION ==========
    function getSelectedIds() {
        return [...document.querySelectorAll('.bulk-checkbox:checked')].map(cb => parseInt(cb.value));
    }

    function updateBulkBar() {
        const ids = getSelectedIds();
        const bar = document.getElementById('bulk-bar');
        const countEl = document.getElementById('bulk-count');
        const infoEl = document.getElementById('bulk-select-info');
        if (ids.length > 0) {
            bar.classList.add('visible');
            countEl.textContent = ids.length + ' selected';
            if (infoEl) infoEl.textContent = ids.length + ' selected';
        } else {
            bar.classList.remove('visible');
            if (infoEl) infoEl.textContent = '';
        }
    }

    function toggleSelectAll(masterCb) {
        document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = masterCb.checked);
        updateBulkBar();
    }

    function clearBulkSelection() {
        document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = false);
        const selectAll = document.getElementById('select-all-checkbox');
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    }

    async function bulkTrash() {
        const ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm(`Move ${ids.length} submission(s) to Trash?\n\nYou can restore them later.`)) return;
        const result = await apiCall({ action: 'bulk_trash', ids });
        if (result.success) {
            showToast(`${ids.length} submission(s) moved to Trash`, 'success');
            ids.forEach(id => fadeOutCard(id));
            clearBulkSelection();
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }

    async function bulkRestore() {
        const ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm(`Restore ${ids.length} submission(s)?`)) return;
        const result = await apiCall({ action: 'bulk_restore', ids });
        if (result.success) {
            showToast(`${ids.length} submission(s) restored`, 'success');
            ids.forEach(id => fadeOutCard(id));
            clearBulkSelection();
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }

    async function bulkPermanentDelete() {
        const ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm(`🚨 PERMANENTLY delete ${ids.length} submission(s)?\n\nThis CANNOT be undone!`)) return;
        const result = await apiCall({ action: 'permanent_delete', ids });
        if (result.success) {
            showToast(`${ids.length} submission(s) permanently deleted`, 'success');
            ids.forEach(id => fadeOutCard(id));
            clearBulkSelection();
        } else {
            showToast(result.error || 'Failed', 'error');
        }
    }
    // Auto-expand from notification link
    <?php if ($expandId > 0): ?>
    (function() {
        const expandId = <?= $expandId ?>;
        const card = document.getElementById('card-' + expandId);
        if (card) {
            setTimeout(() => {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(() => toggleCard(expandId), 400);
            }, 300);
        }
    })();
    <?php endif; ?>

    // Search suggestions
    const searchInput = document.getElementById('main-search-input');
    const suggestionsBox = document.getElementById('main-search-suggestions');
    let searchTimeout;

    if (searchInput && suggestionsBox) {
        searchInput.addEventListener('input', function() {
            const val = this.value.trim();
            clearTimeout(searchTimeout);
            if (val.length < 2) {
                suggestionsBox.style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(async () => {
                const res = await apiCall({ action: 'get_suggestions', query: val });
                if (res.success && res.suggestions.length > 0) {
                    suggestionsBox.innerHTML = res.suggestions.map(s => `
                        <div class="search-suggestion-item" onclick="window.location.href='?search=${s.id}'">
                            <span class="search-suggestion-title">${s.order_number}</span>
                            <span class="search-suggestion-subtitle">${s.hint} &bull; ${s.status}</span>
                        </div>
                    `).join('');
                    suggestionsBox.style.display = 'flex';
                } else if (res.success) {
                    suggestionsBox.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#94a3b8;">No matches found</div>';
                    suggestionsBox.style.display = 'flex';
                }
            }, 300);
        });
        
        // Hide when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });
    }

    // New Order Audio Polling Script
    let lastSeenOrderId = <?= $maxId ?>;
    function playNotificationSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
            oscillator.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.1); // A5
            gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, audioCtx.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1);
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 1);
        } catch(e) { console.log(e); }
    }

    setInterval(() => {
        fetch('api_check_orders.php?last_id=' + lastSeenOrderId)
            .then(res => res.json())
            .then(data => {
                if (data.has_new) {
                    playNotificationSound();
                    lastSeenOrderId = data.max_id;
                    const toast = document.createElement('div');
                    toast.innerHTML = `
                        <div style="position:fixed; bottom:24px; right:24px; background:linear-gradient(135deg, #facc15, #fbbf24); color:#0f172a; padding:16px 24px; border-radius:12px; font-weight:700; box-shadow:0 10px 25px rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; gap:12px; cursor:pointer; font-family:'Inter',sans-serif; text-transform:uppercase; letter-spacing:0.5px; transition: transform 0.2s;" onclick="location.reload()" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fas fa-bell text-xl"></i>
                            ${data.new_count} New Order(s) Received! Click to refresh.
                        </div>
                    `;
                    document.body.appendChild(toast);
                    document.title = `(${data.new_count}) New Orders! - Admin Panel`;

                    // Seamless Live Data Swap
                    fetch(window.location.href)
                        .then(freshRes => freshRes.text())
                        .then(freshHtml => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(freshHtml, 'text/html');
                            
                            const filterTabs = document.querySelector('.filter-tabs');
                            if (filterTabs && doc.querySelector('.filter-tabs')) {
                                filterTabs.innerHTML = doc.querySelector('.filter-tabs').innerHTML;
                            }
                            
                            const submissionsList = document.querySelector('.submissions-list');
                            if (submissionsList && doc.querySelector('.submissions-list')) {
                                submissionsList.innerHTML = doc.querySelector('.submissions-list').innerHTML;
                            }
                        }).catch(e => console.error('Silent update failed:', e));
                }
            }).catch(console.error);
    }, 15000);
    </script>
</body>
</html>
