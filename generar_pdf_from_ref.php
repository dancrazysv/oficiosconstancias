<?php
// Habilitar la visualización de errores (QUITAR EN PRODUCCIÓN)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluye el archivo que verifica la sesión y la base de datos
include 'check_session.php';
include 'db_config.php';

// Establece la zona horaria
date_default_timezone_set('America/El_Salvador');

// Configura la localización a español para la fecha
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_SV', 'spanish');

// Carga el autoloader de Composer
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Builder\Builder;
use setasign\Fpdi\Tcpdf\Fpdi; 

// Función de utilidad para obtener nombres a partir de un ID
function getNombreById($pdo, $tabla, $id) {
    if (empty($id)) return '';
    $stmt = $pdo->prepare("SELECT nombre FROM $tabla WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?? '';
}

// Inicialización de variables (simplificado)
$referencia = $_POST['referencia'] ?? ''; $nombre_difunto = $_POST['nombre_difunto'] ?? ''; $numero_partida = $_POST['numero_partida'] ?? 0; $folio = $_POST['folio'] ?? 0; $libro = $_POST['libro'] ?? ''; 
$distrito_inscripcion_nombre = $_POST['distrito_inscripcion'] ?? ''; $anio_inscripcion = $_POST['anio_inscripcion'] ?? 0; $nombre_licenciado = $_POST['nombre_licenciado'] ?? ''; $cargo_licenciado = $_POST['cargo_licenciado'] ?? '';
$departamento_destino_id = $_POST['departamento_destino'] ?? ''; $municipio_destino_id = $_POST['municipio_destino'] ?? ''; $distrito_destino_id = $_POST['distrito_destino'] ?? ''; 
$departamento_origen_id = $_POST['departamento_origen'] ?? ''; $municipio_origen_id = $_POST['municipio_origen'] ?? ''; $distrito_origen_id = $_POST['distrito_origen'] ?? '';
$user_rol = $_SESSION['user_rol']; // Obtener el rol

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ruta_oficio_temporal = '';
    $ruta_final_almacenada = NULL;
    
    // Iniciar el buffer de salida CRÍTICO
    ob_start(); 
    
    try {
        // === 1. Obtener datos de la base de datos y preparar variables ===
        $departamento_destino_nombre = getNombreById($pdo, 'departamentos', $departamento_destino_id);
        $municipio_destino_nombre = getNombreById($pdo, 'municipios', $municipio_destino_id);
        $distrito_destino_nombre = getNombreById($pdo, 'distritos', $distrito_destino_id);
        $departamento_origen_nombre = getNombreById($pdo, 'departamentos', $departamento_origen_id);
        $municipio_origen_nombre = getNombreById($pdo, 'municipios', $municipio_origen_id);
        $distrito_origen_nombre = getNombreById($pdo, 'distritos', $distrito_origen_id);

        $municipio_inscripcion_nombre = "San Salvador Centro"; $departamento_inscripcion_nombre = "San Salvador";
        $nombre_firmante = "LICDA. KARLA MARIELA OLIVARES MARTINEZ";
        
        $fecha_creacion = date('Y-m-d H:i:s');
        $fecha_display = strftime('%d de %B de %Y', strtotime($fecha_creacion));

        // --- Manejo de Imágenes y QR ---
        $url_validacion = "https://amssmarginaciones.sansalvador.gob.sv/pruebaoficios/validar_documento.php?ref=" . urlencode($referencia);
        
        $builder = Builder::create();
        $qrResult = $builder->data($url_validacion)->writer(new PngWriter())->size(200)->margin(10)->errorCorrectionLevel(new ErrorCorrectionLevelHigh())->build();
        $qrBase64 = $qrResult->getDataUri();

        $logo_path_relative = 'img/img_logo.png'; $full_logo_path = __DIR__ . '/' . $logo_path_relative;
        $logo_base64 = file_exists($full_logo_path) ? ('data:image/png;base64,' . base64_encode(file_get_contents($full_logo_path))) : ''; 

        $firma_path = 'img/FIRMAKARLACENTRO.png';
        $firma_base64_img = file_exists($firma_path) ? ('<img src="data:image/png;base64,' . base64_encode(file_get_contents($firma_path)) . '" style="width: 250px; height: auto;">') : 
            ('<p style="text-align: center;">LICDA. KARLA MARIELA OLIVARES MARTINEZ</p><p>REGISTRADOR DEL ESTADO FAMILIAR</p>');

        // === 2. Guardar el oficio en la base de datos ===
        $estado_inicial = ($user_rol === 'normal') ? 'PENDIENTE' : 'APROBADO'; // ESTADO INICIAL
        
        $sql_insert = "INSERT INTO oficios (referencia, fecha, nombre_licenciado, cargo_licenciado, distrito_destino, municipio_destino, departamento_destino, nombre_difunto, numero_partida, folio, libro, distrito_inscripcion, anio_inscripcion, departamento_origen, municipio_origen, distrito_origen, creado_por, estado_validacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([$referencia, $fecha_creacion, $nombre_licenciado, $cargo_licenciado,
            $distrito_destino_nombre, $municipio_destino_nombre, $departamento_destino_nombre,
            $nombre_difunto, $numero_partida, $folio, $libro, $distrito_inscripcion_nombre,
            $anio_inscripcion, $departamento_origen_nombre, $municipio_origen_nombre,
            $distrito_origen_nombre, $_SESSION['user_id'], $estado_inicial]);

        // === 3. Generar el PDF del Oficio (Página 1) usando Dompdf y Obtener Contenido ===
        $html = file_get_contents('plantilla_oficio.html');
        $replacements = [ /* ... array de reemplazos ... */
            '{{referencia}}' => $referencia, '{{fecha}}' => $fecha_display, '{{nombre_licenciado}}' => $nombre_licenciado,
            '{{cargo_licenciado}}' => $cargo_licenciado, '{{distrito_destino}}' => $distrito_destino_nombre,
            '{{municipio_destino}}' => $municipio_destino_nombre, '{{departamento_destino}}' => $departamento_destino_nombre,
            '{{nombre_difunto}}' => $nombre_difunto, '{{numero_partida}}' => $numero_partida, '{{folio}}' => $folio,
            '{{libro}}' => $libro, '{{distrito_inscripcion}}' => $distrito_inscripcion_nombre, '{{municipio_inscripcion}}' => $municipio_inscripcion_nombre,
            '{{departamento_inscripcion}}' => $departamento_inscripcion_nombre, '{{anio_inscripcion}}' => $anio_inscripcion,
            '{{distrito_origen}}' => $distrito_origen_nombre, '{{municipio_origen}}' => $municipio_origen_nombre, '{{departamento_origen}}' => $departamento_origen_nombre,
            '{{nombre_firmante}}' => $nombre_firmante,
            '{{logo_img}}' => '<img src="' . $logo_base64 . '" style="width: 250px; height: auto; margin-right: 20px; display: block; margin: 0 auto;">',
            '{{qr_code}}' => '<img src="' . $qrBase64 . '" style="width: 100px; height: 100px;">',
            '{{imagen_firma}}' => $firma_base64_img
        ];
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true); $options->set('isRemoteEnabled', true); 
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();

        $oficio_contenido_pdf = $dompdf->output(); // Contenido del PDF del oficio

        // === 4. Procesar el archivo subido y fusionar con FPDI/TCPDF ===
        
        $output_filename = 'Oficio_' . $referencia . '.pdf';
        $pdf_contenido_final = $oficio_contenido_pdf; // Contenido final por defecto

        // Verificar si se subió un archivo para fusionar
        if (isset($_FILES['archivo_anexo']) && $_FILES['archivo_anexo']['error'] === UPLOAD_ERR_OK && $_FILES['archivo_anexo']['type'] === 'application/pdf') {
            
            $ruta_anexo = $_FILES['archivo_anexo']['tmp_name'];
            $output_filename = 'Oficio_Fusionado_' . $referencia . '.pdf'; 
            
            $ruta_oficio_temporal = sys_get_temp_dir() . '/oficio_temp_' . uniqid() . '.pdf';
            file_put_contents($ruta_oficio_temporal, $oficio_contenido_pdf); // Guardar contenido
            
            $pdf = new Fpdi(); // Crear el objeto FPDI para la fusión
            
            if (ob_get_level() > 0) { ob_end_clean(); ob_start(); } // Limpieza crítica ANTES de FPDI
            
            // Importar el Oficio Generado (Página 1)
            $pdf->setSourceFile($ruta_oficio_temporal);
            $pdf->AddPage();
            $pdf->useTemplate($pdf->importPage(1));

            // Importar el Archivo Anexado (Todas las páginas)
            $pagina_count_anexo = $pdf->setSourceFile($ruta_anexo);
            for ($i = 1; $i <= $pagina_count_anexo; $i++) {
                $pdf->AddPage();
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
            }
            
            $pdf_contenido_final = $pdf->Output($output_filename, 'S'); // Salida a cadena
            
            unlink($ruta_oficio_temporal); // Limpiar archivo temporal
        }

        // === 5. Guardado Final en Disco y BDD ===
        
        $carpeta_final = __DIR__ . '/archivos_finales';
        if (!is_dir($carpeta_final)) { mkdir($carpeta_final, 0777, true); }
        
        $ruta_final_servidor = $carpeta_final . '/' . $output_filename;
        file_put_contents($ruta_final_servidor, $pdf_contenido_final);
        $ruta_final_almacenada = 'archivos_finales/' . $output_filename;

        // Actualizar la base de datos con la ruta del PDF Final
        $sql_update_ruta = "UPDATE oficios SET ruta_pdf_final = ? WHERE referencia = ?";
        $stmt_update_ruta = $pdo->prepare($sql_update_ruta);
        $stmt_update_ruta->execute([$ruta_final_almacenada, $referencia]);


        // === 6. Manejo de Redirección y Salida Final ===
        
        // Limpiamos el buffer final
        ob_end_clean(); 
        
        if ($user_rol === 'normal') {
            // Usuario Normal: Redirige inmediatamente al dashboard sin mostrar el PDF
            header('Location: dashboard.php?status=pending');
            exit;
        } else {
            // Admin/Supervisor: Muestra el PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $output_filename . '"');
            echo $pdf_contenido_final;
            exit;
        }


    } catch (PDOException $e) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        // Mostrar error SQL para depuración
        die("Error al procesar el formulario (DB): SQLSTATE[{$e->getCode()}]: {$e->getMessage()}");
    } catch (Exception $e) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        // Mostrar error de fusión/librería para depuración
        die("Error al procesar el formulario (PDF/Fusión): " . $e->getMessage());
    }
} else {
    header('Location: crear_oficio.php');
    exit();
}