<?php
include 'db_config.php';

$action = $_POST['action'] ?? '';

if ($action === 'get_municipios') {
    if (isset($_POST['departamento_id']) && !empty($_POST['departamento_id'])) {
        $departamento_id = $_POST['departamento_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT id, nombre FROM municipios WHERE departamento_id = ? ORDER BY nombre");
            $stmt->execute([$departamento_id]);
            $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $options = '';
            foreach ($municipios as $m) {
                $options .= "<option value=\"{$m['id']}\">{$m['nombre']}</option>";
            }
            echo $options;
        } catch (PDOException $e) {
            // Manejar error silenciosamente para no romper la interfaz
            echo ''; 
        }
    } else {
        echo '';
    }
} elseif ($action === 'get_distritos') {
    if (isset($_POST['municipio_id']) && !empty($_POST['municipio_id'])) {
        $municipio_id = $_POST['municipio_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT id, nombre FROM distritos WHERE municipio_id = ? ORDER BY nombre");
            $stmt->execute([$municipio_id]);
            $distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $options = '';
            foreach ($distritos as $d) {
                $options .= "<option value=\"{$d['id']}\">{$d['nombre']}</option>";
            }
            echo $options;
        } catch (PDOException $e) {
            echo '';
        }
    } else {
        echo '';
    }
} elseif ($action === 'get_oficiante') {
    header('Content-Type: application/json');
    // CORRECCIÓN CLAVE: Ahora se espera el municipio_id
    $municipio_id = $_POST['municipio_id'] ?? ''; 

    if (!empty($municipio_id)) {
        try {
            // La consulta usa municipio_id para buscar en la tabla oficiantes
            $stmt = $pdo->prepare("SELECT nombre, cargo FROM oficiantes WHERE municipio_id = ? LIMIT 1");
            $stmt->execute([$municipio_id]);
            $oficiante = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oficiante) {
                echo json_encode(['success' => true, 'oficiante' => $oficiante]);
            } else {
                // Devuelve campos vacíos si no hay oficiante asociado
                echo json_encode(['success' => true, 'oficiante' => ['nombre' => '', 'cargo' => '']]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error de base de datos.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de municipio no proporcionado.']);
    }
} else {
    // Respuesta por defecto si la acción no es reconocida
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
}
?>