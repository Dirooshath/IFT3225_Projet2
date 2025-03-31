<?php
header('Content-Type: application/json');  // On retourne des réponses en JSON
require_once 'db_connect.php';

// 1) On récupère la route via ?path=xxx (exemple : ?path=gamers/add/john/pass)
$path = isset($_GET['path']) ? $_GET['path'] : '';
$segments = explode('/', $path);

// 2) On récupère la méthode HTTP (GET, POST, etc.)
$method = $_SERVER['REQUEST_METHOD'];

// 3) On dirige selon le premier segment
switch ($segments[0]) {
    case 'gamers':
        handleGamers($method, $segments);
        break;

    case 'admin':
        handleAdmin($method, $segments);
        break;

    default:
        // Si aucun case ne correspond
        echo json_encode(["error" => "Route inconnue"]);
        http_response_code(404);
        break;
}

/* -------------------------------------------------------------
   GESTION DES ROUTES /gamers
   ------------------------------------------------------------- */
function handleGamers($method, $segments) {
    //  /gamers/<joueur> => $segments = ["gamers", "<joueur>"]
    //  /gamers/add/<joueur>/<pwd> => $segments = ["gamers", "add", "<joueur>", "<pwd>"]

    // GET /gamers/<joueur>
    if (count($segments) == 2 && $method == 'GET') {
        $joueur = $segments[1];
        getGamer($joueur);
    }

    // POST /gamers/add/<joueur>/<pwd>
    elseif (count($segments) == 4 && $segments[1] === 'add' && $method == 'POST') {
        $joueur = $segments[2];
        $pwd    = $segments[3];
        addGamer($joueur, $pwd);
    }

    // GET /gamers/login/<joueur>/<pwd>
    elseif (count($segments) == 4 && $segments[1] === 'login' && $method == 'GET') {
        $joueur = $segments[2];
        $pwd    = $segments[3];
        loginGamer($joueur, $pwd);
    }

    // GET /gamers/logout/<joueur>/<pwd>
    elseif (count($segments) == 4 && $segments[1] === 'logout' && $method == 'GET') {
        $joueur = $segments[2];
        $pwd    = $segments[3];
        logoutGamer($joueur, $pwd);
    }

    else {
        echo json_encode(["error" => "Route /gamers invalide"]);
        http_response_code(400);
    }
}

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

    // Hachage du mot de passe
    $hash = password_hash($pwd, PASSWORD_BCRYPT);

    // Insertion en base
    $sql = "INSERT INTO joueurs (login, pwd, parties_jouees, parties_gagnees, score, derniere_connexion)
            VALUES (:login, :pwd, 0, 0, 0, NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':login', $login);
    $stmt->bindValue(':pwd', $hash);
    $stmt->execute();

    // Retourne l'id du nouveau joueur
    $newId = $pdo->lastInsertId();
    echo json_encode(["message" => "Joueur ajouté", "id" => $newId]);
}

function loginGamer($login, $pwd) {
    $pdo = dbConnect();

    // On va chercher l'utilisateur
    $sql = "SELECT id, pwd FROM joueurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["error" => "Login inexistant"]);
        http_response_code(404);
        return;
    }

    // Vérification du mot de passe
    if (password_verify($pwd, $row['pwd'])) {
        // On met à jour la date de dernière connexion
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

    // On vérifie que le joueur existe et que le mdp correspond
    $sql = "SELECT id, pwd FROM joueurs WHERE login = :login";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($pwd, $row['pwd'])) {
        echo json_encode(["error" => "Login/mot de passe invalide"]);
        http_response_code(401);
        return;
    }

    // Ici on fait semblant de déconnecter l'utilisateur
    echo json_encode(["message" => "Déconnexion OK"]);
}

/* -------------------------------------------------------------
   GESTION DES ROUTES /admin
   ------------------------------------------------------------- */
function handleAdmin($method, $segments) {
    // /admin/top[/<nb>] => Ex: /admin/top/5
    if (count($segments) >= 2 && $segments[1] === 'top' && $method == 'GET') {
        // Par défaut on veut le top 5, sinon on prend un nb
        $nb = 5;
        if (isset($segments[2])) {
            $nb = (int) $segments[2];
        }
        getTopScores($nb);
    }
    else {
        echo json_encode(["error" => "Route /admin invalide"]);
        http_response_code(400);
    }
}

function getTopScores($nb) {
    $pdo = dbConnect();

    // On récupère les joueurs avec le plus haut score
    $sql = "SELECT login, score FROM joueurs ORDER BY score DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    // On doit préciser PDO::PARAM_INT pour le LIMIT
    $stmt->bindValue(':limit', $nb, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
}
