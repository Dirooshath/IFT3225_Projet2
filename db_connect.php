<?php
function dbConnect() {
    $host = 'localhost';  // ou l'adresse du serveur MySQL (DIRO?)
    $db   = 'vranderl_projet2';    // nom de ta base
    $user = 'vranderl';       // ton user MySQL
    $pass = 's7M7J5U:7:9bhf';           // ton mot de passe

    // DSN (Data Source Name) pour PDO
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass);
        // Options de PDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // En cas d'erreur de connexion
        die("Erreur de connexion : " . $e->getMessage());
    }
}
