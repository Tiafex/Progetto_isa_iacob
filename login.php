<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Inserisci username e password.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
           if (password_verify($password, $row['password'])) {
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $username;
    $_SESSION['is_admin'] = $row['is_admin']; // Importante!
    
    header('Location: ' . ($row['is_admin'] == 1 ? 'admin.php' : 'recipes.php'));
    exit();
            } else {
                $error = "Password errata.";
            }
        } else {
            $error = "Username non trovato.";
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8" />
<title>Login utente</title>
<link rel="stylesheet" href="styles.css" />
<link rel="icon" type="image/x-icon" href="https://e7.pngegg.com/pngimages/565/647/png-clipart-chefs-uniform-hat-cook-chef-hat-askew-angle-white.png">
</head>
<body>
<div class="header">
    <h1>Login</h1>
</div>
<div class="rcorners2">
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST" action="">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required />

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required />

        <button type="submit">Login</button>
    </form>
    <a href="index.php">Non hai un account? Registrati</a>
</div>
</body>
</html>