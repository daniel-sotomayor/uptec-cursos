<?php

/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * Configuracion de Base de Datos
 *
 * Universidad Politecnica Territorial de Caracas Mariscal Sucre
 * Patron Singleton para conexion PDO con MySQL
 */

declare(strict_types=1);

if (!defined('UPTEC_ACCESS')) {
    define('UPTEC_ACCESS', true);
}

/**
 * Clase Database - Singleton para gestion de conexiones PDO
 */
class Database
{
    private static ?self $instance = null;
    private ?PDO $connection = null;

    private string $host = 'localhost';
    private string $database = 'uptec_cursos';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';

    private function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("[UPTEC] Error de conexion DB: " . $e->getMessage());
            throw new Exception("Error al conectar con la base de datos");
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function __clone()
    {
        throw new Exception("Clonacion no permitida");
    }

    public function __wakeup()
    {
        throw new Exception("Deserializacion no permitida");
    }
}

/**
 * Helper para obtener conexion a base de datos
 * @return PDO Conexion activa
 */
function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}
