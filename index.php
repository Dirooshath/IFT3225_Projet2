<?php
//header('Content-Type: application/json');  // Retourne du JSON
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
    if ($method === 'GET' && count($segments) === 2) {
        $nb = (int)$segments[1];
        if ($nb <= 0) {
            $nb = 10; // valeur par défaut si <nb> non valide
        }

        $pdo = dbConnect();
        $sql = "SELECT id, mot, definition
                FROM definitions
                ORDER BY id
                LIMIT :limitN";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limitN', $nb, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $definitionArray = [$row['definition']];
            $item = [
                "mot"        => $row['mot'],
                "id"         => $row['id'],
                "definition" => $definitionArray
            ];
            $result[] = $item;
        }

        echo json_encode($result, JSON_PRETTY_PRINT);
        return;
    }

    echo json_encode(["error" => "Route /word invalide"]);
    http_response_code(400);
}

// --------------------------------------------------------------------------
// Récupère le dernier joueur connecté
function getLastConnectedGamer() {
    $pdo = dbConnect();
    $sql = "SELECT * FROM joueurs
            ORDER BY derniere_connexion DESC
            LIMIT 1";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row; 
}

// --------------------------------------------------------------------------
// /jeu/...
function handleJeu($method, $segments) {
    if ($method !== 'GET') {
        echo "Méthode invalide pour /jeu";
        http_response_code(405);
        return;
    }

    if (count($segments) < 2) {
        echo "Route /jeu invalide";
        http_response_code(400);
        return;
    }

    $sub = $segments[1]; // "word" ou "def"
    if ($sub === 'word') {
        $lg   = isset($segments[2]) ? $segments[2] : 'en';
        $time = isset($segments[3]) ? (int)$segments[3] : 60;
        $hint = isset($segments[4]) ? (int)$segments[4] : 10;

        showJeuWordPage($lg, $time, $hint);

    } elseif ($sub === 'def') {
        $lg   = isset($segments[2]) ? $segments[2] : 'en';
        $time = isset($segments[3]) ? (int)$segments[3] : 60;

        showJeuDefPage($lg, $time);
    } else {
        echo "Route /jeu invalide (word ou def)";
        http_response_code(400);
    }
}

// --------------------------------------------------------------------------
// showJeuWordPage: le jeu “Trouve le mot”
// --------------------------------------------------------------------------
function showJeuWordPage($langue, $time, $hint) {
    // Récupère le dernier joueur connecté
    $lastGamer = getLastConnectedGamer();
    if (!$lastGamer) {
        echo "<h3>Aucun joueur connecté récemment. Veuillez d'abord vous connecter.</h3>";
        return;
    }

    $playerName  = $lastGamer['login'];
    $playerScore = $lastGamer['score'];

    // Choisir un mot aléatoire
    $pdo = dbConnect();
    $sql = "SELECT id, mot, definition
            FROM definitions
            WHERE langue = :lg
            ORDER BY RAND()
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':lg' => $langue]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<h3>Aucun mot trouvé en langue '$langue'</h3>";
        return;
    }

    $word       = $row['mot'];
    $definition = $row['definition'];
    $wordLen    = mb_strlen($word);
    $scoreInit  = 10 * $wordLen; // simple formula

    // Découper le mot en tableau de lettres (en tenant compte des caractères Unicode)
    $lettersArray = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);

    ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Jeu Word (<?php echo htmlspecialchars($langue); ?>)</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .game-container {
      border: 2px solid #ccc; padding: 20px; max-width: 700px; margin: 0 auto; position: relative;
    }
    .header-section { margin-bottom: 1em; }
    .header-section h2 { margin: 0; padding: 0; }
    .score-timer { margin-top: 0.5em; font-weight: bold; }
    .definition-section { border: 1px solid #aaa; padding: 15px; margin-bottom: 1em; background: #f9f9f9; }
    .button-row {
      margin-bottom: 1em;
      display: flex;
      align-items: center;
    }
    .button-row button { margin-right: 10px; cursor: pointer; }
    /* *** MODIF *** Rendre l'input plus visible et bien aligné */
    #letterInput {
      width: 120px;
      padding: 5px;
      font-size: 1em;
      margin-right: 10px;
    }
    .hint-icon {
      width: 24px; vertical-align: middle; cursor: pointer; margin-left: 10px; display: none;
    }
    .suggestion-box {
      border: 1px solid #666; padding: 10px; margin-top: 10px;
      display: none; background: #ffe;
    }
    .revealed-letters {
      font-size: 1.2em; letter-spacing: 0.2em; margin: 10px 0;
    }
    .steps-info { background: #eef; padding: 10px; margin-top: 1em; }
    .steps-info ol { margin: 0; padding-left: 1.5em; }
  </style>
</head>
<body>

<div class="game-container">
  <div class="header-section">
    <h2>Joueur : <?php echo htmlspecialchars($playerName); ?></h2>
    <p class="score-timer">
      Score actuel : <span id="playerScore"><?php echo (int)$playerScore; ?></span> —
      Temps restant : <span id="timeRemaining"><?php echo (int)$time; ?></span> s
    </p>
    <p>Score initial pour ce mot : <?php echo $scoreInit; ?> points</p>
  </div>

  <div class="definition-section">
    <strong>Définition du mot :</strong><br>
    <?php echo htmlspecialchars($definition); ?>
    <br><br>
    <em style="color:gray">(Mot secret : <?php echo htmlspecialchars($word); ?>)</em>
    <div class="revealed-letters" id="revealedLetters">
      <?php echo str_repeat("_ ", $wordLen); ?>
    </div>
  </div>

  <div class="button-row">
    <!-- *** MODIF *** Champ pour saisir la lettre directement -->
    <input type="text" id="letterInput" maxlength="1" placeholder="Tapez une lettre" />
    <button id="guessWordBtn">Deviner le mot complet</button>
    <img id="hintIcon" src="ampoule.png" alt="Hint" class="hint-icon" title="Voir les suggestions (20 points)" />
  </div>

  <div id="suggestionBox" class="suggestion-box">
    <strong>Suggestions :</strong>
    <p id="suggestionList">Aucune pour le moment.</p>
  </div>

  <div class="steps-info">
    <h3>Exemple de scénario :</h3>
    <ol>
      <li>À partir de time - (wordLen*10), toutes les 10s => -10 points et révélation d'une lettre.</li>
      <li>Au premier hint, l'icône (ampoule) apparaît pour 20 points.</li>
      <li>Ici, on force aussi l'apparition de l'ampoule au bout de 10s (exigence du cahier).</li>
      <li>Etc.</li>
    </ol>
  </div>
</div>

<script>
  let timeLeft   = <?php echo (int)$time; ?>;
  let totalTime  = timeLeft;
  let playerScoreSpan = document.getElementById("playerScore");
  let timeSpan   = document.getElementById("timeRemaining");
  let hintIcon   = document.getElementById("hintIcon");
  let suggestionBox  = document.getElementById("suggestionBox");
  let suggestionList = document.getElementById("suggestionList");
  let revealedLettersDiv = document.getElementById("revealedLetters");
  let letterInput = document.getElementById("letterInput");

  const letters = <?php echo json_encode($lettersArray, JSON_UNESCAPED_UNICODE); ?>;
  let revealed = new Array(letters.length).fill(false);

  function updateRevealedDisplay() {
    let display = "";
    for (let i = 0; i < letters.length; i++) {
      if (revealed[i]) {
        display += letters[i] + " ";
      } else {
        display += "_ ";
      }
    }
    revealedLettersDiv.textContent = display.trim();
  }

  function revealRandomLetter() {
    // Révèle une lettre non encore découverte, au hasard
    let hiddenIndices = [];
    for (let i = 0; i < revealed.length; i++) {
      if (!revealed[i]) hiddenIndices.push(i);
    }
    if (hiddenIndices.length === 0) return;
    let idx = hiddenIndices[Math.floor(Math.random() * hiddenIndices.length)];
    revealed[idx] = true;
    updateRevealedDisplay();
  }

  function adjustScore(delta) {
    let current = parseInt(playerScoreSpan.textContent, 10);
    current += delta;
    playerScoreSpan.textContent = current;
  }

  // Déclenchement progressif de la révélation (même logique qu'avant)
  let nextTrigger = (letters.length * 10) - 10;

  function startTimer() {
    let timer = setInterval(() => {
      if (timeLeft > 0) {
        timeLeft--;
        timeSpan.textContent = timeLeft;

        // Révélation automatique : toutes les 10s en partant de 'nextTrigger'
        if (timeLeft === nextTrigger && nextTrigger >= 0) {
          // Perte de 10 points et révélation d'une lettre
          adjustScore(-10);
          revealRandomLetter();
          nextTrigger -= 10;
        }

      } else {
        clearInterval(timer);
        alert("Temps écoulé !");
      }
    }, 1000);
  }
  startTimer();

  // *** MODIF *** Faire apparaître l'ampoule au bout de 10 secondes
  setTimeout(() => {
    hintIcon.style.display = "inline";
  }, 10000);

  // Quand l'utilisateur clique sur l'icône d'ampoule
  hintIcon.addEventListener("click", () => {
    adjustScore(-20);
    suggestionBox.style.display = "block";
    suggestionList.textContent = "Suggestions en fonction des lettres révélées...";
    hintIcon.style.display = "none";
  });

  // *** MODIF *** Gestion de la saisie de lettre via Enter
  letterInput.addEventListener("keyup", (event) => {
    if (event.key === "Enter") {
      let letter = letterInput.value.toUpperCase().trim();
      if (!letter) return;

      letterInput.value = ""; // réinitialiser l'input

      // Vérifier si la lettre existe dans 'letters'
      // (Exemple basique : +5 si la lettre est présente, -5 sinon)
      let found = false;
      for (let i = 0; i < letters.length; i++) {
        if (letters[i].toUpperCase() === letter) {
          if (!revealed[i]) {
            revealed[i] = true;
            found = true;
          }
        }
      }
      if (found) {
        adjustScore(+5);
      } else {
        adjustScore(-5);
      }
      updateRevealedDisplay();
    }
  });

  document.getElementById("guessWordBtn").addEventListener("click", () => {
    let guess = prompt("Entrez votre proposition :");
    if (!guess) return;

    guess = guess.trim().toUpperCase();
    let secretWord = "<?php echo mb_strtoupper($word); ?>";
    if (guess === secretWord) {
      alert("Bravo, c'est le bon mot !");
      // Ex: On calcule un bonus en fonction du temps restant
      // (+1 point par seconde restante, ou + floor(timeLeft/10), etc.)
      let bonus = Math.floor(timeLeft / 10);
      adjustScore(bonus);
    } else {
      alert("Ce n'est pas le bon mot...");
      adjustScore(-5);
    }
  });
</script>

</body>
</html>
<?php
}

// --------------------------------------------------------------------------
function showJeuDefPage($langue, $time) {
    $pdo = dbConnect();
    $sql = "SELECT id, mot 
            FROM definitions
            WHERE langue = :lg
            ORDER BY RAND() 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':lg' => $langue]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<h3>Aucun mot trouvé pour la langue '$langue'</h3>";
        return;
    }

    $wordId = $row['id'];
    $mot    = $row['mot'];

    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='utf-8'><title>Jeu Def ($langue)</title></head><body>";

    echo "<h1>Ajoutez vos définitions !</h1>";
    echo "<p>Langue: $langue, vous avez $time secondes</p>";
    echo "<b>Mot:</b> $mot (id=$wordId)";

    echo "<form method='POST' action='ajoutDef.php'>";
    echo "  <input type='hidden' name='word_id' value='$wordId'>";
    echo "  <input type='text' name='nouvelle_def' placeholder='Entrez une définition (5 à 200 caractères)'>";
    echo "  <button type='submit'>Ajouter</button>";
    echo "</form>";

    echo "</body></html>";
}

/* --------------------------------------------------------------------------
   /dump/<step> => affiche la table definitions par blocs de <step> lignes
-------------------------------------------------------------------------- */
function handleDump($method, $segments) {
    if ($method !== 'GET') {
        echo "Méthode invalide pour /dump";
        http_response_code(405);
        return;
    }
    $step = isset($segments[1]) ? (int)$segments[1] : 10;

    $pdo = dbConnect();
    $sql = "SELECT id, langue, mot, definition 
            FROM definitions
            LIMIT :limite";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $step, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
    echo "<title>Dump Definitions</title>";
    echo "<script src='https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js'></script>";
    echo "<script src='https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js'></script>";
    echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/datatables.net-dt/css/jquery.dataTables.min.css'>";
    echo "</head><body>";

    echo "<h1>Liste de définitions (max $step lignes)</h1>";
    echo "<table id='defs' class='display' style='width:80%'>";
    echo "<thead><tr><th>ID</th><th>Langue</th><th>Mot</th><th>Définition</th></tr></thead>";
    echo "<tbody>";
    foreach ($rows as $r) {
        $id   = $r['id'];
        $lg   = htmlspecialchars($r['langue']);
        $mot  = htmlspecialchars($r['mot']);
        $def  = htmlspecialchars($r['definition']);
        echo "<tr><td>$id</td><td>$lg</td><td>$mot</td><td>$def</td></tr>";
    }
    echo "</tbody></table>";

    echo "<script>
      \$(document).ready(function() {
        \$('#defs').DataTable();
      });
    </script>";

    echo "</body></html>";
}

// --------------------------------------------------------------------------
// /doc => page HTML décrivant les services
// --------------------------------------------------------------------------
function handleDoc() {
    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='utf-8'><title>Documentation</title></head><body>";
    echo "<h1>Documentation des services REST</h1>";

    echo "<ul>";
    echo "<li><b>/gamers/&lt;joueur&gt;</b> => GET : infos sur un joueur</li>";
    echo "<li><b>/gamers/add/&lt;joueur&gt;/&lt;pwd&gt;</b> => POST : ajout d’un joueur</li>";
    echo "<li><b>/gamers/login/&lt;joueur&gt;/&lt;pwd&gt;</b> => GET : connexion</li>";
    echo "<li><b>/gamers/logout/&lt;joueur&gt;/&lt;pwd&gt;</b> => GET : déconnexion</li>";
    echo "<li><b>/admin/top[/&lt;nb&gt;]</b> => GET : liste top nb joueurs</li>";
    echo "<li><b>/admin/delete/joueur/&lt;joueur&gt;</b> => GET : supprime un joueur</li>";
    echo "<li><b>/admin/delete/def/&lt;id&gt;</b> => GET : supprime une définition</li>";
    echo "<li><b>/word[/&lt;nb&gt;]</b> => GET : renvoie nb mots/defs (10 par défaut)</li>";
    echo "<li><b>/jeu/word/[&lt;lg&gt;[/&lt;time&gt;[/&lt;hint&gt;]]]</b> => GET : page HTML du jeu “Trouve le mot”</li>";
    echo "<li><b>/jeu/def[/&lt;lg&gt;[/&lt;time&gt;]]</b> => GET : page HTML pour ajouter des définitions</li>";
    echo "<li><b>/dump/&lt;step&gt;</b> => GET : DataTable des définitions, &lt;step&gt; par page</li>";
    echo "<li><b>/doc</b> => GET : cette page</li>";
    echo "</ul>";

    echo "</body></html>";
}
?>
