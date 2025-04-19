<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db-connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get request information
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse the URL
$path_parts = parse_url($request_uri);
$path = isset($path_parts['path']) ? $path_parts['path'] : '';

// Handle POST requests for adding definitions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_definition') {
    handleAddDefinition();
    exit;
}

// Handle POST requests for updating scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score') {
    handleUpdateScore();
    exit;
}

// Check if this is an API request (using query parameter for path)
if (isset($_GET['path'])) {
    // API request - get the path from the query parameter
    $path_segments = explode('/', trim($_GET['path'], '/'));
    handleApiRequest($method, $path_segments);
} else {
    // Regular request - serve the SPA
    include 'index.html';
}

/**
 * Handle API requests
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array $segments Path segments
 */
function handleApiRequest($method, $segments) {
    if (empty($segments)) {
        outputJson(['error' => 'No API path specified']);
        return;
    }
    
    $resource = $segments[0];
    
    switch ($resource) {
        case 'gamers':
            handleGamers($method, $segments);
            break;
            
        case 'admin':
            handleAdmin($method, $segments);
            break;
            
        case 'word':
            handleWord($method, $segments);
            break;
            
        case 'jeu':
            handleJeu($method, $segments);
            break;
            
        case 'dump':
            handleDump($method, $segments);
            break;
            
        case 'doc':
            handleDoc();
            break;
            
        default:
            outputJson(['error' => 'Unknown API resource'], 404);
            break;
    }
}

/**
 * Output JSON response
 * @param mixed $data Data to encode as JSON
 * @param int $status HTTP status code
 */
function outputJson($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/* -----------------------------------------------------------------------
   Gamer Management Functions (API Endpoints)
----------------------------------------------------------------------- */
function handleGamers($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid gamers endpoint']);
        return;
    }
    
    // /gamers/<joueur>
    if (count($segments) == 2 && $method === 'GET') {
        $joueur = $segments[1];
        getGamer($joueur);
    }
    // /gamers/add/<joueur>/<pwd>
    elseif (count($segments) >= 4 && $segments[1] === 'add') {
        $joueur = $segments[2];
        $pwd = $segments[3];
        addGamer($joueur, $pwd);
    }
    // /gamers/login/<joueur>/<pwd>
    elseif (count($segments) >= 4 && $segments[1] === 'login') {
        $joueur = $segments[2];
        $pwd = $segments[3];
        loginGamer($joueur, $pwd);
    }
    // /gamers/logout/<joueur>/<pwd>
    elseif (count($segments) >= 4 && $segments[1] === 'logout') {
        $joueur = $segments[2];
        $pwd = $segments[3];
        logoutGamer($joueur, $pwd);
    }
    else {
        outputJson(['error' => 'Invalid gamers endpoint or method'], 400);
    }
}

function getGamer($login) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT login, parties_jouees, parties_gagnees, score, derniere_connexion 
                         FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    
    $gamer = $stmt->fetch();
    if ($gamer) {
        outputJson($gamer);
    } else {
        outputJson(['error' => 'Gamer not found'], 404);
    }
}

function addGamer($login, $pwd) {
    $pdo = dbConnect();
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    
    if ($stmt->fetch()) {
        outputJson(['error' => 'User already exists'], 409);
        return;
    }
    
    // Hash password
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO joueurs (login, pwd, parties_jouees, parties_gagnees, score, derniere_connexion) 
                         VALUES (?, ?, 0, 0, 0, NOW())");
    $stmt->execute([$login, $hash]);
    
    // Get the new user ID
    $newId = $pdo->lastInsertId();
    
    // Start session for new user
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_login'] = $login;
    
    outputJson(['id' => $newId, 'message' => 'User added successfully']);
}

function loginGamer($login, $pwd) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT id, pwd, score FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    
    $user = $stmt->fetch();
    if (!$user) {
        outputJson(['error' => 'User not found'], 404);
        return;
    }
    
    if (password_verify($pwd, $user['pwd'])) {
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE joueurs SET derniere_connexion = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $login;
        
        outputJson([
            'message' => 'Login successful',
            'score' => $user['score']
        ]);
    } else {
        outputJson(['error' => 'Invalid password'], 401);
    }
}

function logoutGamer($login, $pwd) {
    // Clear session
    session_unset();
    session_destroy();
    
    outputJson(['message' => 'Logout successful']);
}

/* -----------------------------------------------------------------------
   Admin Functions (API Endpoints)
----------------------------------------------------------------------- */
function handleAdmin($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid admin endpoint']);
        return;
    }
    
    // /admin/top[/<nb>]
    if ($segments[1] === 'top') {
        $nb = isset($segments[2]) ? (int)$segments[2] : 5;
        getTopScores($nb);
    }
    // /admin/delete/joueur/<joueur>
    elseif (count($segments) >= 4 && $segments[1] === 'delete' && $segments[2] === 'joueur') {
        $joueur = $segments[3];
        deleteGamer($joueur);
    }
    // /admin/delete/def/<id>
    elseif (count($segments) >= 4 && $segments[1] === 'delete' && $segments[2] === 'def') {
        $defId = $segments[3];
        deleteDefinition($defId);
    }
    else {
        outputJson(['error' => 'Invalid admin endpoint or method'], 400);
    }
}

function getTopScores($nb) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT login, score FROM joueurs ORDER BY score DESC LIMIT ?");
    $stmt->bindValue(1, $nb, PDO::PARAM_INT);
    $stmt->execute();
    
    $topPlayers = $stmt->fetchAll();
    outputJson($topPlayers);
}

function deleteGamer($login) {
    $pdo = dbConnect();
    
    // Get user ID first
    $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if (!$user) {
        outputJson(['error' => 'User not found'], 404);
        return;
    }
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM joueurs WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    outputJson(['id' => $user['id'], 'message' => 'User deleted successfully']);
}

function deleteDefinition($defId) {
    $pdo = dbConnect();
    
    // Check if definition exists
    $stmt = $pdo->prepare("SELECT id FROM definitions WHERE id = ?");
    $stmt->execute([$defId]);
    
    if (!$stmt->fetch()) {
        outputJson(['error' => 'Definition not found'], 404);
        return;
    }
    
    // Delete definition
    $stmt = $pdo->prepare("DELETE FROM definitions WHERE id = ?");
    $stmt->execute([$defId]);
    
    outputJson(['id' => $defId, 'message' => 'Definition deleted successfully']);
}

/* -----------------------------------------------------------------------
   Word Functions (API Endpoints)
----------------------------------------------------------------------- */
function handleWord($method, $segments) {
    // /word[/<nb>[/<from>]]
    $nb = isset($segments[1]) ? (int)$segments[1] : 10;
    $from = isset($segments[2]) ? (int)$segments[2] : 1;
    
    if ($nb <= 0) $nb = 10;
    if ($from <= 0) $from = 1;
    
    $offset = $from - 1;
    
    $pdo = dbConnect();
    
    // Get distinct words with definitions
    $stmt = $pdo->prepare("
        SELECT id, mot AS word, definition
        FROM definitions
        ORDER BY id
        LIMIT ?, ?
    ");
    $stmt->bindValue(1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(2, $nb, PDO::PARAM_INT);
    $stmt->execute();
    
    $rows = $stmt->fetchAll();
    
    // Organize data by word
    $words = [];
    foreach ($rows as $row) {
        $wordKey = strtoupper($row['word']);
        
        if (!isset($words[$wordKey])) {
            $words[$wordKey] = [
                'word' => $row['word'],
                'id' => $row['id'],
                'def' => []
            ];
        }
        
        $words[$wordKey]['def'][] = $row['definition'];
    }
    
    outputJson(array_values($words));
}

/* -----------------------------------------------------------------------
   Game Functions (API Endpoints)
----------------------------------------------------------------------- */
function handleJeu($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid game endpoint']);
        return;
    }
    
    $gameType = $segments[1];
    
    if ($gameType === 'word') {
        // /jeu/word[/<lg>[/<time>[/<hint>]]]
        $lg = isset($segments[2]) ? $segments[2] : 'en';
        $time = isset($segments[3]) ? (int)$segments[3] : 60;
        $hint = isset($segments[4]) ? (int)$segments[4] : 10;
        
        getWordGame($lg, $time, $hint);
    }
    elseif ($gameType === 'def') {
        // /jeu/def[/<lg>[/<time>]]
        $lg = isset($segments[2]) ? $segments[2] : 'en';
        $time = isset($segments[3]) ? (int)$segments[3] : 60;
        
        getDefGame($lg, $time);
    }
    else {
        outputJson(['error' => 'Invalid game type'], 400);
    }
}

function getWordGame($lg, $time, $hint) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        SELECT id, mot AS word, definition
        FROM definitions
        WHERE langue = ?
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$lg]);
    
    $word = $stmt->fetch();
    if (!$word) {
        outputJson(['error' => 'No words found for language: ' . $lg], 404);
        return;
    }
    
    // Get possible suggestions for the word
    $wordLength = mb_strlen($word['word']);
    $stmt = $pdo->prepare("
        SELECT DISTINCT mot AS word
        FROM definitions
        WHERE langue = ? 
        AND LENGTH(mot) = ?
        LIMIT 10
    ");
    $stmt->execute([$lg, $wordLength]);
    $suggestions = array_column($stmt->fetchAll(), 'word');
    
    // Return enhanced game data
    outputJson([
        'word' => $word['word'],
        'definition' => $word['definition'],
        'wordLength' => mb_strlen($word['word']),
        'initialScore' => mb_strlen($word['word']) * 10,
        'time' => $time,
        'hintInterval' => $hint,
        'suggestions' => $suggestions
    ]);
}

function getDefGame($lg, $time) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        SELECT id, mot AS word
        FROM definitions
        WHERE langue = ?
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$lg]);
    
    $word = $stmt->fetch();
    if (!$word) {
        outputJson(['error' => 'No words found for language: ' . $lg], 404);
        return;
    }
    
    // Return game data
    outputJson([
        'wordId' => $word['id'],
        'word' => $word['word'],
        'time' => $time
    ]);
}

/* -----------------------------------------------------------------------
   Definition Functions (API Endpoints)
----------------------------------------------------------------------- */
function handleDump($method, $segments) {
    // /dump/<step>[/<offset>]
    $step = isset($segments[1]) ? (int)$segments[1] : 10;
    $offset = isset($segments[2]) ? (int)$segments[2] : 0;
    
    if ($step <= 0) $step = 10;
    if ($offset < 0) $offset = 0;
    
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        SELECT id, langue AS language, mot AS word, definition
        FROM definitions
        ORDER BY id
        LIMIT :offset, :limit
    ");
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $step, PDO::PARAM_INT);
    $stmt->execute();
    
    $definitions = $stmt->fetchAll();
    outputJson($definitions);
}

/* -----------------------------------------------------------------------
   Documentation Function
----------------------------------------------------------------------- */
function handleDoc() {
    // Output API documentation in JSON format
    $endpoints = [
        'gamers' => [
            'GET /gamers/{joueur}' => 'Get information about a player',
            'GET /gamers/add/{joueur}/{pwd}' => 'Add a new player',
            'GET /gamers/login/{joueur}/{pwd}' => 'Login a player',
            'GET /gamers/logout/{joueur}/{pwd}' => 'Logout a player'
        ],
        'admin' => [
            'GET /admin/top[/{nb}]' => 'Get top {nb} players by score',
            'GET /admin/delete/joueur/{joueur}' => 'Delete a player',
            'GET /admin/delete/def/{id}' => 'Delete a definition'
        ],
        'word' => [
            'GET /word[/{nb}[/{from}]]' => 'Get {nb} words with definitions starting from position {from}'
        ],
        'game' => [
            'GET /jeu/word[/{lg}[/{time}[/{hint}]]]' => 'Get word game data',
            'GET /jeu/def[/{lg}[/{time}]]' => 'Get definition game data'
        ],
        'definitions' => [
            'GET /dump/{step}' => 'Get definitions (limited to {step} rows)',
            'POST action=add_definition' => 'Add a new definition'
        ]
    ];
    
    outputJson(['endpoints' => $endpoints]);
}

/**
 * Handle adding a new definition
 */
function handleAddDefinition() {
    // Check if required parameters are provided
    if (!isset($_POST['word']) || !isset($_POST['definition']) || !isset($_POST['language'])) {
        outputJson(['error' => 'Missing required parameters'], 400);
        return;
    }
    
    // Get parameters
    $word = $_POST['word'];
    $definition = $_POST['definition'];
    $language = $_POST['language'];
    $user = $_POST['user'] ?? 'Guest';
    
    // Validate definition
    if (strlen($definition) < 5) {
        outputJson(['error' => 'Definition must be at least 5 characters long'], 400);
        return;
    }
    
    if (strlen($definition) > 200) {
        outputJson(['error' => 'Definition must be no more than 200 characters long'], 400);
        return;
    }
    
    // Check if definition already exists for this word
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        SELECT id FROM definitions 
        WHERE mot = ? AND definition = ?
    ");
    $stmt->execute([$word, $definition]);
    
    if ($stmt->fetch()) {
        outputJson(['error' => 'This definition already exists for this word'], 409);
        return;
    }
    
    // Insert the new definition
    $stmt = $pdo->prepare("
        INSERT INTO definitions (mot, definition, langue, source) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$word, $definition, $language, $user]);
    
    // Get the inserted ID
    $newId = $pdo->lastInsertId();
    
    // If a user is logged in, update their score
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("
            UPDATE joueurs SET score = score + 5 WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    outputJson([
        'success' => true,
        'id' => $newId,
        'message' => 'Definition added successfully'
    ]);
}

/**
 * Handle updating a player's score
 */
function handleUpdateScore() {
    // Check if required parameters are provided
    if (!isset($_POST['username']) || !isset($_POST['score'])) {
        outputJson(['error' => 'Missing required parameters'], 400);
        return;
    }
    
    // Get parameters
    $username = $_POST['username'];
    $score = (int)$_POST['score'];
    
    // Update score in database
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        UPDATE joueurs 
        SET score = score + ?, 
            parties_jouees = parties_jouees + 1,
            parties_gagnees = parties_gagnees + 1
        WHERE login = ?
    ");
    $stmt->execute([$score, $username]);
    
    outputJson([
        'success' => true,
        'message' => 'Score updated successfully'
    ]);
}