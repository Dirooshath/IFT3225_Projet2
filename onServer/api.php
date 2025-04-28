<?php


ini_set('display_errors', 0);
error_reporting(E_ALL);


set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server error',
        'message' => $errstr,
        'details' => "$errfile:$errline"
    ]);
    exit;
});


register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Fatal error',
            'message' => $error['message'],
            'details' => "{$error['file']}:{$error['line']}"
        ]);
        exit;
    }
});


if (ob_get_length()) ob_clean();

require_once 'db-connect.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];


$path_parts = parse_url($request_uri);
$path = isset($path_parts['path']) ? $path_parts['path'] : '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_definition') {
    handleAddDefinition();
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score') {
    handleUpdateScore();
    exit;
}


if (isset($_GET['path'])) {
    
    $path_segments = explode('/', trim($_GET['path'], '/'));
    handleApiRequest($method, $path_segments);
} else {
    
    include 'index.html';
}


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


function outputJson($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


function handleGamers($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid gamers endpoint']);
        return;
    }
    
    
    if (count($segments) == 2 && $method === 'GET') {
        $joueur = $segments[1];
        getGamer($joueur);
    }
    
    elseif (count($segments) >= 4 && $segments[1] === 'add') {
        $joueur = $segments[2];
        $pwd = $segments[3];
        addGamer($joueur, $pwd);
    }
    
    elseif (count($segments) >= 4 && $segments[1] === 'login') {
        $joueur = $segments[2];
        $pwd = $segments[3];
        loginGamer($joueur, $pwd);
    }
    
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
    
    
    $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    
    if ($stmt->fetch()) {
        outputJson(['error' => 'User already exists'], 409);
        return;
    }
    
    
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    
    
    $stmt = $pdo->prepare("INSERT INTO joueurs (login, pwd, parties_jouees, parties_gagnees, score, derniere_connexion) 
                         VALUES (?, ?, 0, 0, 0, NOW())");
    $stmt->execute([$login, $hash]);
    
    
    $newId = $pdo->lastInsertId();
    
    
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_login'] = $login;
    
    outputJson(['id' => $newId, 'message' => 'User added successfully']);
}

function loginGamer($login, $pwd) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT id, pwd, score, is_admin FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    
    $user = $stmt->fetch();
    if (!$user) {
        outputJson(['error' => 'User not found'], 404);
        return;
    }
    
    if (password_verify($pwd, $user['pwd'])) {
        
        $updateStmt = $pdo->prepare("UPDATE joueurs SET derniere_connexion = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $login;
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        
        outputJson([
            'message' => 'Login successful',
            'score' => $user['score'],
            'is_admin' => (bool)$user['is_admin']
        ]);
    } else {
        outputJson(['error' => 'Invalid password'], 401);
    }
}

function logoutGamer($login, $pwd) {
    
    session_unset();
    session_destroy();
    
    outputJson(['message' => 'Logout successful']);
}


function handleAdmin($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid admin endpoint']);
        return;
    }
    
    
    
    if ($segments[1] !== 'top' && 
        (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
        outputJson(['error' => 'Unauthorized access. Admin privileges required.'], 403);
        return;
    }
    
    
    if ($segments[1] === 'users' && $method === 'GET') {
        getAllUsers();
    }
    elseif ($segments[1] === 'users' && isset($segments[2]) && $method === 'POST') {
        $userId = $segments[2];
        updateUser($userId, $_POST);
    }
    elseif ($segments[1] === 'definitions' && isset($segments[2]) && $method === 'POST') {
        $defId = $segments[2];
        updateDefinition($defId, $_POST);
    }
    elseif ($segments[1] === 'stats' && $method === 'GET') {
        getGameStats();
    }
    
    elseif ($segments[1] === 'top') {
        $nb = isset($segments[2]) ? (int)$segments[2] : 5;
        getTopScores($nb);
    }
    elseif (count($segments) >= 4 && $segments[1] === 'delete' && $segments[2] === 'joueur') {
        $joueur = $segments[3];
        deleteGamer($joueur);
    }
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
    
    
    $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if (!$user) {
        outputJson(['error' => 'User not found'], 404);
        return;
    }
    
    
    $stmt = $pdo->prepare("DELETE FROM joueurs WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    outputJson(['id' => $user['id'], 'message' => 'User deleted successfully']);
}

function deleteDefinition($defId) {
    $pdo = dbConnect();
    
    
    $stmt = $pdo->prepare("SELECT id FROM definitions WHERE id = ?");
    $stmt->execute([$defId]);
    
    if (!$stmt->fetch()) {
        outputJson(['error' => 'Definition not found'], 404);
        return;
    }
    
    
    $stmt = $pdo->prepare("DELETE FROM definitions WHERE id = ?");
    $stmt->execute([$defId]);
    
    outputJson(['id' => $defId, 'message' => 'Definition deleted successfully']);
}


function getAllUsers() {
    
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        outputJson(['error' => 'Unauthorized access'], 403);
        return;
    }
    
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        SELECT id, login, parties_jouees, parties_gagnees, score, 
               derniere_connexion, is_admin 
        FROM joueurs 
        ORDER BY score DESC
    ");
    $stmt->execute();
    
    $users = $stmt->fetchAll();
    outputJson($users);
}


function updateUser($userId, $data) {
    
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        outputJson(['error' => 'Unauthorized access'], 403);
        return;
    }
    
    
    if (!isset($data['parties_jouees']) || !isset($data['parties_gagnees']) || 
        !isset($data['score']) || !isset($data['is_admin'])) {
        outputJson(['error' => 'Missing required fields'], 400);
        return;
    }
    
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        UPDATE joueurs 
        SET parties_jouees = ?, 
            parties_gagnees = ?, 
            score = ?, 
            is_admin = ? 
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['parties_jouees'],
        $data['parties_gagnees'],
        $data['score'],
        $data['is_admin'] ? 1 : 0,
        $userId
    ]);
    
    outputJson(['message' => 'User updated successfully']);
}


function updateDefinition($defId, $data) {
    
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        outputJson(['error' => 'Unauthorized access'], 403);
        return;
    }
    
    
    if (!isset($data['word']) || !isset($data['definition']) || 
        !isset($data['language']) || !isset($data['source'])) {
        outputJson(['error' => 'Missing required fields'], 400);
        return;
    }
    
    $pdo = dbConnect();
    $stmt = $pdo->prepare("
        UPDATE definitions 
        SET mot = ?, 
            definition = ?, 
            langue = ?, 
            source = ? 
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['word'],
        $data['definition'],
        $data['language'],
        $data['source'],
        $defId
    ]);
    
    outputJson(['message' => 'Definition updated successfully']);
}


function getGameStats() {
    
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        outputJson(['error' => 'Unauthorized access'], 403);
        return;
    }
    
    $pdo = dbConnect();
    
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM joueurs");
    $totalPlayers = $stmt->fetch()['total'];
    
    
    $stmt = $pdo->query("SELECT SUM(parties_jouees) as total FROM joueurs");
    $totalGames = $stmt->fetch()['total'] ?: 0;
    
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM definitions");
    $totalDefinitions = $stmt->fetch()['total'];
    
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM definitions WHERE source != ?");
    $stmt->execute(['system']);
    $userDefinitions = $stmt->fetch()['total'];
    
    
    $stmt = $pdo->prepare("
        SELECT login, parties_jouees, derniere_connexion 
        FROM joueurs 
        ORDER BY parties_jouees DESC, derniere_connexion DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $activeUsers = $stmt->fetchAll();
    
    
    $stmt = $pdo->prepare("
        SELECT mot as word, langue as language, COUNT(*) as count
        FROM definitions
        GROUP BY mot, langue
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $popularWords = $stmt->fetchAll();
    
    outputJson([
        'totalPlayers' => $totalPlayers,
        'totalGames' => $totalGames,
        'totalDefinitions' => $totalDefinitions,
        'userDefinitions' => $userDefinitions,
        'activeUsers' => $activeUsers,
        'popularWords' => $popularWords
    ]);
}


function handleWord($method, $segments) {
    
    $nb = isset($segments[1]) ? (int)$segments[1] : 10;
    $from = isset($segments[2]) ? (int)$segments[2] : 1;
    
    if ($nb <= 0) $nb = 10;
    if ($from <= 0) $from = 1;
    
    $offset = $from - 1;
    
    $pdo = dbConnect();
    
    
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


function handleJeu($method, $segments) {
    if (count($segments) < 2) {
        outputJson(['error' => 'Invalid game endpoint']);
        return;
    }
    
    $gameType = $segments[1];
    
    if ($gameType === 'word') {
        
        $lg = isset($segments[2]) ? $segments[2] : 'en';
        $time = isset($segments[3]) ? (int)$segments[3] : 60;
        $hint = isset($segments[4]) ? (int)$segments[4] : 10;
        
        getWordGame($lg, $time, $hint);
    }
    elseif ($gameType === 'def') {
        
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
    
    
    $wordLength = strlen($word['word']);
    $stmt = $pdo->prepare("
        SELECT DISTINCT mot AS word
        FROM definitions
        WHERE langue = ? 
        AND LENGTH(mot) = ?
        LIMIT 100  
    ");
    $stmt->execute([$lg, $wordLength]);
    $allWords = array_column($stmt->fetchAll(), 'word');
    
    
    $targetWord = strtoupper($word['word']);
    $suggestions = [];
    
    foreach ($allWords as $suggestion) {
        
        if (strtoupper($suggestion) === $targetWord) {
            continue;
        }
        
        
        $sharedLetters = 0;
        $suggestionUpper = strtoupper($suggestion);
        
        for ($i = 0; $i < $wordLength; $i++) {
            if ($i < strlen($suggestionUpper) && $targetWord[$i] === $suggestionUpper[$i]) {
                $sharedLetters++;
            }
        }
        
        
        if ($sharedLetters >= 1) {
            $suggestions[] = $suggestion;
        }
    }
    
    
    if (count($suggestions) < 10) {
        
        foreach ($allWords as $word) {
            if (!in_array($word, $suggestions) && strtoupper($word) !== $targetWord) {
                $suggestions[] = $word;
                if (count($suggestions) >= 20) break;
            }
        }
    }
    
    
    $suggestions = array_slice($suggestions, 0, 20);
    
    
    outputJson([
        'word' => $word['word'],
        'definition' => $word['definition'],
        'wordLength' => strlen($word['word']),
        'initialScore' => strlen($word['word']) * 10,
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
    
    
    outputJson([
        'wordId' => $word['id'],
        'word' => $word['word'],
        'time' => $time
    ]);
}



function handleDump($method, $segments) {
    
    $step = isset($segments[1]) ? intval($segments[1]) : 10;
    $offset = isset($segments[2]) ? intval($segments[2]) : 0;
    
    
    if ($step <= 0) $step = 10;
    if ($offset < 0) $offset = 0;
    
    
    if (isset($_GET['start']) && isset($_GET['length'])) {
        $offset = intval($_GET['start']);
        $step = intval($_GET['length']);
    }
    
    $pdo = dbConnect();
    
    
    $searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';
    $language = isset($_GET['lang']) ? trim($_GET['lang']) : '';
    
    
    $sortColumnIndex = isset($_GET['sortColumn']) ? intval($_GET['sortColumn']) : 0;
    $sortDirection = isset($_GET['sortDir']) ? strtoupper($_GET['sortDir']) : 'ASC';
    
    
    if ($sortDirection != 'ASC' && $sortDirection != 'DESC') {
        $sortDirection = 'ASC';
    }
    
    
    $columns = [
        0 => 'id',
        1 => 'langue',
        2 => 'mot',
        3 => 'definition'
    ];
    
    
    $sortColumn = isset($columns[$sortColumnIndex]) ? $columns[$sortColumnIndex] : 'id';
    
    
    $sql = "SELECT id, langue AS language, mot AS word, definition, source FROM definitions WHERE 1=1";
    $countSql = "SELECT COUNT(*) as total FROM definitions WHERE 1=1";
    $params = [];
    
    
    if (!empty($searchTerm)) {
        $sql .= " AND (mot LIKE ? OR definition LIKE ?)";
        $countSql .= " AND (mot LIKE ? OR definition LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    
    if (!empty($language)) {
        $sql .= " AND langue = ?";
        $countSql .= " AND langue = ?";
        $params[] = $language;
    }
    
    
    $sql .= " ORDER BY $sortColumn $sortDirection LIMIT ?, ?";
    
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    
    $stmt = $pdo->prepare($sql);
    
    
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    
    
    $nextIndex = count($params) + 1;
    $stmt->bindValue($nextIndex, $offset, PDO::PARAM_INT);
    $stmt->bindValue($nextIndex + 1, $step, PDO::PARAM_INT);
    
    $stmt->execute();
    $definitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    outputJson([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => $totalCount,
        'recordsFiltered' => $totalCount,
        'data' => $definitions
    ]);
}


function handleDoc() {
    
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
            'GET /admin/delete/def/{id}' => 'Delete a definition',
            'GET /admin/users' => '[Admin] Get all users',
            'POST /admin/users/{id}' => '[Admin] Update a user',
            'POST /admin/definitions/{id}' => '[Admin] Update a definition',
            'GET /admin/stats' => '[Admin] Get game statistics'
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


function handleAddDefinition() {
    
    if (!isset($_POST['word']) || !isset($_POST['definition']) || !isset($_POST['language'])) {
        outputJson(['error' => 'Missing required parameters'], 400);
        return;
    }
    
    
    $word = $_POST['word'];
    $definition = $_POST['definition'];
    $language = $_POST['language'];
    $user = $_POST['user'] ?? 'Guest';
    
    
    if (strlen($definition) < 5) {
        outputJson(['error' => 'Definition must be at least 5 characters long'], 400);
        return;
    }
    
    if (strlen($definition) > 200) {
        outputJson(['error' => 'Definition must be no more than 200 characters long'], 400);
        return;
    }
    
    
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
    
    
    $stmt = $pdo->prepare("
        INSERT INTO definitions (mot, definition, langue, source) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$word, $definition, $language, $user]);
    
    
    $newId = $pdo->lastInsertId();
    
    
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


function handleUpdateScore() {
    
    if (!isset($_POST['username']) || !isset($_POST['score'])) {
        outputJson(['error' => 'Missing required parameters'], 400);
        return;
    }
    
    
    $username = $_POST['username'];
    $score = (int)$_POST['score'];
    
    
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