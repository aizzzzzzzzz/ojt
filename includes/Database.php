<?php
/**
 * Database Class - Centralized database operations
 * Provides a clean interface for all database interactions
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a SELECT query and return results
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database select error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a SELECT query and return single row
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database selectOne error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Get user by credentials (for authentication)
     */
    public function getUserByCredentials($table, $usernameField, $username, $password) {
        $query = "SELECT * FROM $table WHERE $usernameField = ?";
        $user = $this->selectOne($query, [$username]);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    /**
     * Check if record exists
     */
    public function exists($table, $conditions, $params = []) {
        $whereClause = '';
        $i = 0;
        foreach ($conditions as $field => $value) {
            $whereClause .= ($i > 0 ? ' AND ' : '') . "$field = ?";
            $i++;
        }

        $query = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";
        $result = $this->selectOne($query, $params ?: array_values($conditions));
        return $result !== false;
    }

    /**
     * Insert record and return ID
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $query = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES ($placeholders)";

        if ($this->execute($query, array_values($data))) {
            return $this->lastInsertId();
        }
        return false;
    }

    /**
     * Update records
     */
    public function update($table, $data, $conditions, $conditionParams = []) {
        $setClause = '';
        $i = 0;
        foreach ($data as $field => $value) {
            $setClause .= ($i > 0 ? ', ' : '') . "$field = ?";
            $i++;
        }

        $whereClause = '';
        $j = 0;
        foreach ($conditions as $field => $value) {
            $whereClause .= ($j > 0 ? ' AND ' : '') . "$field = ?";
            $j++;
        }

        $query = "UPDATE $table SET $setClause WHERE $whereClause";
        $params = array_merge(array_values($data), $conditionParams ?: array_values($conditions));

        return $this->execute($query, $params);
    }

    /**
     * Delete records
     */
    public function delete($table, $conditions, $params = []) {
        $whereClause = '';
        $i = 0;
        foreach ($conditions as $field => $value) {
            $whereClause .= ($i > 0 ? ' AND ' : '') . "$field = ?";
            $i++;
        }

        $query = "DELETE FROM $table WHERE $whereClause";
        return $this->execute($query, $params ?: array_values($conditions));
    }
}
?>
