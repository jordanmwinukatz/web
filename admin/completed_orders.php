<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$limit = max(1, min(500, $limit));
$search = $_GET['search'] ?? '';

$sql = "SELECT id, order_number, submission_type, form_data, user_info, created_at, updated_at FROM user_submissions WHERE submission_status = 'completed'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (id LIKE ? OR order_number LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(user_info, '$.name')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(user_info, '$.email')) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY updated_at DESC LIMIT ?";
$stmt = $pdo->prepare($sql);

$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex, $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$records = [];
foreach ($rows as $row) {
    $form = json_decode($row['form_data'] ?? '[]', true);
    if (!is_array($form)) { $form = []; }
    $user = json_decode($row['user_info'] ?? '[]', true);
    if (!is_array($user)) { $user = []; }

    $sideRaw = $form['order_type'] ?? $row['submission_type'] ?? 'Buy';
    $side = strtolower((string)$sideRaw) === 'sell' ? 'Sell' : 'Buy';
    $platform = $form['platform'] ?? $form['platform_name'] ?? $form['exchange'] ?? '—';
    $paymentMethod = $form['payment_method'] ?? $form['paymentMethod'] ?? $form['payout_channel'] ?? '—';

    $amountInput = $form['amount_input'] ?? null;
    $amountInputUnit = $form['amount_input_unit'] ?? null;
    $primaryAmount = $amountInput
        ? trim($amountInput . ' ' . ($amountInputUnit ?: ''))
        : ($form['amount'] ?? '—');
    $amountUsdt = $form['amount_usdt'] ?? $form['cryptoAmount'] ?? $form['amountCrypto'] ?? null;
    $amountTzs = $form['amount_tzs'] ?? $form['fiatAmount'] ?? $form['amountFiat'] ?? $form['tzs'] ?? null;

    $receipts = [];
    if (!empty($form['receipts']) && is_array($form['receipts'])) {
        $receipts = array_filter($form['receipts']);
    } elseif (!empty($form['receipt_url'])) {
        $receipts = [$form['receipt_url']];
    }
    // Normalize relative paths so they resolve from admin/ directory
    $receipts = array_map(function($url) {
        if (!empty($url) && strpos($url, 'http') !== 0 && strpos($url, '/') !== 0 && strpos($url, '../') !== 0) {
            return '../' . $url;
        }
        return $url;
    }, $receipts);

    $records[] = [
        'id' => (int)$row['id'],
        'order_number' => $row['order_number'] ?: 'ORD-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
        'side' => $side,
        'platform' => $platform,
        'payment_method' => $paymentMethod,
        'amount_primary' => $primaryAmount,
        'amount_usdt' => $amountUsdt,
        'amount_tzs' => $amountTzs,
        'user_name' => $user['name'] ?? '—',
        'user_email' => $user['email'] ?? '—',
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'receipts' => $receipts,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Completed Orders - Jordan P2P Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <script src="/js/csrf_interceptor.js?v=2026.03.25.1"></script>
</head>
<body class="bg-[#0b1225] text-white min-h-screen">
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
                <a href="submissions_dashboard.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Submissions</a>
                <a href="completed_orders.php" class="sidebar-link active"><i class="fas fa-clipboard-check"></i> Completed Orders</a>
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
                    <span class="current">Completed Orders</span>
                </div>
            </div>

            <main class="px-8 lg:px-12 py-8 space-y-6 flex-1">
                <section class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-semibold">Completed Orders</h2>
                            <p class="text-sm text-white/60">Showing the most recent <?php echo htmlspecialchars((string)$limit); ?> records.</p>
                        </div>
                        <div class="flex flex-col md:flex-row items-end md:items-center gap-4">
                            <form method="get" class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 focus-within:ring-2 focus-within:ring-emerald-400/60 relative">
                                <i class="fas fa-search text-white/40 text-sm"></i>
                                <input type="text" id="completed-search-input" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search orders, names..." class="bg-transparent border-none outline-none text-sm text-white placeholder-white/40 w-48 xl:w-64" autocomplete="off">
                                <?php if ($limit !== 200): ?><input type="hidden" name="limit" value="<?php echo $limit; ?>"><?php endif; ?>
                                <div id="completed-search-suggestions" class="absolute top-full left-0 right-0 mt-2 bg-slate-800 border border-white/10 rounded-xl shadow-2xl overflow-hidden hidden z-[100] max-h-72 overflow-y-auto w-full min-w-[280px]"></div>
                            </form>
                            <form method="get" class="flex items-center gap-2">
                                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <label class="text-sm text-white/60">Show</label>
                                <select name="limit" onchange="this.form.submit()" class="bg-white/10 border border-white/20 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400/60">
                                    <?php foreach ([50, 100, 200, 300, 500] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php if ($opt === $limit) echo 'selected'; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="text-sm text-white/60">entries</span>
                            </form>
                        </div>
                    </div>

                    <?php if (empty($records)): ?>
                        <div class="text-center py-14 text-white/60">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No completed orders yet. Finish an order to see it logged here.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/10 text-sm">
                                <thead class="bg-white/5 text-white/70 uppercase tracking-wider text-xs">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Order</th>
                                        <th class="px-4 py-3 text-left">Type</th>
                                        <th class="px-4 py-3 text-left">Customer</th>
                                        <th class="px-4 py-3 text-left">Platform</th>
                                        <th class="px-4 py-3 text-left">Amounts</th>
                                        <th class="px-4 py-3 text-left">Payment/Payout</th>
                                        <th class="px-4 py-3 text-left">Proofs</th>
                                        <th class="px-4 py-3 text-left">Updated</th>
                                        <th class="px-4 py-3 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <?php foreach ($records as $record): 
                                        $previewTarget = strtolower($record['side']) === 'sell'
                                            ? '../sell-order.html?id=' . $record['id'] . '&readonly=1'
                                            : '../buy-order.html?id=' . $record['id'] . '&readonly=1';
                                        $proofCount = count($record['receipts']);
                                    ?>
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-white"><?php echo htmlspecialchars($record['order_number']); ?></div>
                                                <div class="text-xs text-white/50">#<?php echo htmlspecialchars((string)$record['id']); ?> • <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($record['created_at']))); ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold
                                                    <?php echo strtolower($record['side']) === 'sell'
                                                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                                        : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'; ?>">
                                                    <i class="fas fa-exchange-alt"></i>
                                                    <?php echo htmlspecialchars($record['side']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium"><?php echo htmlspecialchars($record['user_name']); ?></div>
                                                <div class="text-xs text-white/50"><?php echo htmlspecialchars($record['user_email']); ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium"><?php echo htmlspecialchars($record['platform']); ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div><?php echo htmlspecialchars($record['amount_primary']); ?></div>
                                                <?php if ($record['amount_usdt']): ?>
                                                    <div class="text-xs text-white/50"><?php echo htmlspecialchars($record['amount_usdt']); ?> USDT</div>
                                                <?php endif; ?>
                                                <?php if ($record['amount_tzs']): ?>
                                                    <div class="text-xs text-white/50"><?php echo htmlspecialchars($record['amount_tzs']); ?> TZS</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div><?php echo htmlspecialchars($record['payment_method']); ?></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($proofCount > 0): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-500/20 text-amber-200 text-xs border border-amber-400/40">
                                                        <i class="fas fa-paperclip"></i>
                                                        <?php echo $proofCount; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-white/30 text-xs">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-white/60">
                                                <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($record['updated_at']))); ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button onclick="openReviewModal(<?php echo (int)$record['id']; ?>)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-200 text-xs hover:bg-emerald-500/30 transition cursor-pointer">
                                                    <i class="fas fa-eye"></i> Review
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-white/50"></i>
                        Tips
                    </h3>
                    <ul class="space-y-2 text-sm text-white/60">
                        <li>• Use this log to reconcile payouts or investigate disputes.</li>
                        <li>• Click "Review" to reopen the exact order summary with attached proof files.</li>
                        <li>• Export data by copying the table into a spreadsheet when needed.</li>
                    </ul>
                </section>
            </main>
        </div>
    </div>

    <!-- Review Modal Overlay -->
    <div id="review-modal-overlay" class="fixed inset-0 z-[200] hidden" style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div id="review-modal" class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-[#0f172a] shadow-2xl overflow-hidden" style="max-height:90vh;overflow-y:auto;">
                <!-- Modal Header -->
                <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b border-white/10 bg-[#0f172a]/95 backdrop-blur">
                    <div class="flex items-center gap-3">
                        <div id="modal-icon" class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-emerald-500/15 text-emerald-400">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <h3 id="modal-title" class="text-base font-bold text-white">Order Review</h3>
                            <p id="modal-subtitle" class="text-xs text-white/50">Read-only view</p>
                        </div>
                    </div>
                    <button onclick="closeReviewModal()" class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/10 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Modal Body -->
                <div id="modal-body" class="px-6 py-5 space-y-4">
                    <div class="text-center text-white/50 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p class="text-sm">Loading order details…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Proof Image Lightbox -->
    <div id="proof-lightbox" class="fixed inset-0 z-[300] hidden items-center justify-center" style="background:rgba(0,0,0,0.92);display:none;" onclick="closeLightbox()">
        <button onclick="event.stopPropagation();closeLightbox()" class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-white text-lg hover:bg-white/20 transition z-10">
            <i class="fas fa-times"></i>
        </button>
        <img id="lightbox-img" src="" alt="Proof" style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;" onclick="event.stopPropagation()">
    </div>

    <script>
    // ── Order data embedded from PHP ──
    const orderRecords = <?php echo json_encode($records, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function openReviewModal(id) {
        const record = orderRecords.find(r => r.id === id);
        if (!record) return;

        const overlay = document.getElementById('review-modal-overlay');
        const icon = document.getElementById('modal-icon');
        const title = document.getElementById('modal-title');
        const subtitle = document.getElementById('modal-subtitle');
        const body = document.getElementById('modal-body');

        const isSell = record.side.toLowerCase() === 'sell';

        // Header
        icon.className = `w-9 h-9 rounded-lg flex items-center justify-center text-base ${isSell ? 'bg-rose-500/15 text-rose-400' : 'bg-emerald-500/15 text-emerald-400'}`;
        icon.innerHTML = isSell ? '<i class="fas fa-hand-holding-usd"></i>' : '<i class="fas fa-shopping-cart"></i>';
        title.textContent = record.order_number;
        subtitle.textContent = `${record.side} Order • Completed`;

        // Build info rows
        const infoRows = [
            ['Customer', record.user_name],
            ['Email', record.user_email],
            ['Platform', record.platform],
            ['Payment / Payout', record.payment_method],
        ];
        if (record.amount_usdt) infoRows.push(['USDT Amount', record.amount_usdt + ' USDT']);
        if (record.amount_tzs) infoRows.push(['TZS Amount', Number(record.amount_tzs).toLocaleString() + ' TZS']);
        if (!record.amount_usdt && !record.amount_tzs) infoRows.push(['Amount', record.amount_primary]);
        infoRows.push(['Created', new Date(record.created_at).toLocaleString()]);
        infoRows.push(['Updated', new Date(record.updated_at).toLocaleString()]);

        let html = '<div class="rounded-xl border border-white/5 bg-white/[0.02] divide-y divide-white/5">';
        infoRows.forEach(([label, value]) => {
            html += `<div class="flex justify-between items-center px-4 py-3 text-sm">
                <span class="text-white/50">${label}</span>
                <span class="text-white font-medium text-right">${value || '—'}</span>
            </div>`;
        });
        html += '</div>';

        // Proofs
        if (record.receipts && record.receipts.length > 0) {
            html += `<div class="pt-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/40 mb-3">
                    <i class="fas fa-receipt mr-1"></i> Payment Proofs (${record.receipts.length})
                </p>
                <div class="grid grid-cols-3 gap-2">`;
            record.receipts.forEach((url, i) => {
                html += `<div onclick="openLightbox('${url.replace(/'/g, "\\'")}')"
                    class="block rounded-lg overflow-hidden border border-white/10 hover:border-amber-400/40 transition aspect-square relative cursor-pointer">
                    <img src="${url}" alt="Proof ${i+1}" class="w-full h-full object-cover" loading="lazy">
                    <span class="absolute bottom-1 right-1.5 bg-black/70 text-amber-300 text-[10px] px-1.5 py-0.5 rounded-full">#${i+1}</span>
                </div>`;
            });
            html += '</div></div>';
        }

        body.innerHTML = html;
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewModal() {
        document.getElementById('review-modal-overlay').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ── Lightbox for proof images ──
    function openLightbox(url) {
        const lb = document.getElementById('proof-lightbox');
        const img = document.getElementById('lightbox-img');
        img.src = url;
        lb.style.display = 'flex';
    }
    function closeLightbox() {
        const lb = document.getElementById('proof-lightbox');
        lb.style.display = 'none';
        document.getElementById('lightbox-img').src = '';
    }

    // Close on overlay click
    document.getElementById('review-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this || e.target === this.firstElementChild) closeReviewModal();
    });
    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const lb = document.getElementById('proof-lightbox');
            if (lb.style.display === 'flex') { closeLightbox(); }
            else { closeReviewModal(); }
        }
    });

    // ── Search suggestions ──
    const searchInput = document.getElementById('completed-search-input');
    const suggestionsBox = document.getElementById('completed-search-suggestions');
    let searchTimeout;

    if (searchInput && suggestionsBox) {
        searchInput.addEventListener('input', function() {
            const val = this.value.trim();
            clearTimeout(searchTimeout);
            if (val.length < 2) {
                suggestionsBox.classList.add('hidden');
                return;
            }
            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch('submission_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get_suggestions', query: val })
                    });
                    const data = await res.json();
                    if (data.success && data.suggestions.length > 0) {
                        suggestionsBox.innerHTML = data.suggestions.map(s => `
                            <div class="p-3 border-b border-white/5 hover:bg-white/5 cursor-pointer flex flex-col gap-0.5 transition" onclick="window.location.href='?search=${s.id}'">
                                <span class="text-sm font-semibold text-emerald-400">${s.order_number}</span>
                                <span class="text-xs text-white/50 capitalize">${s.hint} &bull; ${s.status}</span>
                            </div>
                        `).join('') + '<div class="p-2 border-b-0 border-transparent"></div>';
                        suggestionsBox.classList.remove('hidden');
                    } else if (data.success) {
                        suggestionsBox.innerHTML = '<div class="p-3 text-xs text-white/50">No matches found</div>';
                        suggestionsBox.classList.remove('hidden');
                    }
                } catch (e) {
                    console.error('Search error', e);
                }
            }, 300);
        });
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.add('hidden');
            }
        });
    }
    </script>
</body>
</html>
