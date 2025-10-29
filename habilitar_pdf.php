<?php
include 'check_session.php';
include 'db_config.php';

// Solo permite acceso si es administrador o supervisor
if ($_SESSION['user_rol'] !== 'administrador' && $_SESSION['user_rol'] !== 'supervisor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

header('Content-Type: application/json');

$oficio_id = $_POST['id'] ?? null;

if (empty($oficio_id)) {
    echo json_encode(['success' => false, 'message' => 'ID de oficio no proporcionado.']);
    exit();
}

try {
    // 1. Ejecutar la actualización del estado
    $stmt = $pdo->prepare("UPDATE oficios SET estado_validacion = 'APROBADO' WHERE id = ?");
    $stmt->execute([$oficio_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Oficio habilitado y aprobado.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'El oficio ya estaba aprobado o no se encontró.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}