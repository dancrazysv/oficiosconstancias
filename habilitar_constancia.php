<?php
// Habilitar la visualización de errores solo para desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Incluir archivos necesarios
include 'check_session.php'; // Asegura la sesión y la autenticación
include 'db_config.php';     // Incluye la conexión PDO

// 2. Configurar la respuesta a JSON
header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// 3. Verificar que la solicitud sea POST y que exista el ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Solicitud no válida o ID del documento faltante.';
    echo json_encode($response);
    exit;
}

$constancia_id = $_POST['id'];
$rol_validador = strtolower(trim($_SESSION['user_rol']));
$user_id = $_SESSION['user_id'];

// 4. Verificar permisos del rol
if (!in_array($rol_validador, ['administrador', 'supervisor'])) {
    $response['message'] = 'Permiso denegado: Su rol no puede validar documentos.';
    echo json_encode($response);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();

    // 5. Obtener el estado actual para evitar re-validar
    $sql_select = "SELECT estado_validacion, validacion_supervisor, validacion_administrador FROM constancias WHERE id = ?";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute([$constancia_id]);
    $constancia = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$constancia) {
        throw new Exception('Constancia no encontrada.');
    }

    // Si ya está aprobado, no hacer nada más
    if ($constancia['estado_validacion'] === 'APROBADO') {
        throw new Exception('Este documento ya se encuentra aprobado.');
    }

    // 6. Determinar qué campo de validación marcar
    $campo_a_marcar = '';
    if ($rol_validador === 'supervisor') {
        if ((int)$constancia['validacion_supervisor'] === 1) {
            throw new Exception('La constancia ya fue marcada por un Supervisor.');
        }
        $campo_a_marcar = 'validacion_supervisor';
    } elseif ($rol_validador === 'administrador') {
        if ((int)$constancia['validacion_administrador'] === 1) {
            throw new Exception('La constancia ya fue marcada por un Administrador.');
        }
        $campo_a_marcar = 'validacion_administrador';
    }

    // 7. --- LÓGICA SIMPLIFICADA ---
    // Actualiza el rol que valida Y aprueba el documento en un solo paso.
    $sql_approve = "UPDATE constancias 
                    SET 
                        estado_validacion = 'APROBADO', 
                        {$campo_a_marcar} = 1,
                        fecha_validacion = NOW()
                    WHERE id = ?";
    
    $stmt_approve = $pdo->prepare($sql_approve);
    $stmt_approve->execute([$constancia_id]);

    // Finalizar la transacción
    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = 'Constancia APROBADA y HABILITADA exitosamente.';

} catch (Exception $e) {
    // Si algo sale mal, revertir cambios y notificar
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Fallo al habilitar: ' . $e->getMessage();
}

// 8. Devolver la respuesta JSON
echo json_encode($response);
?>