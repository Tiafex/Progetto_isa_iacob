<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Inserisci username e password.";
    } else {
        // Controlla se username già esiste
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username già esistente.";
        } else {
            // Inserisci nuovo utente
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            if ($stmt->execute()) {
                header('Location: login.php');
                exit();
            } else {
                $error = "Errore durante la registrazione.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8" />
<title>Registrazione utente</title>
<link rel="stylesheet" href="index.css" />
<link rel="icon" type="image/x-icon" href="https://e7.pngegg.com/pngimages/565/647/png-clipart-chefs-uniform-hat-cook-chef-hat-askew-angle-white.png">

</head>
<body>
<div class="header">
    <h1>Registrati</h1>
</div>
<div class="container">
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST" action="">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required />

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required />

        <button type="submit">Registrati</button>
    </form>
    <a href="login.php">Hai già un account? Accedi</a>
</div>
</body>
</html>