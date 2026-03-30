<?php
// Simple authentication check for admin pages
// Redirects to main site login if not authenticated

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuthenticated = false;

// 1. Fast path: session flag already set
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isAuthenticated = true;
}

// 2. Fallback: user is logged in — check email against known admin list
//    (covers the case where the is_admin DB column hasn't been added yet)
if (!$isAuthenticated && !empty($_SESSION['user_id'])) {
    $adminEmails = [
        'jordanmwinukatz@gmail.com',
        'thiongoowen7@gmail.com',
    ];

    try {
        require_once __DIR__ . '/../config/database.php';
        $db  = new Database();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && in_array($row['email'], $adminEmails, true)) {
            // Promote session so future requests take the fast path
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = [
                'id'    => $_SESSION['user_id'],
                'email' => $row['email'],
            ];
            $isAuthenticated = true;
        }
    } catch (Exception $e) {
        error_log('Admin auth fallback error: ' . $e->getMessage());
    }
}

// If not authenticated, redirect to main site with login modal
if (!$isAuthenticated) {
    header('Location: ../index.html?login=1');
    exit();
}
