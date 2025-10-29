<?php
// Asegura que la sesión esté iniciada y el usuario esté autenticado
include 'check_session.php';
// Incluye la configuración de la base de datos
include 'db_config.php';

// Carga el autoloader de Composer para usar Dompdf y la librería de QR
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Builder\Builder; // Importa el Builder

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // === 1. Obtener y validar datos del formulario ===
        $referencia = $_POST['referencia'] ?? '';
        $nombre_difunto = $_POST['nombre_difunto'] ?? '';
        $numero_partida = $_POST['numero_partida'] ?? '';
        $folio = $_POST['folio'] ?? '';
        $libro = $_POST['libro'] ?? '';
        $distrito_inscripcion_nombre = $_POST['distrito_inscripcion'] ?? '';
        $anio_inscripcion = $_POST['anio_inscripcion'] ?? '';
        $nombre_licenciado = $_POST['nombre_licenciado'] ?? '';
        $cargo_licenciado = $_POST['cargo_licenciado'] ?? '';

        // Obtener IDs de los selectores
        $departamento_destino_id = $_POST['departamento_destino'] ?? '';
        $municipio_destino_id = $_POST['municipio_destino'] ?? '';
        $distrito_destino_id = $_POST['distrito_destino'] ?? '';
        $departamento_origen_id = $_POST['departamento_origen'] ?? '';
        $municipio_origen_id = $_POST['municipio_origen'] ?? '';
        $distrito_origen_id = $_POST['distrito_origen'] ?? '';

        // === 2. Consultar la base de datos para obtener los nombres ===
        function getNombreById($pdo, $tabla, $id) {
            $stmt = $pdo->prepare("SELECT nombre FROM $tabla WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?? '';
        }

        $departamento_destino_nombre = getNombreById($pdo, 'departamentos', $departamento_destino_id);
        $municipio_destino_nombre = getNombreById($pdo, 'municipios', $municipio_destino_id);
        $distrito_destino_nombre = getNombreById($pdo, 'distritos', $distrito_destino_id);
        $departamento_origen_nombre = getNombreById($pdo, 'departamentos', $departamento_origen_id);
        $municipio_origen_nombre = getNombreById($pdo, 'municipios', $municipio_origen_id);
        $distrito_origen_nombre = getNombreById($pdo, 'distritos', $distrito_origen_id);

        $municipio_inscripcion_nombre = "San Salvador Centro";
        $departamento_inscripcion_nombre = "San Salvador";
        $nombre_firmante = "LICDA. KARLA MARIELA OLIVARES MARTINEZ";
        $fecha_creacion = date('Y-m-d H:i:s');
        $fecha_display = date('d \d\e F \d\e Y', strtotime($fecha_creacion));

        // === 3. Generar el código QR con la sintaxis corregida ===
        $url_validacion = "http://localhost/tu-sitio/validar_documento.php?ref=" . urlencode($referencia);

        // Sintaxis corregida: se usa el constructor del Builder para crear la instancia
        $builder = new Builder();
        $result = $builder
            ->data($url_validacion)
            ->writer(new PngWriter())
            ->size(200)
            ->margin(10)
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->build();

        $qrBase64 = $result->getDataUri();

        // === 4. Guardar el oficio en la base de datos ===
        $sql_insert = "INSERT INTO oficios (referencia, fecha, nombre_licenciado, cargo_licenciado, distrito_destino, municipio_destino, departamento_destino, nombre_difunto, numero_partida, folio, libro, distrito_inscripcion, anio_inscripcion, departamento_origen, municipio_origen, distrito_origen, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $referencia, $fecha_creacion, $nombre_licenciado, $cargo_licenciado,
            $distrito_destino_nombre, $municipio_destino_nombre, $departamento_destino_nombre,
            $nombre_difunto, $numero_partida, $folio, $libro, $distrito_inscripcion_nombre,
            $anio_inscripcion, $departamento_origen_nombre, $municipio_origen_nombre,
            $distrito_origen_nombre, $_SESSION['user_id']
        ]);

        // === 5. Generar el PDF con Dompdf ===
        $html = file_get_contents('plantilla_oficio.html');
        $replacements = [
            '{{referencia}}' => $referencia,
            '{{fecha}}' => $fecha_display,
            '{{nombre_licenciado}}' => $nombre_licenciado,
            '{{cargo_licenciado}}' => $cargo_licenciado,
            '{{distrito_destino}}' => $distrito_destino_nombre,
            '{{municipio_destino}}' => $municipio_destino_nombre,
            '{{departamento_destino}}' => $departamento_destino_nombre,
            '{{nombre_difunto}}' => $nombre_difunto,
            '{{numero_partida}}' => $numero_partida,
            '{{folio}}' => $folio,
            '{{libro}}' => $libro,
            '{{distrito_inscripcion}}' => $distrito_inscripcion_nombre,
            '{{municipio_inscripcion}}' => $municipio_inscripcion_nombre,
            '{{departamento_inscripcion}}' => $departamento_inscripcion_nombre,
            '{{anio_inscripcion}}' => $anio_inscripcion,
            '{{distrito_origen}}' => $distrito_origen_nombre,
            '{{municipio_origen}}' => $municipio_origen_nombre,
            '{{departamento_origen}}' => $departamento_origen_nombre,
            '{{nombre_firmante}}' => $nombre_firmante,
            '{{qr_code}}' => '<img src="' . $qrBase64 . '" style="width: 100px; height: 100px;">'
        ];
        
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream('Oficio.pdf', array('Attachment' => 0));

    } catch (PDOException $e) {
        die("Error al procesar el formulario: " . $e->getMessage());
    }
} else {
    header('Location: crear_oficio.php');
    exit();
}