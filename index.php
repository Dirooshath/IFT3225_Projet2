<?php
header('Content-Type: application/json');  // Retourne du JSON
require_once 'db_connect.php'; // <-- contient la fonction dbConnect()

// Récupère la route brute via ?path=xxx
$path = isset($_GET['path']) ? $_GET['path'] : '';
$segments = explode('/', $path);

// Ex: /gamers/add/john/pass => $segments = ["gamers", "add", "john", "pass"]
$method = $_SERVER['REQUEST_METHOD']; // GET, POST, PUT, DELETE, etc.

switch ($segments[0]) {
    case 'gamers':
        handleGamers($method, $segments);
        break;

    case 'admin':
        handleAdmin($method, $segments);
        break;

    // ************* NOUVEL AJOUT : route "word" *************
    case 'word':
        handleWord($method, $segments);
        break;
    // ************* FIN AJOUT ********************************

    default:
        echo json_encode(["error" => "Route inconnue"]);
        http_response_code(404);
        break;
}

/* -----------------------------------------------------------------------
   Gestion des joueurs (/gamers)
----------------------------------------------------------------------- */
function handleGamers($method, $segments) {
    if (count($segments) == 2 && $method == 'GET') {
        // GET /gamers/<joueur>
        $joueur = $segments[1];
        getGamer($joueur);
    }
    elseif (count($segments) == 4 && $segments[1] === 'add' && $method == 'POST') {
        // POST /gamers/add/<joueur>/<pwd>
        $joueur = $segments[2];
        $pwd    = $segments[3];
        addGamer($joueur, $pwd);
    }
    elseif (count($segments) == 4 && $segments[1] === 'login' && $method == 'GET') {
        // GET /gamers/login/<joueur>/<pwd>
        $joueur = $segments[2];
        $pwd    = $segments[3];
        loginGamer($joueur, $pwd);
    }
    elseif (count($segments) == 4 && $segments[1] === 'logout' && $method == 'GET') {
        // GET /gamers/logout/<joueur>/<pwd>
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
        echo json_encode(["error" => "Joueur non trouve"]);
        http_response_code(404);
    }
}

function addGamer($login, $pwd) {
    $pdo = dbConnect();

    // Vérifier si le login existe déjà
    $check = $pdo->prepare("SELECT id FROM joueurs WHERE login = :login");
    $check->execute([':login' => $login]);
    if ($check->fetch()) {
        echo json_encode(["error" => "Ce login existe deja"]);
        http_response_code(409);
        return;
    }

    // Hasher le mot de passe
    $hash = password_hash($pwd, PASSWORD_BCRYPT);

    $sql = "INSERT INTO joueurs (login, pwd, parties_jouees, parties_gagnees, score, derniere_connexion)
            VALUES (:login, :pwd, 0, 0, 0, NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':login', $login);
    $stmt->bindValue(':pwd', $hash);
    $stmt->execute();

    $newId = $pdo->lastInsertId();
    echo json_encode(["message" => "Joueur ajoute", "id" => $newId]);
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

    if (password_verify($pwd, $row['pwd'])) {
        // Mettre à jour la date de dernière connexion
        $update = $pdo->prepare("UPDATE joueurs SET derniere_connexion = NOW() WHERE id = :id");
        $update->execute([':id' => $row['id']]);

        echo json_encode(["message" => "Connexion reussie", "id" => $row['id']]);
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

    // Simule la déconnexion
    echo json_encode(["message" => "Deconnexion OK"]);
}

/* -----------------------------------------------------------------------
   Consultation admin (/admin)
----------------------------------------------------------------------- */
function handleAdmin($method, $segments) {
    // admin/top[/<nb>]
    if (count($segments) >= 2 && $segments[1] === 'top' && $method == 'GET') {
        $nb = 5; // Par défaut
        if (isset($segments[2])) {
            $nb = (int) $segments[2];
        }
        getTopScores($nb);
    }

    // admin/delete/joueur/<joueur>
    elseif (count($segments) == 4 && $segments[1] === 'delete' && $segments[2] === 'joueur' && $method == 'GET') {
        $login = $segments[3];
        deleteGamer($login);
    }

    // admin/delete/def/<id>
    elseif (count($segments) == 4 && $segments[1] === 'delete' && $segments[2] === 'def' && $method == 'GET') {
        $defId = $segments[3];
        deleteDefinition($defId);
    }

    else {
        echo json_encode(["error" => "Route /admin invalide"]);
        http_response_code(400);
    }
}

function getTopScores($nb) {
    $pdo = dbConnect();
    $sql = "SELECT login, score FROM joueurs ORDER BY score DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $nb, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
}

function deleteGamer($login) {
    $pdo = dbConnect();

    // Vérifier si le joueur existe
    $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE login = :login");
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["error" => "Joueur introuvable"]);
        http_response_code(404);
        return;
    }

    $joueurId = $row['id'];

    // Supprimer le joueur
    $del = $pdo->prepare("DELETE FROM joueurs WHERE id = :id");
    $del->execute([':id' => $joueurId]);

    echo json_encode(["message" => "Joueur supprime", "id" => $joueurId]);
}

function deleteDefinition($defId) {
    $pdo = dbConnect();

    // Vérifier si la définition existe
    $check = $pdo->prepare("SELECT id FROM definitions WHERE id = :id");
    $check->execute([':id' => $defId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["error" => "Definition introuvable"]);
        http_response_code(404);
        return;
    }

    // Supprimer la définition
    $del = $pdo->prepare("DELETE FROM definitions WHERE id = :id");
    $del->execute([':id' => $defId]);

    echo json_encode(["message" => "Definition supprimee", "id" => $defId]);
}

/* -----------------------------------------------------------------------
   NOUVELLE SECTION : gestion de /word/<nb>
----------------------------------------------------------------------- */
function handleWord($method, $segments) {
    // On attend : GET /word/<nb>
    if ($method === 'GET' && count($segments) === 2) {
        $nb = (int)$segments[1];
        if ($nb <= 0) {
            $nb = 10; // valeur par défaut si <nb> non valide
        }

        // Connexion à la BD
        $pdo = dbConnect();

        // On récupère <nb> lignes dans la table "definitions"
        // Adaptez le nom des champs selon votre schéma :
        //   id (PK), word (le mot), def (la définition)
        $sql = "SELECT id, mot, definition
                FROM definitions
                ORDER BY id
                LIMIT :limitN";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limitN', $nb, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 1. Rassembler le résultat final sous forme d'un tableau
        //    où chaque enregistrement a la forme :
        //    { "word": "...", "id": "...", "def": ["Définition 1", "Définition 2", ...] }

        //    Ici, si votre BD ne stocke qu'UNE seule définition par ligne,
        //    on place la valeur `def` dans un petit tableau, ex: ["..."].
        //    En cas de multi-définitions, on ferait un groupement par word.

        $result = [];
        foreach ($rows as $row) {
            // Placer la définition dans un petit tableau
            $definitionArray = [$row['definition']];

            // Construire l'objet voulu
            $item = [
                "mot" => $row['mot'],
                "id"   => $row['id'],
                "definition"  => $definitionArray
            ];

            $result[] = $item;
        }

        // 2. Retourner ce tableau en JSON (niveau racine)
        echo json_encode($result, JSON_PRETTY_PRINT);
        return;
    }

    // Sinon, on ne reconnaît pas la route /word
    echo json_encode(["error" => "Route /word invalide"]);
    http_response_code(400);
}
