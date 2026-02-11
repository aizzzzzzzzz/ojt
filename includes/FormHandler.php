<?php
class FormHandler {
    private $errors = [];
    private $data = [];

    public function validateRequired($field, $value, $message = null) {
        if (empty(trim($value))) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' is required.';
            return false;
        }
        return true;
    }

    public function validateEmail($field, $value, $message = null) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?: 'Invalid email format.';
            return false;
        }
        return true;
    }

    public function validateMinLength($field, $value, $minLength, $message = null) {
        if (strlen($value) < $minLength) {
            $this->errors[$field] = $message ?: ucfirst($field) . " must be at least $minLength characters.";
            return false;
        }
        return true;
    }

    public function validateMaxLength($field, $value, $maxLength, $message = null) {
        if (strlen($value) > $maxLength) {
            $this->errors[$field] = $message ?: ucfirst($field) . " must not exceed $maxLength characters.";
            return false;
        }
        return true;
    }

    public function validateNumeric($field, $value, $message = null) {
        if (!is_numeric($value)) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' must be a number.';
            return false;
        }
        return true;
    }

    public function validatePassword($field, $value, $message = null) {
        if (!validate_password($value)) {
            $this->errors[$field] = $message ?: 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
            return false;
        }
        return true;
    }
    public function validateMatch($field1, $value1, $field2, $value2, $message = null) {
        if ($value1 !== $value2) {
            $this->errors[$field2] = $message ?: ucfirst($field1) . ' and ' . ucfirst($field2) . ' do not match.';
            return false;
        }
        return true;
    }

    public function validateUnique($field, $value, $table, $column, $excludeId = null, $message = null) {
        $db = Database::getInstance();
        $query = "SELECT 1 FROM $table WHERE $column = ?";
        $params = [$value];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }

        if ($db->selectOne($query, $params)) {
            $this->errors[$field] = $message ?: ucfirst($field) . ' already exists.';
            return false;
        }
        return true;
    }

    public function sanitizeInput($field, $value) {
        $this->data[$field] = sanitize_input($value);
        return $this->data[$field];
    }

    public function getData($field = null) {
        return $field ? ($this->data[$field] ?? null) : $this->data;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function isValid() {
        return empty($this->errors);
    }
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    public function reset() {
        $this->errors = [];
        $this->data = [];
    }

    public function processLogin($username, $password) {
        $this->reset();

        $this->validateRequired('username', $username);
        $this->validateRequired('password', $password);

        if ($this->isValid()) {
            $this->sanitizeInput('username', $username);
            $this->sanitizeInput('password', $password);
        }

        return $this->isValid();
    }

    public function processStudentRegistration($data) {
        $this->reset();

        $this->validateRequired('username', $data['username']);
        $this->validateRequired('password', $data['password']);
        $this->validateRequired('first_name', $data['first_name']);
        $this->validateRequired('last_name', $data['last_name']);
        $this->validateRequired('course', $data['course']);
        $this->validateRequired('school', $data['school']);
        $this->validateNumeric('required_hours', $data['required_hours']);

        if ($this->isValid()) {
            $this->validateMinLength('username', $data['username'], 3);
            $this->validateMaxLength('username', $data['username'], 50);
            $this->validatePassword('password', $data['password']);
            $this->validateUnique('username', $data['username'], 'students', 'username');
        }

        if ($this->isValid()) {
            foreach ($data as $field => $value) {
                $this->sanitizeInput($field, $value);
            }
        }

        return $this->isValid();
    }

    public function processEmployerRegistration($data) {
        $this->reset();

        $this->validateRequired('username', $data['username']);
        $this->validateRequired('password', $data['password']);
        $this->validateRequired('name', $data['name']);
        $this->validateRequired('company', $data['company']);

        if ($this->isValid()) {
            $this->validateMinLength('username', $data['username'], 3);
            $this->validateMaxLength('username', $data['username'], 50);
            $this->validatePassword('password', $data['password']);
            $this->validateUnique('username', $data['username'], 'employers', 'username');
        }

        if ($this->isValid()) {
            foreach ($data as $field => $value) {
                $this->sanitizeInput($field, $value);
            }
        }

        return $this->isValid();
    }
}
?>
