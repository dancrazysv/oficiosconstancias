<?php
include 'check_session.php';
include 'db_config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM oficios WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: dashboard.php');
        exit();
    } catch (PDOException $e) {
        echo "Error al eliminar el oficio.";
    }
} else {
    header('Location: dashboard.php');
    exit();
}
?>