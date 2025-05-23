<?php
session_start();
require 'config.php';


// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
 header('Location: login.php');
    exit();
}

// Verifica se l'utente è admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
   
    // Controlla nel database per sicurezza
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        if ($user_data['is_admin'] == 1) {
            // Aggiorna la sessione se il database dice che è admin
            $_SESSION['is_admin'] = 1;
         } else {
            // Non è admin, reindirizza
            header('Location: recipes.php?error=access_denied');
            exit();
        }
    } else {
        // Utente non trovato nel database
        session_destroy();
        header('Location: login.php?error=user_not_found');
        exit();
    }
    $stmt->close();
}

$admin_id = $_SESSION['user_id'];

// Gestione azioni admin
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            if ($user_id != $admin_id) {
                try {
                    // Prima elimina tutte le ricette dell'utente
                    $stmt = $conn->prepare("DELETE FROM recipes WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Poi elimina l'utente
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success_message = "Utente eliminato con successo";
                } catch (Exception $e) {
                    $error_message = "Errore nell'eliminazione: " . $e->getMessage();
                }
            }
            break;
            
        case 'delete_recipe':
            $recipe_id = intval($_POST['recipe_id']);
            try {
                $stmt = $conn->prepare("DELETE FROM recipes WHERE id = ?");
                $stmt->bind_param("i", $recipe_id);
                $stmt->execute();
                $stmt->close();
                $success_message = "Ricetta eliminata con successo";
            } catch (Exception $e) {
                $error_message = "Errore nell'eliminazione della ricetta: " . $e->getMessage();
            }
            break;
            
        case 'toggle_admin':
            $user_id = intval($_POST['user_id']);
            if ($user_id != $admin_id) {
                $new_admin_status = intval($_POST['new_admin_status']);
                try {
                    $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_admin_status, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Ruolo utente modificato con successo";
                } catch (Exception $e) {
                    $error_message = "Errore nella modifica del ruolo: " . $e->getMessage();
                }
            }
            break;
    }
    
    // Redirect per evitare risubmit (solo se non ci sono errori)
    if (!isset($error_message)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
        exit();
    }
}

// Statistiche generali
$stats = [];

try {
    // Conteggio utenti
    $result = $conn->query("SELECT COUNT(*) as total_users FROM users");
    $stats['total_users'] = $result->fetch_assoc()['total_users'];

    // Conteggio admin
    $result = $conn->query("SELECT COUNT(*) as total_admins FROM users WHERE is_admin = 1");
    $stats['total_admins'] = $result->fetch_assoc()['total_admins'];

    // Conteggio ricette
    $result = $conn->query("SELECT COUNT(*) as total_recipes FROM recipes");
    $stats['total_recipes'] = $result->fetch_assoc()['total_recipes'];

    // Ricetta più recente
    $result = $conn->query("SELECT r.title, u.username
                           FROM recipes r 
                           INNER JOIN users u ON r.user_id = u.id");
    $latest_recipe = $result->fetch_assoc();

    // Ottenere tutti gli utenti con il conteggio delle ricette
    $users_query = "SELECT u.id, u.username, u.is_admin, COUNT(r.id) as recipe_count
                    FROM users u
                    LEFT JOIN recipes r ON u.id = r.user_id
                    GROUP BY u.id, u.username, u.is_admin";
    $users_result = $conn->query($users_query);
    $users = $users_result->fetch_all(MYSQLI_ASSOC);

    // Ottenere tutte le ricette con informazioni utente
    $recipes_query = "SELECT r.id, r.title, r.description,
                             u.username, u.id as user_id
                      FROM recipes r
                      INNER JOIN users u ON r.user_id = u.id";
    $recipes_result = $conn->query($recipes_query);
    $recipes = $recipes_result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Errore nel caricamento dei dati: " . $e->getMessage();
    $stats = ['total_users' => 0, 'total_admins' => 0, 'total_recipes' => 0];
    $users = [];
    $recipes = [];
    $latest_recipe = null;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannello Admin - Gestione Ricette</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://e7.pngegg.com/pngimages/565/647/png-clipart-chefs-uniform-hat-cook-chef-hat-askew-angle-white.png">
    <link rel="stylesheet" href="admin.css" />
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>🛡️ Pannello Admin</h1>
            <nav class="admin-nav">
                <span style="color: #666;">Benvenuto, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="recipes.php">📚 Vai alle Ricette</a>
                <a href="logout.php">🚪 Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ Operazione completata con successo!
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
            <div class="alert alert-error">
                🚫 Accesso negato: non hai i privilegi di amministratore.
            </div>
        <?php endif; ?>

       
      
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Utenti Totali</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Amministratori</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_recipes']; ?></div>
                <div class="stat-label">Ricette Totali</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users'] - $stats['total_admins']; ?></div>
                <div class="stat-label">Utenti Standard</div>
            </div>
        </div>

        <?php if ($latest_recipe): ?>
        <div class="section">
            <h2>📈 Ultima Attività</h2>
            <p><strong><?php echo htmlspecialchars($latest_recipe['username']); ?></strong> ha aggiunto "<strong><?php echo htmlspecialchars($latest_recipe['title']); ?></strong>" 
        </div>
        <?php endif; ?>

        // gestione utenti

        <div class="section">
            <h2>👥 Gestione Utenti</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Ruolo</th>
                            <th>Ricette</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <span class="admin-badge">Admin</span>
                                <?php else: ?>
                                    Utente
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['recipe_count']; ?></td>
                            <td>
                                <?php if ($user['id'] != $admin_id): ?>
                                    <button class="btn btn-warning" onclick="toggleAdmin(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['is_admin'] ? 0 : 1; ?>)">
                                        <?php echo $user['is_admin'] ? 'Rimuovi Admin' : 'Rendi Admin'; ?>
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        Elimina
                                    </button>
                                <?php else: ?>
                                    <span style="color: #666; font-style: italic;">Tu</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        // gestione ricette
        <div class="section">
            <h2>🍽️ Gestione Ricette</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titolo</th>
                            <th>Descrizione</th>
                            <th>Autore</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><?php echo $recipe['id']; ?></td>
                            <td><?php echo htmlspecialchars($recipe['title']); ?></td>
                            <td class="description-cell" title="<?php echo htmlspecialchars($recipe['description']); ?>">
                                <?php echo htmlspecialchars(substr($recipe['description'], 0, 50)) . (strlen($recipe['description']) > 50 ? '...' : ''); ?>
                            </td>
                            <td><?php echo htmlspecialchars($recipe['username']); ?></td>
                            <td>
                                <button class="btn btn-danger" onclick="confirmDeleteRecipe(<?php echo $recipe['id']; ?>, '<?php echo htmlspecialchars($recipe['title']); ?>')">
                                    Elimina
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    // conferma azione
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Conferma Azione</h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button id="confirmBtn" class="btn btn-danger">Conferma</button>
                <button class="btn" onclick="closeModal()" style="background: #95a5a6; color: white;">Annulla</button>
            </div>
        </div>
    </div>

     // form nascosti
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="user_id" id="userId">
        <input type="hidden" name="recipe_id" id="recipeId">
        <input type="hidden" name="new_admin_status" id="newAdminStatus">
    </form>

    <script>
        function confirmDeleteUser(userId, username) {
            document.getElementById('modalTitle').textContent = '⚠️ Elimina Utente';
            document.getElementById('modalMessage').innerHTML = `Sei sicuro di voler eliminare l'utente "<strong>${username}</strong>"?<br><small style="color: #e74c3c;">Verranno eliminate anche tutte le sue ricette!</small>`;
            document.getElementById('confirmBtn').onclick = () => deleteUser(userId);
            document.getElementById('confirmModal').style.display = 'block';
        }

        function confirmDeleteRecipe(recipeId, recipeTitle) {
            document.getElementById('modalTitle').textContent = '⚠️ Elimina Ricetta';
            document.getElementById('modalMessage').innerHTML = `Sei sicuro di voler eliminare la ricetta "<strong>${recipeTitle}</strong>"?`;
            document.getElementById('confirmBtn').onclick = () => deleteRecipe(recipeId);
            document.getElementById('confirmModal').style.display = 'block';
        }

        function toggleAdmin(userId, username, newStatus) {
            const action = newStatus ? 'rendere amministratore' : 'rimuovere i privilegi di amministratore da';
            document.getElementById('modalTitle').textContent = '🛡️ Modifica Ruolo';
            document.getElementById('modalMessage').innerHTML = `Sei sicuro di voler ${action} "<strong>${username}</strong>"?`;
            document.getElementById('confirmBtn').onclick = () => changeAdminStatus(userId, newStatus);
            document.getElementById('confirmModal').style.display = 'block';
        }

        function deleteUser(userId) {
            document.getElementById('actionType').value = 'delete_user';
            document.getElementById('userId').value = userId;
            document.getElementById('actionForm').submit();
        }

        function deleteRecipe(recipeId) {
            document.getElementById('actionType').value = 'delete_recipe';
            document.getElementById('recipeId').value = recipeId;
            document.getElementById('actionForm').submit();
        }

        function changeAdminStatus(userId, newStatus) {
            document.getElementById('actionType').value = 'toggle_admin';
            document.getElementById('userId').value = userId;
            document.getElementById('newAdminStatus').value = newStatus;
            document.getElementById('actionForm').submit();
        }

        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // Chiudi azione cliccando fuori o con ESC
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>