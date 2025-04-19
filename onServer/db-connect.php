<?php
/**
 * Database connection function
 * Returns a PDO connection to the MySQL database
 */
function dbConnect() {
    // Create a log file for database connection issues
    $logFile = 'db_connection.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection attempt started\n", FILE_APPEND);
    
    // Database connection details
    $host = 'localhost';         // MySQL server (locally on the DIRO server)
    $dbname = 'vranderl_projet2';                // Your database name (e.g., yourusername_projet2)
    $user = 'vranderl';                  // Your DIRO username
    $pass = '';                  // Your MySQL password (often empty on DIRO)
    
    // Fill in your database details - IMPORTANT: You must fill these in!
    if (empty($dbname)) {
        $dbname = 'vranderl_projet2';  // Replace with your actual database name
        file_put_contents($logFile, "WARNING: Using default database name: $dbname\n", FILE_APPEND);
    }
    if (empty($user)) {
        $user = 'vranderl';  // Replace with your DIRO username
        file_put_contents($logFile, "WARNING: Using default username: $user\n", FILE_APPEND);
    }
    
    file_put_contents($logFile, "Connection info: host=$host, dbname=$dbname, user=$user\n", FILE_APPEND);
    
    try {
        // Create a new PDO instance
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        
        // Set error mode to exception
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set default fetch mode to associative array
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection successful\n", FILE_APPEND);
        return $pdo;
    } catch (PDOException $e) {
        // Log detailed error information
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Connection FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
        
        // Handle connection error
        if (strpos($_SERVER['REQUEST_URI'], 'api') !== false) {
            // API error response (JSON)
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Database connection failed',
                'message' => $e->getMessage(),
                'details' => 'Check db_connection.log for more information'
            ]);
        } else {
            // HTML error response
            echo "<h1>Database Error</h1>";
            echo "<p>Failed to connect to the database: " . $e->getMessage() . "</p>";
            echo "<p>Please make sure your database connection details are correct in db_connect.php</p>";
            echo "<p>Check db_connection.log for more detailed information.</p>";
        }
        exit;
    }
}