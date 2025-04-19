<?php
// Database connection details
$host = 'localhost';
$db   = 'vranderl_projet2';
$user = 'vranderl';
$pass = '';  // Empty if no password required

echo "<h1>Test de connexion à la base de données et import des définitions</h1>";

try {
    echo "<p>Tentative de connexion à la base de données...</p>";
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>Connexion réussie à la base de données!</p>";

    echo "<p>Vérification et création des tables...</p>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS joueurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(50) NOT NULL UNIQUE,
        pwd VARCHAR(255) NOT NULL,
        parties_jouees INT DEFAULT 0,
        parties_gagnees INT DEFAULT 0,
        score INT DEFAULT 0,
        derniere_connexion DATETIME
    )");
    echo "<p>Table 'joueurs' vérifiée/créée avec succès.</p>";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS definitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        langue VARCHAR(5) NOT NULL,
        source VARCHAR(100),
        mot VARCHAR(50) NOT NULL,
        definition TEXT NOT NULL,
        INDEX (langue),
        INDEX (mot)
    )");
    echo "<p>Table 'definitions' vérifiée/créée avec succès.</p>";
    
    $file_path = 'def.txt';
    if (!file_exists($file_path)) {
        echo "<p style='color:red'>Le fichier 'def.txt' n'a pas été trouvé!</p>";
        echo "<p>Veuillez uploader le fichier def.txt dans le même répertoire que ce script.</p>";
        die();
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM definitions");
    $existingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Nombre de définitions actuellement dans la base: $existingCount</p>";
    
    if ($existingCount > 0) {
        echo "<p style='color:orange'>Des définitions existent déjà. Voulez-vous réimporter?</p>";
        echo "<p>Si oui, videz d'abord la table avec cette commande SQL: TRUNCATE TABLE definitions;</p>";
        die();
    }

    $stmt = $pdo->prepare("INSERT INTO definitions (langue, source, mot, definition) VALUES (?, ?, ?, ?)");
    
    echo "<p>Importation des définitions depuis le fichier...</p>";
    $file = fopen($file_path, 'r');
    $count = 0;
    
    while (($line = fgets($file)) !== false) {
        $parts = explode("\t", $line);
        if (count($parts) >= 4) {
            $langue = trim($parts[0]);
            $source = trim($parts[1]);
            $mot = trim($parts[2]);
            $definition = trim($parts[3]);
            
            $stmt->execute([$langue, $source, $mot, $definition]);
            $count++;

            if ($count % 100 == 0) {
                echo "<p>$count définitions importées...</p>";
                ob_flush();
                flush();
            }
        }
    }
    
    fclose($file);
    echo "<p style='color:green'>Importation terminée! $count définitions importées avec succès.</p>";
    
} catch(PDOException $e) {
    echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<p><a href="index.php">Retourner à la page d'accueil</a></p>