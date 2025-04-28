<?php

function dbConnect() {
    
    $logFile = 'db_connection.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection attempt started\n", FILE_APPEND);
    
    
    $host = 'localhost';         
    $dbname = 'vranderl_projet2';                
    $user = 'vranderl';                  
    $pass = '';                  
    
    
    if (empty($dbname)) {
        $dbname = 'vranderl_projet2';  
        file_put_contents($logFile, "WARNING: Using default database name: $dbname\n", FILE_APPEND);
    }
    if (empty($user)) {
        $user = 'vranderl';  
        file_put_contents($logFile, "WARNING: Using default username: $user\n", FILE_APPEND);
    }
    
    file_put_contents($logFile, "Connection info: host=$host, dbname=$dbname, user=$user\n", FILE_APPEND);
    
    try {
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection successful\n", FILE_APPEND);
        return $pdo;
    } catch (PDOException $e) {
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
        
        
        if (strpos($_SERVER['REQUEST_URI'], 'api') !== false) {
            
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Database connection failed',
                'message' => $e->getMessage(),
                'details' => 'Check db_connection.log for more information'
            ]);
        } else {
            
            echo "<h1>Database Error</h1>";
            echo "<p>Failed to connect to the database: " . $e->getMessage() . "</p>";
            echo "<p>Please make sure your database connection details are correct in db_connect.php</p>";
            echo "<p>Check db_connection.log for more detailed information.</p>";
        }
        exit;
    }
}