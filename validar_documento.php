<?php
// Incluir la configuración de la base de datos
include 'db_config.php';

$oficio = null;
$error = '';
$usuario_creador = '';
$nombre_firmante_remitente = "LICDA. KARLA MARIELA OLIVARES MARTINEZ, Registrador del Estado Familiar del Municipio de San Salvador Centro";
$link_descarga = '#'; 
$mensaje_validacion = '';
$documento_valido = true; 
$ruta_pdf_final = '';

// Establecer la zona horaria para cálculos
date_default_timezone_set('America/El_Salvador');
// Configurar la localización a español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_SV', 'spanish');


// Lógica para cargar el logo (Escudo)
$escudo_path = 'img/escudo.png';
$escudo_base64 = '';
if (file_exists($escudo_path)) {
    $escudo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($escudo_path));
}

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referencia = urldecode($_GET['ref']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM oficios WHERE referencia = ?");
        $stmt->execute([$referencia]);
        $oficio = $stmt->fetch();

        if ($oficio) {
            
            // --- Lógica de Tiempo y Vencimiento ---
            $fecha_creacion = new DateTime($oficio['fecha']);
            $fecha_actual = new DateTime('now');
            $fecha_limite = clone $fecha_creacion;
            $fecha_limite->modify('+3 months');

            // 1. Verificar si el documento ha expirado
            if ($fecha_actual > $fecha_limite) {
                $documento_valido = false;
                $mensaje_validacion = '<p class="text-danger">❌ LA VALIDACIÓN HA VENCIDO. Han transcurrido más de tres meses desde su emisión.</p>';
                
                // --- Eliminación del Archivo PDF Guardado (Si el campo no es NULL) ---
                if (!empty($oficio['ruta_pdf_final'])) {
                    $ruta_absoluta = __DIR__ . '/' . $oficio['ruta_pdf_final'];
                    if (file_exists($ruta_absoluta)) {
                        unlink($ruta_absoluta);
                    }
                    // Borrar la ruta de la base de datos
                    $pdo->prepare("UPDATE oficios SET ruta_pdf_final = NULL WHERE id = ?")->execute([$oficio['id']]);
                }
                
            } else {
                // 2. Documento activo: Calcular días restantes y preparar datos
                $intervalo = $fecha_actual->diff($fecha_limite);
                $dias_restantes = $intervalo->days;
                $mensaje_validacion = '<p class="text-success">✅ Documento activo. Quedan <b>' . $dias_restantes . ' días</b> para la validación.</p>';
                
                // Obtener datos del oficio y ruta
                $stmt_usuario = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
                $stmt_usuario->execute([$oficio['creado_por']]);
                $usuario_creador = $stmt_usuario->fetchColumn();
                
                // CORRECCIÓN DE FECHA: Formatear a español (d-m-Y H:i:s)
                $fecha_formateada = strftime('%d-%m-%Y %H:%M:%S', strtotime($oficio['fecha']));

                $ruta_pdf_final = $oficio['ruta_pdf_final'];
                
                // Establecer enlace de descarga
                if (!empty($ruta_pdf_final)) {
                    // Usamos la IP de tu servidor local para la descarga
                    $link_descarga = 'https://amssmarginaciones.sansalvador.gob.sv/pruebaoficios/' . $ruta_pdf_final; 
                }
            }

        } else {
            $error = "Documento no encontrado o referencia inválida.";
        }
    } catch (PDOException $e) {
        $error = "Error al conectar con la base de datos.";
    }
} else {
    $error = "Referencia del documento no proporcionada.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Validación de Documento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; }
        .card { border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .logo-institucion { 
            width: 100px; 
            height: auto;
            margin-bottom: 15px; 
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8">
            <div class="text-center mb-4">
                <?php if (!empty($escudo_base64)): ?>
                    <img src="<?php echo $escudo_base64; ?>" alt="Escudo de la Institución" class="logo-institucion">
                <?php endif; ?>
                <h2 class="mt-3">Validación de Oficio</h2>
            </div>
            <div class="card p-4">
                <?php if ($oficio): ?>
                    
                    <?php echo $mensaje_validacion; ?>

                    <?php if ($documento_valido): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th scope="row">1. Referencia</th>
                                        <td><?php echo htmlspecialchars($oficio['referencia']); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">2. Fecha y Hora de Emisión</th>
                                        <td><?php echo htmlspecialchars($fecha_formateada); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">3. Nombre del Fallecido</th>
                                        <td><?php echo htmlspecialchars($oficio['nombre_difunto']); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">4. Foliación</th>
                                        <td>N° Partida: <?php echo htmlspecialchars($oficio['numero_partida']); ?>, Folio: <?php echo htmlspecialchars($oficio['folio']); ?>, Libro: <?php echo htmlspecialchars($oficio['libro']); ?>, Año: <?php echo htmlspecialchars($oficio['anio_inscripcion']); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">5. Lugar de Inscripción</th>
                                        <td>Distrito de <?php echo htmlspecialchars($oficio['distrito_inscripcion']); ?>, Municipio de San Salvador Centro, Departamento de San Salvador</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">6. Registrador que remite</th>
                                        <td><?php echo htmlspecialchars($nombre_firmante_remitente); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">7. Elaborado por</th>
                                        <td><?php echo htmlspecialchars($usuario_creador); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">8. Link de Descarga</th>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($link_descarga); ?>" 
                                               target="_blank" 
                                               class="btn btn-primary btn-sm <?php echo (empty($ruta_pdf_final)) ? 'disabled' : ''; ?>"
                                               <?php echo (empty($ruta_pdf_final)) ? 'onclick="return false;"' : ''; ?>>
                                                Descargar PDF Final
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-center mt-3">
                            <small>La información mostrada aquí coincide con los registros oficiales.</small>
                        </p>

                    <?php endif; ?>
                    
                <?php else: ?>
                    <p class="text-danger text-center">❌ <?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>