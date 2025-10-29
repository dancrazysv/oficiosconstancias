<?php
// Habilitar la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluye la configuración de la base de datos
include 'db_config.php';

// El parámetro que se espera es 'id' (el ID de la constancia)
$constancia_id = $_GET['id'] ?? null;

if (!$constancia_id) {
    die("ID de constancia no proporcionado.");
}

try {
    // 1. Cargar la RUTA del PDF final guardado desde la tabla 'constancias'
    $stmt = $pdo->prepare("SELECT ruta_pdf_final FROM constancias WHERE id = ?");
    $stmt->execute([$constancia_id]);
    $ruta_relativa = $stmt->fetchColumn();

    if (empty($ruta_relativa)) {
        die("Error: El archivo PDF final no existe o no ha sido generado para esta constancia.");
    }
    
    // 2. Construir la ruta absoluta y verificar el archivo
    $ruta_absoluta = __DIR__ . '/' . $ruta_relativa; 
    
    if (!file_exists($ruta_absoluta)) {
        die("Error: El archivo PDF guardado no se encuentra en la ruta: " . htmlspecialchars($ruta_absoluta));
    }
    
    // 3. Enviar el archivo guardado al navegador
    $filename = basename($ruta_absoluta);
    
    // Limpieza de buffering de salida CRÍTICA
    if (ob_get_level() > 0) {
        ob_end_clean(); 
    }
    
    // 4. Configurar los encabezados para enviar el PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($ruta_absoluta));
    
    // 5. Leer y enviar el contenido del archivo
    readfile($ruta_absoluta);
    exit;

} catch (PDOException $e) {
    die("Error de base de datos al cargar la constancia: " . $e->getMessage());
} catch (Exception $e) {
    die("Error al procesar el archivo: " . $e->getMessage());
}