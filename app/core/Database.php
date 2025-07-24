<?php

namespace AppDAF\CORE;

use PDO;

class Database extends Singleton
{
    private ?PDO $pdo = null;
    private static array $configDefault = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    public function __construct()
    {
        try {
            $dsn = DB_DRIVE . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
            $this->pdo = new PDO(
                $dsn,
                DB_USERNAME,
                DB_PASSWORD,
                self::$configDefault
            );
        } catch (\PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }

    public function getConnexion(): PDO
    {
        return $this->pdo;
    }
}
