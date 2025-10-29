<?php
session_start();

// Si el usuario ID o el nombre de usuario NO están en la sesión, redirige al login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['nombre_usuario'])) {
    // Redirige al login.php y termina la ejecución
    header('Location: login.php'); 
    exit();
}
// El script continúa si la sesión es válida
?>