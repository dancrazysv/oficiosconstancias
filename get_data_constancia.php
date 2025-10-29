<?php
// Habilitar la visualización de errores (CRÍTICO para saber el fallo SQL)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CRÍTICO: Asegurar que la sesión se inicie antes de cualquier uso de $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_config.php';

// Obtener ID del usuario logueado 
$user_id = $_SESSION['user_id'] ?? 0;

$action = $_POST['action'] ?? '';

// ====================================================================
// 1. LÓGICA PARA CARGAR HOSPITALES (DEVUELVE HTML PARA DATALIST)
// ====================================================================
if ($action === 'get_hospitales') {
    
    try {
        $stmt = $pdo->query("SELECT nombre FROM hospitales ORDER BY nombre");
        $hospitales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $options = '';
        foreach ($hospitales as $h) {
            $nombre = htmlspecialchars($h['nombre']);
            // El formato debe ser <option value="Nombre Hospital">
            $options .= "<option value=\"{$nombre}\">";
        }
        
        // Devolver el string de opciones (HTML puro)
        echo $options; 
        
    } catch (PDOException $e) {
        error_log("Error de BDD al cargar hospitales: " . $e->getMessage());
        echo "<option value=''>--- ERROR AL CARGAR HOSPITALES ---</option>";
    }
    exit;

// ====================================================================
// 2. LÓGICA PARA CARGAR MUNICIPIOS (DEVUELVE JSON)
// ====================================================================
} elseif ($action === 'get_municipios') {
    header('Content-Type: application/json');
    $depto_id = $_POST['depto_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM municipios WHERE departamento_id = ? ORDER BY nombre");
        $stmt->execute([$depto_id]);
        $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'municipios' => $municipios]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de BDD al cargar municipios: ' . $e->getMessage()]);
    }
    exit;

// ====================================================================
// 3. LÓGICA PARA CARGAR DISTRITOS (DEVUELVE JSON)
// ====================================================================
} elseif ($action === 'get_distritos') {
    header('Content-Type: application/json');
    $municipio_id = $_POST['municipio_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM distritos WHERE municipio_id = ? ORDER BY nombre");
        $stmt->execute([$municipio_id]);
        $distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'distritos' => $distritos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de BDD al cargar distritos: ' . $e->getMessage()]);
    }
    exit;
    
// ====================================================================
// 4. LÓGICA DE BÚSQUEDA/REGISTRO DE SOLICITANTE (DEVUELVE JSON)
// ====================================================================
} elseif ($action === 'buscar_solicitante') {
    header('Content-Type: application/json');
    
    $tipo_doc_id = $_POST['tipo_documento_id'] ?? 0;
    $numero_doc = $_POST['numero_documento'] ?? '';
    $nombre_solicitante_manual = $_POST['nombre_solicitante_manual'] ?? ''; 
    
    // Limpieza CRÍTICA del documento
    $numero_doc_limpio = preg_replace('/[^a-zA-Z0-9]/', '', $numero_doc);
    $nombre_solicitante_manual = trim($nombre_solicitante_manual);
    
    if (empty($numero_doc_limpio) || empty($tipo_doc_id)) {
         echo json_encode(['success' => false, 'message' => 'Faltan datos para la búsqueda.']);
         exit;
    }

    try {
        // 1. INTENTAR BUSCAR EN LA BDD (tabla 'solicitantes')
        $sql_select = "SELECT nombre_completo FROM solicitantes 
                       WHERE tipo_documento_id = ? AND numero_documento_limpio = ?";
        
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute([$tipo_doc_id, $numero_doc_limpio]);
        $nombre_encontrado = $stmt_select->fetchColumn();

        if ($nombre_encontrado) {
            // Caso A: Solicitante ENCONTRADO
            echo json_encode(['success' => true, 'nombre' => $nombre_encontrado, 'registrado' => true]);
            
        } elseif (!empty($nombre_solicitante_manual) && $user_id !== 0) {
            // Caso B: NO ENCONTRADO, PERO SE PROPORCIONÓ NOMBRE MANUAL: Registrarlo
            
            // 2. Intentamos INSERTAR el nuevo solicitante.
            $sql_insert = "INSERT IGNORE INTO solicitantes (nombre_completo, tipo_documento_id, numero_documento_limpio) VALUES (?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$nombre_solicitante_manual, $tipo_doc_id, $numero_doc_limpio]);
            
            // Devolver el nombre que el usuario ingresó manualmente
            echo json_encode(['success' => true, 'nombre' => $nombre_solicitante_manual, 'registrado' => false]);

        } else {
            // Caso C: No encontrado, y el campo de nombre manual estaba vacío
            echo json_encode(['success' => false, 'message' => 'Ciudadano no encontrado. Ingrese nombre.']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de BDD al buscar o registrar: ' . $e->getMessage()]);
    }


} else {
    // Si la acción no es reconocida, se asume JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
}