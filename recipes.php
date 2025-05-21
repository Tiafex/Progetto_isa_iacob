<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Verifica che l'ID utente sia valido prima di continuare
if (!is_numeric($user_id)) {
    die("Errore: ID utente non valido.");
}

// Gestione eliminazione ricetta (solo per le proprie ricette)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect per evitare la rimozione accidentale con refresh
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Inserimento nuova ricetta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if (!empty($title)) {
        // Prima di inserire, verifica che l'utente esista
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // L'utente esiste, procedi con l'inserimento della ricetta
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO recipes (user_id, title, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $title, $description);
            $stmt->execute();
            $stmt->close();
            
            // Redirect per evitare il doppio submit
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            // Se l'utente non esiste, visualizza un messaggio di errore
            $stmt->close();
            die("Errore: L'utente non esiste.");
        }
    }
}

// ricette preimpostate
$predefined_recipes = [
    ["title" => "Tiramisù di Pan di Stelle", "description" => "🍪 Prepara una crema con mascarpone, panna montata e zucchero, Inzuppa i Pan di Stelle nel latte o caffè. Fai uno strato di biscotti in una pirofila. Copri con uno strato di crema. Ripeti gli strati fino a esaurimento. Spolvera con cacao amaro in superficie. Metti in frigo per almeno 3 ore."],
    ["title" => "Casatiello napoletano", "description" => "🥧 Sciogli il lievito in acqua tiepida con un pizzico di zucchero, aggiungi farina, strutto, sale e impasta fino a ottenere un panetto morbido. Lascialo lievitare per 2 ore coperto poi stendi l'impasto in forma rettangolare. Distribuisci salumi e formaggi tagliati a cubetti. Arrotola l'impasto e forma una ciambella. Metti l'impasto in uno stampo a ciambella unto. Incorpora le uova crude a metà impasto con delle strisce a croce, Lascia lievitare di nuovo per 1 ora, Inforna a 180°C per 50-60 minuti."],
    ["title" => "Pasta con le Vongole e Bottarga", "description" => "🦪   Fai spurgare le vongole in acqua salata.  Rosola aglio e peperoncino in olio.  Aggiungi le vongole e sfuma con vino bianco.  Cuoci finché si aprono, poi filtra il liquido.  Cuoci la pasta e scolala al dente.  Salta la pasta con il liquido delle vongole.  Aggiungi prezzemolo tritato e bottarga grattugiata.  Servi subito con un filo d'olio crudo."],
    ["title" => "Pasta all'Amatriciana", "description" => " 🍝 Taglia il guanciale a striscioline. Rosolalo in padella senza olio finché croccante. Aggiungi pomodori pelati e cuoci per 15-20 min. Cuoci la pasta (bucatini o spaghetti) al dente. Scola e manteca in padella col sugo. Aggiungi pecorino romano grattugiato. Servi caldo con pepe nero."]
];

// Ricette dell'utente corrente
$result = $conn->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY id DESC");
$result->bind_param("i", $user_id);
$result->execute();
$recipes_result = $result->get_result();
$user_recipes = $recipes_result->fetch_all(MYSQLI_ASSOC);
$result->close();

// Ricette di tutti gli altri utenti con i nomi degli autori
$stmt = $conn->prepare("SELECT r.id, r.title, r.description, u.username 
    FROM recipes r 
    INNER JOIN users u ON r.user_id = u.id 
    WHERE r.user_id != ? ");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$other_recipes_result = $stmt->get_result();
$other_recipes = $other_recipes_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8" />
<title>Le mie Ricette</title>
<link rel="stylesheet" href="recipes.css" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="https://e7.pngegg.com/pngimages/565/647/png-clipart-chefs-uniform-hat-cook-chef-hat-askew-angle-white.png">

</head>
<body>
<div class="header">
    <h1>Benvenuto, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <div style="position: absolute; right: 20px; top: 20px;">
        
        <a style="color:black; font-weight: 500; text-decoration: none;" href="logout.php">Logout</a>
    </div>
    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
            <a style="color:black; font-weight: 500; margin-right: 15px; text-decoration: none; padding: 8px 12px; background: rgba(102, 126, 234, 0.1); border-radius: 6px; border: 1px solid #667eea;" href="admin.php">🛡️ Pannello Admin</a>
        <?php endif; ?>
</div>
<div class="container">
    
    <h2 style="text-align:center">Ricette facili facili</h2>
    <?php foreach ($predefined_recipes as $recipe): ?>
        <div class="recipe-card">
            <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
            <p><?php echo htmlspecialchars($recipe['description']); ?></p>
            <?php if ($recipe['title']== 'Tiramisù di Pan di Stelle'): ?>
            <img src="https://blog.giallozafferano.it/dulcisinforno/wp-content/uploads/2020/06/Tiramisu-pan-di-stelle-8190.jpg" style="width:200px; border-radius: 8px; margin-top: 10px;">
            <?php endif; ?>
            <?php if ($recipe['title']== 'Casatiello napoletano'): ?>
            <img src="https://www.cucchiaio.it/content/dam/cucchiaio/it/ricette/2013/03/ricetta-tortano/_R5_4199.jpg" style="width:200px; border-radius: 8px; margin-top: 10px;">
            <?php endif; ?>
            <?php if ($recipe['title']== 'Pasta con le Vongole e Bottarga'): ?>
            <img src="https://www.ivitelloni.it/wp-content/uploads/2017/07/spaghetti_vongole_e_bottarga_di_carloforte-870x500.jpg" style="width:200px; border-radius: 8px; margin-top: 10px;">
            <?php endif; ?>
            <?php if ($recipe['title']== "Pasta all'Amatriciana"): ?>
            <img src="https://www.todis.it/wp-content/uploads/2023/09/pasta-allamatriciana.jpg" style="width:200px; border-radius: 8px; margin-top: 10px;">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<h2>Ricette degli altri utenti</h2>
    <?php if (count($other_recipes) == 0): ?>
        <p style="text-align: center; font-style: italic; color: #666;">Nessun altro utente ha ancora condiviso ricette.</p>
    <?php else: ?>
        <?php foreach ($other_recipes as $recipe): ?>
            <div class="recipe-card">
                <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                <p><?php echo htmlspecialchars($recipe['description']); ?></p>
                <div style="margin-top: 10px; font-size: 0.9em; color: #888;">
                    <em>Creata da: <?php echo htmlspecialchars($recipe['username']); ?></em>
                    <?php if (isset($recipe['created_at'])): ?>
                        • <?php echo date('d/m/Y H:i', strtotime($recipe['created_at'])); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Cosa hai in mente oggi?</h2>
    <form method="POST" action="">
        <label for="title">Titolo della tua ricetta:</label>
        <input type="text" id="title" name="title" required />

        <label for="description">Descrizione della tua ricetta:</label>
        <textarea id="description" name="description" rows="4"></textarea>

        <button type="submit">Aggiungi Ricetta</button>
    </form>

    <h2>Le tue ricette</h2>
    <?php if (count($user_recipes) == 0): ?>
        <p style="text-align: center; font-style: italic; color: #666;">Non hai ancora aggiunto ricette.</p>
    <?php else: ?>
        <?php foreach ($user_recipes as $recipe): ?>
            <div class="recipe-card" data-recipe-id="<?php echo $recipe['id']; ?>">
                <button 
                    class="delete-btn" 
                    onclick="confirmDelete(<?php echo $recipe['id']; ?>, '<?php echo htmlspecialchars($recipe['title'], ENT_QUOTES); ?>')"
                    title="Elimina ricetta"
                >
                    ×
                </button>
                <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                <p><?php echo htmlspecialchars($recipe['description']); ?></p>
                <div style="margin-top: 10px; font-size: 0.9em; color: #888;">
                    <em>Creata da te</em>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    
</div>
<footer class="bg-oldmoney-dark text-oldmoney-light text-center py-4 mt-auto">
    <div class="container" style="text-align:center">
      <h3 class="mb-3">Seguici su</h3>
      <div class="social-icons mb-3">
        <a href="#" aria-label="Facebook" class="mx-2"><img src="https://imag.malavida.com/mvimgbig/download-s/facebook-10163-0.jpg" alt="Facebook"  width=50px /></a>
        <a href="#" aria-label="YouTube" class="mx-2"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/YouTube_social_white_square_%282017%29.svg/250px-YouTube_social_white_square_%282017%29.svg.png" alt="YouTube"  width=50px /></a>
        <a href="#" aria-label="Snapchat" class="mx-2"><img src="https://www.unidformazione.com/wp-content/uploads/2024/02/snapchat.jpg" alt="Snapchat"  width=50px /></a>
        <a href="#" aria-label="Instagram" class="mx-2"><img src="https://img.freepik.com/vettori-premium/icona-del-logo-vettoriale-di-instagram-logotipo-di-social-media_901408-392.jpg?semt=ais_hybrid&w=740" alt="Instagram"  width=50px /></a>
        <a href="#" aria-label="TikTok" class="mx-2"><img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS45jF2oX5HYzhemcWzyNdDf9PuYxjj8cf6ww&s" alt="TikTok"  width=50px /></a>
        <a href="#" aria-label="Twitter" class="mx-2"><img src="https://m.media-amazon.com/images/I/31AGs2bX7mL.png" alt="Twitter"  width=50px /></a>
      </div>
      
     
    </div>
  </footer>
<script>
function confirmDelete(recipeId, recipeTitle) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `<div class="modal-content">
        <h3 style="color: #e74c3c; margin-bottom: 15px;">⚠️ Conferma eliminazione</h3>
        <p>Sei sicuro di voler eliminare la ricetta "<strong>${recipeTitle}</strong>"?</p>
        <p style="color: #7f8c8d; font-size: 0.9em;">Questa azione non può essere annullata.</p>
        <div class="modal-buttons">
            <button class="btn-confirm" onclick="deleteRecipe(${recipeId})">Elimina</button>
            <button class="btn-cancel" onclick="closeModal()">Annulla</button>
        </div>
    </div>`;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Chiudi modal cliccando fuori
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

function deleteRecipe(recipeId) {
    const recipeCard = document.querySelector(`[data-recipe-id="${recipeId}"]`);
    recipeCard.classList.add('deleting');
    
    setTimeout(() => {
        window.location.href = `?delete_id=${recipeId}`;
    }, 500);
}

function closeModal() {
    const modal = document.querySelector('.modal');
    if (modal) {
        modal.remove();
    }
}

// Chiudi modal con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
</body>
</html>