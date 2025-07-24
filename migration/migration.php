<?php

require_once __DIR__ . '/../vendor/autoload.php';

function prompt(string $message): string {
    echo $message;
    return trim(fgets(STDIN));
}

function writeEnv(array $config): void {
    $envPath = __DIR__ . '/../.env';
    $env = <<<ENV
DB_DRIVER={$config['driver']}
DB_HOST={$config['host']}
DB_PORT={$config['port']}
DB_NAME={$config['dbname']}
DB_USERNAME={$config['user']}
DB_PASSWORD={$config['pass']}
DSN="{$config['driver']}:host={$config['host']};dbname={$config['dbname']};port={$config['port']}"
ENV;

    file_put_contents($envPath, $env);
    echo ".env généré/mis à jour avec succès.\n";
}

// Valeurs par défaut adaptées à l'environnement Docker
$driver = strtolower(prompt("Quel SGBD utiliser ? (mysql / pgsql) : "));
$host = prompt("Hôte (default: db) : ") ?: "db";
$port = prompt("Port (default: 3306 ou 5434) : ") ?: ($driver === 'pgsql' ? "5434" : "3306");
$user = prompt("Utilisateur (default: root/papasidy) : ") ?: ($driver === 'pgsql' ? "papasidy" : "root");
$pass = prompt("Mot de passe : ");
$dbName = prompt("Nom de la base à créer : ");

try {
    // Connexion sans base pour pouvoir en créer une
    $dsn = "$driver:host=$host;port=$port";
    if ($driver === 'pgsql') {
        $dsn .= ";dbname=postgres";
    }

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($driver === 'pgsql') {
        $check = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$dbName'")->fetch();
        if (!$check) {
            $pdo->exec("CREATE DATABASE \"$dbName\"");
            echo "✅ Base PostgreSQL `$dbName` créée.\n";
        } else {
            echo "ℹ️ Base PostgreSQL `$dbName` déjà existante.\n";
        }
    } elseif ($driver === 'mysql') {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Base MySQL `$dbName` créée ou existante.\n";
    } else {
        die("❌ SGBD non supporté\n");
    }

    // 🔄 Connexion à la base cible
    $dsnDb = "$driver:host=$host;port=$port;dbname=$dbName";
    $pdo = new PDO($dsnDb, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 📦 Création des tables
    $tables = [];

    if ($driver === 'pgsql') {
        $tables = [
            "CREATE TABLE IF NOT EXISTS citoyen (
                id SERIAL PRIMARY KEY,
                nom VARCHAR(100) NOT NULL,
                prenom VARCHAR(100) NOT NULL,
                date_naissance DATE NOT NULL,
                lieu_naissance VARCHAR(150) NOT NULL,
                cni VARCHAR(20) UNIQUE NOT NULL,
                cni_recto_url TEXT NOT NULL,
                cni_verso_url TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "CREATE INDEX IF NOT EXISTS idx_citoyen_cni ON citoyen(cni);",
            "CREATE TABLE IF NOT EXISTS log (
                id SERIAL PRIMARY KEY,
                date DATE NOT NULL,
                heure TIME NOT NULL,
                localisation VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                statut VARCHAR(10) CHECK (statut IN ('SUCCES', 'ERROR')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );"
        ];
    } else {
        $tables = [
            "CREATE TABLE IF NOT EXISTS citoyen (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(100) NOT NULL,
                prenom VARCHAR(100) NOT NULL,
                date_naissance DATE NOT NULL,
                lieu_naissance VARCHAR(150) NOT NULL,
                cni VARCHAR(20) UNIQUE NOT NULL,
                cni_recto_url TEXT NOT NULL,
                cni_verso_url TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;",
            "CREATE INDEX idx_citoyen_cni ON citoyen(cni);",
            "CREATE TABLE IF NOT EXISTS log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                heure TIME NOT NULL,
                localisation VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                statut ENUM('SUCCES', 'ERROR') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;"
        ];
    }

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    echo "✅ Tables `citoyen` et `log` créées avec succès.\n";

    writeEnv([
        'driver' => $driver,
        'host'   => $host,
        'port'   => $port,
        'user'   => $user,
        'pass'   => $pass,
        'dbname' => $dbName
    ]);

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
