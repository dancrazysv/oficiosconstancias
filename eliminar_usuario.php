<?php
include 'check_session.php';
include 'db_config.php';

// Redirigir si no es administrador
if ($_SESSION['user_rol'] !== 'administrador') {
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: dashboard.php');
        exit();
    } catch (PDOException $e) {
        // Manejar el error, por ejemplo, redirigir con un mensaje de error
        echo "Error al eliminar el usuario.";
    }
} else {
    header('Location: dashboard.php');
    exit();
}
?>