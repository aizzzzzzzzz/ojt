<?php
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

    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage());
            return false;
        }
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    public function getUserByCredentials($table, $usernameField, $username, $password) {
        $query = "SELECT * FROM $table WHERE $usernameField = ?";
        $user = $this->selectOne($query, [$username]);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

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

    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $query = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES ($placeholders)";

        if ($this->execute($query, array_values($data))) {
            return $this->lastInsertId();
        }
        return false;
    }

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
