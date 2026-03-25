<?php
/**
 * core/Model.php
 * Base class for all MVC Models.
 */

require_once __DIR__ . '/../config/database.php';

abstract class Model {
    protected $db;
    protected $table;

    public function __construct() {
        // Automatically reuse the PDO singleton
        $this->db = Database::shared()->getConnection();
    }

    /**
     * Helper to find a record by ID.
     */
    public function find($id) {
        if (!$this->table) throw new Exception("Model table not defined");
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the underlying PDO instance for custom queries.
     */
    public function connection() {
        return $this->db;
    }
}
