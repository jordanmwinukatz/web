<?php
/**
 * models/User.php
 */

require_once __DIR__ . '/../core/Model.php';

class User extends Model {
    protected $table = 'users';

    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $email, $passwordHash, $emailVerified = 1) {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (name, email, password_hash, email_verified) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $passwordHash, $emailVerified])) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function updatePassword($userId, $newPasswordHash) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$newPasswordHash, $userId]);
    }
}
