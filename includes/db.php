<?php
require_once __DIR__ . '/config.php';
class Database {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    static function getInstance() { if(!self::$instance) self::$instance = new self(); return self::$instance; }
    function getConnection() { return $this->pdo; }
}
function getDb() { return Database::getInstance()->getConnection(); }
?>