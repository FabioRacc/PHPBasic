<?php
require_once 'Exceptions/DBConnectionException.php';
require_once 'Exceptions/QueryException.php';
require_once 'Exceptions/QueryBindingException.php';
require_once 'Exceptions/QueryPrepareException.php';
require_once 'Exceptions/QueryFetchingException.php';

class Database {
    private $host;
    private $dbname;
    private $port;
    private $username;
    private $password;
    private $charset;
    private $db;
    private $stmt;
    
    public function __construct($host = 'localhost', $dbname = 'test', $port ='3306', $username = 'root', $password = '', $charset = 'utf8mb4') {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
        
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};port={$this->port};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->db = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $message = "Errore connesione al database";
            Debug::log($message, 'DATABASE');
            throw new DBConnectionException($message, 0, $e);
        }
    }
    
    public function query($query, $prepare = false, array $bindings = []) {
        Debug::startTimer($query);
        Debug::memoryCheckpoint($query."-start");
        try {
            if ($prepare) {
                $this->prepare($query, $bindings);
                $this->execute();
            } else {
                $this->stmt = $this->db->query($query);
            }
            
            Debug::endTimer($query);
            Debug::memoryCheckpoint($query."-end");
            Debug::setQuery($query, $bindings);
            Debug::logQuery($query);
            
            return $this;
        } catch (PDOException $e) {
            $message = "Errore con la query: {$query}";
            Debug::log($message, 'QUERY');
            throw new QueryException($message, 0, $e);
        }
    }
    
    public function prepare($query, $bindings) {
        try {
            $this->stmt = $this->db->prepare($query);
            
            foreach ($bindings as $key => $value) {
                $this->bind($key, $value);
            }
        } catch (PDOException $e) {
            $message = "Problemi col prepare della query: {$query}";
            Debug::log($message, 'QUERY');
            throw new QueryException($message, 0, $e);
        }
    }
    
    public function bind($param, $value, $type = null) {
        try {
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
        } catch (PDOException $e) {
            $message = "Errore col binding di un parametro: {$param}";
            Debug::log($message, 'QUERY');
            throw new QueryBindingException($message, 0, $e);
        }
    }
    
    public function execute() {
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $message = "Non sono riuscito ad eseguire la query";
            Debug::log($message, 'QUERY');
            throw new QueryException($message, 0, $e);
        }
    }
    
    public function single() {
        try {
            return empty($this->stmt) ? null : $this->stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = "Errore nel fetch: {$this->stmt}";
            Debug::log($message, 'QUERY');
            throw new QueryFetchingException($message, 0, $e);
        }
    }
    
    public function all() {
        try {
            return empty($this->stmt) ? null : $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = "Errore nel fetch: {$this->stmt}";
            Debug::log($message, 'QUERY');
            throw new QueryFetchingException($message, 0, $e);
        }
    }
    
    public function rowCount() {
        try {
            return $this->stmt->rowCount();
        } catch (PDOException $e) {
            $message = "Errore nel contare le righe: {$this->stmt}";
            Debug::log($message, 'QUERY');
            throw new QueryException($message, 0, $e);
        }
    }
    
    public function lastInsertId() {
        try {
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            $message = "Errore nel prendere ultimo id: {$this->stmt}";
            Debug::log($message, 'QUERY');
            throw new QueryException($message, 0, $e);
        }
    }
}
