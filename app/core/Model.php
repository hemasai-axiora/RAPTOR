<?php
// Raptor CRM Core Base Model

class Model {
    protected $db;
    protected $stmt;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Prepare statement with query
    public function query($sql) {
        $this->stmt = $this->db->prepare($sql);
    }

    // Bind values
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    // Execute the prepared statement
    public function execute() {
        try {
            return $this->stmt ? $this->stmt->execute() : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    // Get result set as array of objects
    public function resultSet() {
        try {
            if (!$this->stmt || !$this->execute()) {
                return [];
            }
            $res = $this->stmt->fetchAll(PDO::FETCH_OBJ);
            return is_array($res) ? $res : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // Get single record as object
    public function single() {
        try {
            if (!$this->stmt || !$this->execute()) {
                return false;
            }
            $res = $this->stmt->fetch(PDO::FETCH_OBJ);
            return $res ?: false;
        } catch (Throwable $e) {
            return false;
        }
    }

    // Get row count
    public function rowCount() {
        try {
            return $this->stmt ? $this->stmt->rowCount() : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    // Get last inserted ID
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
}
