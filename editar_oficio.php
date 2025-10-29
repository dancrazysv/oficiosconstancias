<?php
include 'check_session.php';
include 'db_config.php';

// Establece la zona horaria
date_default_timezone_set('America/El_Salvador');

// Configura la localización a español para la fecha
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_SV', 'spanish');

// Carga las clases de PDF y QR (necesarias para la fusión)
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use setasign\Fpdi\Tcpdf\Fpdi; // CLASE CRÍTICA PARA LA FUSIÓN

// === FUNCIONES DE UTILIDAD ===
function getIdByName($pdo, $table, $name) {
    if (empty($name)) return null;
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE nombre = ? LIMIT 1");
    $stmt->execute([$name]);
    return $stmt->fetchColumn(); 
}

function getNombreById($pdo, $table, $id) {
    if (empty($id)) return '';
    $stmt = $pdo->prepare("SELECT nombre FROM $table WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?? '';
}

function getSelectOptions($pdo, $table, $fk_col, $fk_id, $selected_name) {
    $options = '<option value="">Seleccione...</option>';
    if ($fk_id) {
        $stmt = $pdo->prepare("SELECT id, nombre FROM $table WHERE $fk_col = ? ORDER BY nombre");
        $stmt->execute([$fk_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $selected = ($item['nombre'] == $selected_name) ? 'selected' : '';
            $options .= "<option value=\"{$item['id']}\" {$selected}>" . htmlspecialchars($item['nombre']) . "</option>";
        }
    }
    return $options;
}


// === LÓGICA PRINCIPAL Y CARGA DE DATOS ===

$oficio_id = $_GET['id'] ?? null;
$oficio = null;
$error = '';
$ruta_oficio_temporal_fusion = ''; // Inicializar para manejo de errores

if (!$oficio_id) {
    die("ID de oficio no proporcionado.");
}

try {
    // 1. Cargar datos del oficio
    $stmt = $pdo->prepare("SELECT * FROM oficios WHERE id = ?");
    $stmt->execute([$oficio_id]);
    $oficio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oficio) {
        die("Oficio no encontrado.");
    }

    // 2. Obtener la lista de departamentos para el primer select
    $stmt_depto = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre");
    $departamentos = $stmt_depto->fetchAll();

    // 3. Obtener los IDs actuales para precargar los menús encadenados
    $depto_destino_id_actual = getIdByName($pdo, 'departamentos', $oficio['departamento_destino']);
    $municipio_destino_id_actual = getIdByName($pdo, 'municipios', $oficio['municipio_destino']);
    $distrito_destino_id_actual = getIdByName($pdo, 'distritos', $oficio['distrito_destino']);

    $depto_origen_id_actual = getIdByName($pdo, 'departamentos', $oficio['departamento_origen']);
    $municipio_origen_id_actual = getIdByName($pdo, 'municipios', $oficio['municipio_origen']);
    $distrito_origen_id_actual = getIdByName($pdo, 'distritos', $oficio['distrito_origen']);

    // 4. Generar opciones iniciales para Municipios y Distritos
    $municipios_destino_options = getSelectOptions($pdo, 'municipios', 'departamento_id', $depto_destino_id_actual, $oficio['municipio_destino']);
    $distritos_destino_options = getSelectOptions($pdo, 'distritos', 'municipio_id', $municipio_destino_id_actual, $oficio['distrito_destino']);
    
    $municipios_origen_options = getSelectOptions($pdo, 'municipios', 'departamento_id', $depto_origen_id_actual, $oficio['municipio_origen']);
    $distritos_origen_options = getSelectOptions($pdo, 'distritos', 'municipio_id', $municipio_origen_id_actual, $oficio['distrito_origen']);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// === LÓGICA DE ACTUALIZACIÓN (UPDATE) ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_cambios'])) {
    
    // 1. Obtener los NOMBRES a partir de los IDs seleccionados
    $depto_destino_nombre_post = getNombreById($pdo, 'departamentos', $_POST['departamento_destino']);
    $municipio_destino_nombre_post = getNombreById($pdo, 'municipios', $_POST['municipio_destino']);
    $distrito_destino_nombre_post = getNombreById($pdo, 'distritos', $_POST['distrito_destino']);
    
    $depto_origen_nombre_post = getNombreById($pdo, 'departamentos', $_POST['departamento_origen']);
    $municipio_origen_nombre_post = getNombreById($pdo, 'municipios', $_POST['municipio_origen']);
    $distrito_origen_nombre_post = getNombreById($pdo, 'distritos', $_POST['distrito_origen']);

    $subir_archivo = isset($_FILES['archivo_anexo']) && $_FILES['archivo_anexo']['error'] === UPLOAD_ERR_OK && $_FILES['archivo_anexo']['type'] === 'application/pdf';

    // Variables de POST
    $referencia = $_POST['referencia'] ?? $oficio['referencia'];
    $nombre_difunto = $_POST['nombre_difunto'] ?? $oficio['nombre_difunto'];
    $numero_partida = $_POST['numero_partida'] ?? $oficio['numero_partida'];
    $folio = $_POST['folio'] ?? $oficio['folio'];
    $libro = $_POST['libro'] ?? $oficio['libro'];
    $distrito_inscripcion_nombre = $_POST['distrito_inscripcion'] ?? $oficio['distrito_inscripcion'];
    $anio_inscripcion = $_POST['anio_inscripcion'] ?? $oficio['anio_inscripcion'];
    $nombre_licenciado = $_POST['nombre_licenciado'] ?? $oficio['nombre_licenciado'];
    $cargo_licenciado = $_POST['cargo_licenciado'] ?? $oficio['cargo_licenciado'];


$fondo_path = 'img/fondo_oficio.png';
        $fondo_base64 = '';
        if (file_exists($fondo_path)) {
            $fondo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($fondo_path));
        }
        
        // Estilo de fondo a inyectar en el HTML
        $background_style = $fondo_base64 ? "background-image: url('$fondo_base64'); background-repeat: no-repeat; background-position: center top; background-size: 100% 100%;" : "";




    try {
        // 2. Ejecutar la sentencia UPDATE de los datos de texto (CRÍTICO)
        $sql_update = "UPDATE oficios SET 
            nombre_licenciado = ?, cargo_licenciado = ?, distrito_destino = ?, municipio_destino = ?, 
            departamento_destino = ?, nombre_difunto = ?, numero_partida = ?, folio = ?, 
            libro = ?, distrito_inscripcion = ?, anio_inscripcion = ?, departamento_origen = ?, 
            municipio_origen = ?, distrito_origen = ?
            WHERE id = ?";

        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            $nombre_licenciado, $cargo_licenciado, $distrito_destino_nombre_post,
            $municipio_destino_nombre_post, $depto_destino_nombre_post, $nombre_difunto,
            $numero_partida, $folio, $libro, $distrito_inscripcion_nombre,
            $anio_inscripcion, $depto_origen_nombre_post, $municipio_origen_nombre_post,
            $distrito_origen_nombre_post, $oficio_id
        ]);

        // 3. Si hay un archivo, generar el PDF base y fusionar inmediatamente
        if ($subir_archivo) {
            
            // --- Cargar datos actualizados del oficio ---
            $stmt_reload = $pdo->prepare("SELECT * FROM oficios WHERE id = ?");
            $stmt_reload->execute([$oficio_id]);
            $oficio_actualizado = $stmt_reload->fetch(PDO::FETCH_ASSOC);

            if (!$oficio_actualizado) {
                 throw new Exception("Error al obtener los datos actualizados del oficio.");
            }

            // Lógica de Dompdf para generar y obtener el contenido
            $fecha_display = strftime('%d de %B de %Y', strtotime($oficio_actualizado['fecha']));
            $nombre_remitente = "LICDA. KARLA MARIELA OLIVARES MARTINEZ";

            $logo_path_relative = 'img/img_logo.png'; $full_logo_path = __DIR__ . '/img/img_logo.png'; 
            $logo_base64 = file_exists($full_logo_path) ? ('data:image/png;base64,' . base64_encode(file_get_contents($full_logo_path))) : ''; 
            $firma_path = 'img/FIRMAKARLACENTRO.png';
            $firma_base64_img = file_exists($firma_path) ? ('<img src="data:image/png;base64,' . base64_encode(file_get_contents($firma_path)) . '" style="width: 350px; height: auto;">') : 
                ('<p style="text-align: center;">LICDA. KARLA MARIELA OLIVARES MARTINEZ</p><p>REGISTRADOR DEL ESTADO FAMILIAR</p>');
            
            $url_validacion = "https://amssmarginaciones.sansalvador.gob.sv/pruebaoficios/validar_documento.php?ref=" . urlencode($oficio_actualizado['referencia']);
            $builder = Builder::create();
            $qrResult = $builder->data($url_validacion)->writer(new PngWriter())->size(200)->margin(10)->errorCorrectionLevel(new ErrorCorrectionLevelHigh())->build();
            $qrBase64 = $qrResult->getDataUri();

            $html = file_get_contents('plantilla_oficio.html');
            $replacements = [
                '{{referencia}}' => $oficio_actualizado['referencia'], '{{fecha}}' => $fecha_display, '{{nombre_licenciado}}' => $oficio_actualizado['nombre_licenciado'],
                '{{cargo_licenciado}}' => $oficio_actualizado['cargo_licenciado'], '{{distrito_destino}}' => $oficio_actualizado['distrito_destino'],
                '{{municipio_destino}}' => $oficio_actualizado['municipio_destino'], '{{departamento_destino}}' => $oficio_actualizado['departamento_destino'],
                '{{nombre_difunto}}' => $oficio_actualizado['nombre_difunto'], '{{numero_partida}}' => $oficio_actualizado['numero_partida'], '{{folio}}' => $oficio_actualizado['folio'],
                '{{libro}}' => $oficio_actualizado['libro'], '{{distrito_inscripcion}}' => $oficio_actualizado['distrito_inscripcion'], '{{municipio_inscripcion}}' => 'San Salvador Centro',
                '{{departamento_inscripcion}}' => 'San Salvador', '{{anio_inscripcion}}' => $oficio_actualizado['anio_inscripcion'],
                '{{distrito_origen}}' => $oficio_actualizado['distrito_origen'], '{{municipio_origen}}' => $oficio_actualizado['municipio_origen'], '{{departamento_origen}}' => $oficio_actualizado['departamento_origen'],
                '{{nombre_firmante}}' => $nombre_remitente, '{{logo_img}}' => '<img src="' . $logo_base64 . '" style="width: 250px; height: auto; margin-right: 20px; display: block; margin: 0 auto;">',
                '{{qr_code}}' => '<img src="' . $qrBase64 . '" style="width: 125px; height: 125px;">', '{{imagen_firma}}' => $firma_base64_img, 
                '{{background_style}}' => $background_style 
            ];
            $html = str_replace(array_keys($replacements), array_values($replacements), $html);

            $options = new Options(); $options->set('isHtml5ParserEnabled', true); $options->set('isRemoteEnabled', true); 
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('Letter', 'portrait');
            $dompdf->render();
            $oficio_contenido_pdf = $dompdf->output();

            // --- Lógica de FUSIÓN Y GUARDADO ---
            $ruta_anexo = $_FILES['archivo_anexo']['tmp_name'];
            $output_filename = 'Oficio_' . $oficio_actualizado['referencia'] . '.pdf'; // Nombre Unificado
            
            // Guardar temporalmente el PDF del oficio
            $ruta_oficio_temporal_fusion = sys_get_temp_dir() . '/oficio_fusion_edit_' . uniqid() . '.pdf';
            file_put_contents($ruta_oficio_temporal_fusion, $oficio_contenido_pdf);
            
            $pdf = new Fpdi();
            if (ob_get_level() > 0) { ob_end_clean(); } // Limpieza crítica
            
            // Importar el Oficio Generado (Página 1)
            $pdf->setPrintHeader(false);
            $pdf->setSourceFile($ruta_oficio_temporal_fusion); $pdf->AddPage(); $pdf->useTemplate($pdf->importPage(1));
            
            // Importar el Archivo Anexado
            $pagina_count_anexo = $pdf->setSourceFile($ruta_anexo);
            for ($i = 1; $i <= $pagina_count_anexo; $i++) {
                $pdf->AddPage();
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
            }
            
            $pdf_fusionado_contenido = $pdf->Output($output_filename, 'S'); // Salida a cadena

            // Guardado Permanente
            $carpeta_final = __DIR__ . '/archivos_finales';
            if (!is_dir($carpeta_final)) { mkdir($carpeta_final, 0777, true); }
            $ruta_final_almacenada = 'archivos_finales/' . $output_filename;
            file_put_contents($carpeta_final . '/' . $output_filename, $pdf_fusionado_contenido);

            $sql_update_ruta = "UPDATE oficios SET ruta_pdf_final = ? WHERE id = ?";
            $stmt_update_ruta = $pdo->prepare($sql_update_ruta);
            $stmt_update_ruta->execute([$ruta_final_almacenada, $oficio_id]);
            
            // 5. Envío al navegador y limpieza
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $output_filename . '"');
            echo $pdf_fusionado_contenido;
            
            unlink($ruta_oficio_temporal_fusion); // Limpiar archivos temporales
            
            // 6. Redirección final con JavaScript (Cierra la pestaña de PDF y redirige la principal)
            echo '<script type="text/javascript">
                    if(window.opener) {
                        window.opener.location.href = "dashboard.php"; 
                        window.close(); 
                    } else {
                        window.location.href = "dashboard.php"; 
                    }
                  </script>';
            exit; // Detener la ejecución aquí
        }

        // 4. Si NO hay archivo subido, redirigir al dashboard (Redirección HTTP)
        header('Location: dashboard.php');
        exit();

    } catch (PDOException $e) {
        $error = "Error al guardar los cambios: " . $e->getMessage();
        // Intentar limpiar el archivo temporal si existe
        if (isset($ruta_oficio_temporal_fusion) && file_exists($ruta_oficio_temporal_fusion)) {
            unlink($ruta_oficio_temporal_fusion);
        }
    } catch (Exception $e) {
        $error = "Error al fusionar PDF: " . $e->getMessage();
        if (isset($ruta_oficio_temporal_fusion) && file_exists($ruta_oficio_temporal_fusion)) {
            unlink($ruta_oficio_temporal_fusion);
        }
    }
}

// Obtener el rol y nombre del usuario para la navegación
$rol = $_SESSION['user_rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Oficio: <?php echo htmlspecialchars($oficio['referencia']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin-top: 50px; padding: 30px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="dashboard.php">Registro de Oficios</a>
    
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="crear_oficio.php">Crear Oficio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
            </li>
            <?php if ($rol === 'administrador'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="usuarios_admin.php">Gestión de Usuarios</a>
                </li>
            <?php endif; ?>
        </ul>
        <span class="navbar-text mr-3">
            Bienvenido, **<?php echo htmlspecialchars($nombre_usuario); ?>**
        </span>
        <a href="logout.php" class="btn btn-outline-light">Cerrar Sesión</a>
    </div>
</nav>

<div class="container">
    <h2 class="text-center mb-4">Editar Oficio: <?php echo htmlspecialchars($oficio['referencia']); ?></h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form id="editform" action="editar_oficio.php?id=<?php echo $oficio_id; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="guardar_cambios" value="1">
        
        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Referencia:</label>
                <input type="text" class="form-control" name="referencia" value="<?php echo htmlspecialchars($oficio['referencia']); ?>" readonly>
            </div>
            <div class="form-group col-md-6">
                <label>Fecha de Creación:</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($oficio['fecha']))); ?>" readonly>
            </div>
        </div>

        <h4 class="mt-4">Datos del Destinatario (A quien se remite)</h4>
        
        <div class="form-group">
            <label>Departamento:</label>
            <select class="form-control" id="departamento_destino" name="departamento_destino" required>
                <option value="">Seleccione un departamento</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?php echo $depto['id']; ?>" 
                            <?php echo ($depto['id'] == $depto_destino_id_actual) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($depto['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Municipio:</label>
            <select class="form-control" id="municipio_destino" name="municipio_destino" required 
                    <?php echo $depto_destino_id_actual ? '' : 'disabled'; ?>>
                <?php echo $municipios_destino_options; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Distrito:</label>
            <select class="form-control" id="distrito_destino" name="distrito_destino" required 
                    <?php echo $municipio_destino_id_actual ? '' : 'disabled'; ?>>
                <?php echo $distritos_destino_options; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Nombre del Licenciado(a):</label>
            <input type="text" class="form-control" id="nombre_licenciado" name="nombre_licenciado" 
                   value="<?php echo htmlspecialchars($oficio['nombre_licenciado']); ?>" readonly>
        </div>
        <div class="form-group">
            <label>Cargo del Licenciado(a):</label>
            <input type="text" class="form-control" id="cargo_licenciado" name="cargo_licenciado" 
                   value="<?php echo htmlspecialchars($oficio['cargo_licenciado']); ?>" readonly>
        </div>
        
        <h4 class="mt-4">Datos del Difunto</h4>
        <div class="form-group">
            <label>Nombre del Difunto:</label>
            <input type="text" class="form-control" name="nombre_difunto" 
                   value="<?php echo htmlspecialchars($oficio['nombre_difunto']); ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Número de Partida:</label>
                <input type="text" class="form-control" name="numero_partida" 
                       value="<?php echo htmlspecialchars($oficio['numero_partida']); ?>" required>
            </div>
            <div class="form-group col-md-4">
                <label>Folio:</label>
                <input type="text" class="form-control" name="folio" 
                       value="<?php echo htmlspecialchars($oficio['folio']); ?>" required>
            </div>
            <div class="form-group col-md-4">
                <label>Libro:</label>
                <input type="text" class="form-control" name="libro" 
                       value="<?php echo htmlspecialchars($oficio['libro']); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Distrito de Inscripción (SS Centro):</label>
                <select class="form-control" id="distrito_inscripcion" name="distrito_inscripcion">
                    <option value="<?php echo htmlspecialchars($oficio['distrito_inscripcion']); ?>" selected>
                        <?php echo htmlspecialchars($oficio['distrito_inscripcion']); ?>
                    </option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Año de Inscripción:</label>
                <input type="number" class="form-control" name="anio_inscripcion" 
                       value="<?php echo htmlspecialchars($oficio['anio_inscripcion']); ?>" required>
            </div>
        </div>
        
        <h4 class="mt-4">Lugar de Origen del Difunto</h4>
        
        <div class="form-group">
            <label>Departamento:</label>
            <select class="form-control" id="departamento_origen" name="departamento_origen" required>
                <option value="">Seleccione un departamento</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?php echo $depto['id']; ?>" 
                            <?php echo ($depto['id'] == $depto_origen_id_actual) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($depto['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Municipio:</label>
            <select class="form-control" id="municipio_origen" name="municipio_origen" required 
                    <?php echo $depto_origen_id_actual ? '' : 'disabled'; ?>>
                <?php echo $municipios_origen_options; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Distrito:</label>
            <select class="form-control" id="distrito_origen" name="distrito_origen" required 
                    <?php echo $municipio_origen_id_actual ? '' : 'disabled'; ?>>
                <?php echo $distritos_origen_options; ?>
            </select>
        </div>
        
        <h4 class="mt-4">Reemplazar/Anexar PDF</h4>
        <div class="form-group">
            <label for="archivo_anexo">Nuevo PDF a Anexar (Reemplaza el anterior):</label>
            <input type="file" class="form-control-file" id="archivo_anexo" name="archivo_anexo" accept="application/pdf">
            <small class="form-text text-muted">Subir un archivo aquí actualizará la versión fusionada en el sistema.</small>
        </div>


        <button type="submit" class="btn btn-primary btn-block mt-4">Guardar Cambios</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        // --- Redirección JavaScript para el flujo de edición ---
        $('#editform').on('submit', function() {
            // Si el formulario se envía y NO hay PDF subido, redirige al dashboard
            if ($('#archivo_anexo').get(0).files.length === 0) {
                 setTimeout(function() {
                     window.location.href = 'dashboard.php';
                 }, 500); 
            }
        });

        // --- Funciones AJAX de Carga Dinámica (Simplificadas) ---

        function loadMunicipios(departamentoId, targetSelect, nextSelect, selectedId = null) {
            targetSelect.html('<option value="">Cargando...</option>').prop('disabled', true);
            if (nextSelect) nextSelect.html('<option value="">Seleccione un distrito</option>').prop('disabled', true);

            if (departamentoId) {
                $.ajax({
                    url: 'get_data.php',
                    type: 'POST',
                    data: { action: 'get_municipios', departamento_id: departamentoId },
                    success: function(data) {
                        targetSelect.html('<option value="">Seleccione un municipio</option>' + data).prop('disabled', false).trigger('change');
                        if (selectedId) {
                            targetSelect.val(selectedId).trigger('change');
                        }
                    }
                });
            } else {
                targetSelect.html('<option value="">Seleccione un municipio</option>').prop('disabled', true);
            }
        }

        function loadDistritos(municipioId, targetSelect, selectedId = null) {
            targetSelect.html('<option value="">Cargando...</option>').prop('disabled', true);

            if (municipioId) {
                $.ajax({
                    url: 'get_data.php',
                    type: 'POST',
                    data: { action: 'get_distritos', municipio_id: municipioId },
                    success: function(data) {
                        targetSelect.html('<option value="">Seleccione un distrito</option>' + data).prop('disabled', false);
                        if (selectedId) {
                            targetSelect.val(selectedId);
                        }
                    }
                });
            } else {
                targetSelect.html('<option value="">Seleccione un distrito</option>').prop('disabled', true);
            }
        }

        // --- Lógica de PRECARGA y Eventos ---
        
        // IDs de precarga (obtenidos del PHP)
        const preloadedMunicipioDestinoId = '<?php echo $municipio_destino_id_actual; ?>';
        const preloadedDistritoDestinoId = '<?php echo $distrito_destino_id_actual; ?>';
        const preloadedMunicipioOrigenId = '<?php echo $municipio_origen_id_actual; ?>';
        const preloadedDistritoOrigenId = '<?php echo $distrito_origen_id_actual; ?>';

        // 1. Cargar Municipios Destino al cambiar el Departamento Destino (Dispara la carga inicial)
        $('#departamento_destino').on('change', function() {
            var deptoId = $(this).val();
            if (deptoId) {
                loadMunicipios(deptoId, $('#municipio_destino'), $('#distrito_destino'), preloadedMunicipioDestinoId);
            }
        }).trigger('change'); // Forzar la carga inicial

        // 2. Cargar Distritos Destino y Oficiante al cambiar el Municipio Destino
        $('#municipio_destino').on('change', function() {
            var municipioId = $(this).val();
            
            // Cargar Distritos
            if (municipioId) {
                loadDistritos(municipioId, $('#distrito_destino'), preloadedDistritoDestinoId);
            }
            
            // Cargar Oficiante (Asociado al MUNICIPIO)
            if (municipioId) {
                $.ajax({
                    url: 'get_data.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'get_oficiante', municipio_id: municipioId },
                    success: function(response) {
                        if (response.success && response.oficiante) {
                            $('#nombre_licenciado').val(response.oficiante.nombre);
                            $('#cargo_licenciado').val(response.oficiante.cargo);
                        } else {
                            $('#nombre_licenciado').val('');
                            $('#cargo_licenciado').val('');
                        }
                    }
                });
            } else {
                $('#nombre_licenciado').val('');
                $('#cargo_licenciado').val('');
            }
        });
        
        // 3. Cargar Lugar de Origen
        $('#departamento_origen').on('change', function() {
            var deptoId = $(this).val();
            if (deptoId) {
                loadMunicipios(deptoId, $('#municipio_origen'), $('#distrito_origen'), preloadedMunicipioOrigenId);
            }
        }).trigger('change');
        
        $('#municipio_origen').on('change', function() {
            var municipioId = $(this).val();
            if (municipioId) {
                loadDistritos(municipioId, $('#distrito_origen'), preloadedDistritoOrigenId);
            }
        });
        
        // Carga inicial de distritos de inscripción (SAN SALVADOR CENTRO)
        fetch('get_distritos_inscripcion.php')
            .then(response => response.json())
            .then(distritos => {
                const selectElement = $('#distrito_inscripcion');
                const selectedInscripcion = '<?php echo htmlspecialchars($oficio['distrito_inscripcion']); ?>';

                selectElement.empty();
                
                distritos.forEach(distrito => {
                    const option = $('<option></option>').val(distrito).text(distrito);
                    if (distrito == selectedInscripcion) {
                        option.prop('selected', true);
                    }
                    selectElement.append(option);
                });
            })
            .catch(error => console.error('Error al cargar distritos:', error));
    });
</script>
</body>
</html>