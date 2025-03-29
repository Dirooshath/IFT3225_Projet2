<?php
header('Content-Type: application/json');  // Retourne du JSON
require_once 'db_connect.php';

// Récupère la route brute via ?path=xxx
$path = isset($_GET['path']) ? $_GET['path'] : '';
$segments = explode('/', $path);

// Ex: /gamers/add/john/pass => $segments = ["gamers", "add", "john", "pass"]
$method = $_SERVER['REQUEST_METHOD']; // GET, POST, PUT, DELETE, etc.

switch ($segments[0]) {
    case 'gamers':
        handleGamers($method, $segments);
        break;
    // case 'admin':
    //     ...
    //     break;
    // case 'word':
    //     ...
    //     break;
    default:
        echo json_encode(["error" => "Route inconnue"]);
        http_response_code(404);
}

function handleGamers($method, $segments) {
    // gamers/<joueur> => $segments = ["gamers", "<joueur>"]
    // gamers/add/<joueur>/<pwd> => $segments = ["gamers", "add", "<joueur>", "<pwd>"]
    if (count($segments) == 2 && $method == 'GET') {
        // GET /gamers/<joueur>
        $joueur = $segments[1];
        getGamer($joueur);
    }
    elseif (count($segments) == 4 && $segments[1] === 'add' && $method == 'POST') {
        // POST /gamers/add/<joueur>/<pwd>
        $joueur = $segments[2];
        $pwd = $segments[3];
        addGamer($joueur, $pwd);
    }
    elseif (count($segments) == 4 && $segments[1] === 'login' && $method == 'GET') {
        // GET /gamers/login/<joueur>/<pwd>
        $joueur = $segments[2];
        $pwd = $segments[3];
        loginGamer($joueur, $pwd);
    }
    elseif (count($segments) == 4 && $segments[1] === 'logout' && $method == 'GET') {
        // GET /gamers/logout/<joueur>/<pwd>
        $joueur = $segments[2];
        $pwd = $segments[3];
        logoutGamer($joueur, $pwd);
    }
    else {
        echo json_encode(["error" => "Route /gamers invalide"]);
        http_response_code(400);
    }
}

// -----------------------------------------------------------------------
// Fonctions de gestion "gamers"
function getGamer($login) {
    $pdo = dbConnect();
    $sql = "SELECT * FROM joueurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':login', $login);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode($row);
    } else {
        echo json_encode(["error" => "Joueur non trouvé"]);
        http_response_code(404);
    }
}

function addGamer($login, $pwd) {
    $pdo = dbConnect();

    // Vérifier si le login existe déjà
    $check = $pdo->prepare("SELECT id FROM joueurs WHERE login = :login");
    $check->execute([':login' => $login]);
    if ($check->fetch()) {
        echo json_encode(["error" => "Ce login existe déjà"]);
        http_response_code(409);
        return;
    }

    // Hasher le mot de passe (FORTEMENT RECOMMANDÉ)
    $hash = password_hash($pwd, PASSWORD_BCRYPT);

    $sql = "INSERT INTO joueurs (login, pwd, parties_jouees, parties_gagnees, score, derniere_connexion)
            VALUES (:login, :pwd, 0, 0, 0, NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':login', $login);
    $stmt->bindValue(':pwd', $hash);
    $stmt->execute();

    // Retourner l'id du nouveau joueur
    $newId = $pdo->lastInsertId();
    echo json_encode(["message" => "Joueur ajouté", "id" => $newId]);
}

function loginGamer($login, $pwd) {
    $pdo = dbConnect();
    $sql = "SELECT id, pwd FROM joueurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["error" => "Login inexistant"]);
        http_response_code(404);
        return;
    }

    // Vérifier le mot de passe hashé
    if (password_verify($pwd, $row['pwd'])) {
        // On peut simuler un "login" => par ex., mettre un champ dans la table "sessions" ou "joueurs"
        // Mettre à jour la date de dernière connexion
        $update = $pdo->prepare("UPDATE joueurs SET derniere_connexion = NOW() WHERE id = :id");
        $update->execute([':id' => $row['id']]);

        echo json_encode(["message" => "Connexion réussie", "id" => $row['id']]);
    } else {
        echo json_encode(["error" => "Mot de passe incorrect"]);
        http_response_code(401);
    }
}

function logoutGamer($login, $pwd) {
    $pdo = dbConnect();
    $sql = "SELECT id, pwd FROM joueurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($pwd, $row['pwd'])) {
        echo json_encode(["error" => "Login/mot de passe invalide"]);
        http_response_code(401);
        return;
    }

    // Ici, on simule une déconnexion => ex: on met est_connecte=0 dans la table "sessions" (à toi de définir la logique)
    // Pour le démo, on renvoie juste "logout OK"
    echo json_encode(["message" => "Déconnexion OK"]);
}
