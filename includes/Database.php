<?php

class Database {
    private $pdo;

    /**
     * Constructor that accepts a PDO instance.
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Prepares an SQL statement for execution.
     * @param string $sql The SQL query to prepare.
     * @return PDOStatement Returns a PDOStatement object.
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    /**
     * Executes a prepared statement with given parameters.
     * @param PDOStatement $stmt The PDOStatement object to execute.
     * @param array $params An array of parameters to bind to the statement.
     * @return PDOStatement Returns the executed PDOStatement object.
     */
    public function execute($stmt, $params = []) {
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetches a single row from a statement.
     * @param PDOStatement $stmt The PDOStatement object to fetch from.
     * @return mixed Returns a single row (array or object, depending on fetch mode) or false if no more rows.
     */
    public function fetch($stmt) {
        return $stmt->fetch();
    }

    /**
     * Fetches all rows from a statement.
     * @param PDOStatement $stmt The PDOStatement object to fetch from.
     * @return array Returns an array containing all of the remaining rows in the result set.
     */
    public function fetchAll($stmt) {
        return $stmt->fetchAll();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @return string Returns the ID of the last inserted row.
     */
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

?>
