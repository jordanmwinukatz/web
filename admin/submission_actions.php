<?php
require_once 'auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'update_status':
            $id = (int)($input['id'] ?? 0);
            $newStatus = $input['status'] ?? '';
            $notes = $input['notes'] ?? '';
            
            if (!$id || !in_array($newStatus, ['pending', 'reviewed', 'completed', 'rejected'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }

            // Get current status
            $stmt = $pdo->prepare("SELECT submission_status FROM user_submissions WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                exit;
            }

            $oldStatus = $current['submission_status'];

            // Update status
            $update = $pdo->prepare("UPDATE user_submissions SET submission_status = ?, admin_notes = CONCAT(IFNULL(admin_notes,''), ?), admin_viewed = 1, admin_viewed_at = NOW() WHERE id = ?");
            $noteAppend = $notes ? "\n[" . date('Y-m-d H:i') . "] Status: $newStatus - $notes" : "\n[" . date('Y-m-d H:i') . "] Status changed to: $newStatus";
            $update->execute([$newStatus, $noteAppend, $id]);

            // Log to status history
            $history = $pdo->prepare("INSERT INTO submission_status_history (submission_id, old_status, new_status, admin_user, notes, created_at) VALUES (?, ?, ?, 'admin', ?, NOW())");
            $history->execute([$id, $oldStatus, $newStatus, $notes]);

            echo json_encode(['success' => true, 'message' => "Status updated to $newStatus"]);
            break;

        case 'add_note':
            $id = (int)($input['id'] ?? 0);
            $notes = trim($input['notes'] ?? '');

            if (!$id || empty($notes)) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }

            $update = $pdo->prepare("UPDATE user_submissions SET admin_notes = CONCAT(IFNULL(admin_notes,''), ?) WHERE id = ?");
            $noteAppend = "\n[" . date('Y-m-d H:i') . "] Note: $notes";
            $update->execute([$noteAppend, $id]);

            echo json_encode(['success' => true, 'message' => 'Note added']);
            break;

        case 'mark_viewed':
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'Invalid ID']);
                exit;
            }
            $update = $pdo->prepare("UPDATE user_submissions SET admin_viewed = 1, admin_viewed_at = NOW() WHERE id = ?");
            $update->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_details':
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'Invalid ID']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM user_submissions WHERE id = ?");
            $stmt->execute([$id]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sub) {
                echo json_encode(['success' => false, 'error' => 'Not found']);
                exit;
            }

            // Get status history
            $histStmt = $pdo->prepare("SELECT * FROM submission_status_history WHERE submission_id = ? ORDER BY created_at DESC");
            $histStmt->execute([$id]);
            $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark as viewed
            $pdo->prepare("UPDATE user_submissions SET admin_viewed = 1, admin_viewed_at = NOW() WHERE id = ? AND admin_viewed = 0")->execute([$id]);

            echo json_encode([
                'success' => true,
                'submission' => $sub,
                'history' => $history
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
