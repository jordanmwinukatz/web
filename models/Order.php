<?php
/**
 * models/Order.php
 */

require_once __DIR__ . '/../core/Model.php';

class Order extends Model {
    protected $table = 'user_submissions';

    public function findBySessionId($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE session_id = ? ORDER BY created_at DESC");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPending() {
        $stmt = $this->db->query("SELECT COUNT(*) AS c FROM {$this->table} WHERE submission_status = 'pending' AND (admin_viewed = 0 OR admin_viewed IS NULL)");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }
}
?>
