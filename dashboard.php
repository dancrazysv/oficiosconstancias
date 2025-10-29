<?php
// Habilitar la visualización de errores (CRÍTICO para saber el fallo SQL)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Asegura que la sesión esté iniciada y el usuario esté autenticado
include 'check_session.php';
// Incluye la configuración de la base de datos
include 'db_config.php';

$rol = $_SESSION['user_rol'];
$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre_usuario'];

// --- Lógica de Búsqueda y Paginación ---
$documentos_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1; 
$offset = ($pagina_actual - 1) * $documentos_por_pagina;
$busqueda = $_GET['busqueda'] ?? '';

// Construir la cláusula WHERE y los parámetros
$where_clauses_oficios = [];
$params_oficios = [];
$where_clauses_constancias = [];
$params_constancias = [];

// 1. Filtro por Rol: Define la visibilidad
if ($rol === 'normal') {
    // CRÍTICO: Usamos 'o.creado_por = ?' y 'c.creado_por_id = ?' en la cláusula WHERE
    $where_clauses_oficios[] = "o.creado_por = ?"; 
    $params_oficios[] = $user_id;
    $where_clauses_constancias[] = "c.creado_por_id = ?";
    $params_constancias[] = $user_id;
}

// 2. Filtro de Búsqueda: Aplicar a ambas consultas
if (!empty($busqueda)) {
    // Oficios: busca en difunto, referencia, o elaborador
    $where_clauses_oficios[] = "(o.nombre_difunto LIKE ? OR o.referencia LIKE ? OR u.nombre_completo LIKE ?)";
    array_push($params_oficios, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    
    // Constancias: busca en solicitante o persona no registrada
    $where_clauses_constancias[] = "(c.nombre_solicitante LIKE ? OR c.nombre_no_registro LIKE ? OR u.nombre_completo LIKE ?)";
    array_push($params_constancias, "%$busqueda%", "%$busqueda%", "%$busqueda%");
}

// Combinar las cláusulas WHERE
$where_sql_oficios = count($where_clauses_oficios) > 0 ? " WHERE " . implode(' AND ', $where_clauses_oficios) : "";
$where_sql_constancias = count($where_clauses_constancias) > 0 ? " WHERE " . implode(' AND ', $where_clauses_constancias) : "";


// --- CONSTRUCCIÓN DE CONSULTAS BASE CON FILTROS ---

// Consulta Base de OFICIOS (o) - incluye WHERE
$sql_oficios_full = "
    SELECT 
        o.id, o.referencia, o.fecha, o.nombre_difunto AS registrado_a, o.municipio_destino_id, o.enviado_correo, o.estado_validacion, u.nombre_completo AS elaborado_por, 'OFICIO' AS tipo_documento
    FROM oficios o 
    LEFT JOIN usuarios u ON o.creado_por = u.id
    {$where_sql_oficios}
";

// Consulta Base de CONSTANCIAS (c) - incluye WHERE
$sql_constancias_full = "
    SELECT 
        c.id, c.tipo_constancia AS referencia, c.fecha_emision AS fecha, c.nombre_no_registro AS registrado_a, NULL AS municipio_destino_id, c.enviado_correo, c.estado_validacion, u.nombre_completo AS elaborado_por, 'CONSTANCIA' AS tipo_documento
    FROM constancias c
    LEFT JOIN usuarios u ON c.creado_por_id = u.id
    {$where_sql_constancias}
";

// --- UNIFICACIÓN Y ORDENAMIENTO DE DATOS ---

// CONSULTA DE DATOS FINAL Y PAGINADA (Estructura simplificada para compatibilidad)
$union_sql = "
    ({$sql_oficios_full})
    UNION ALL
    ({$sql_constancias_full})
    ORDER BY fecha DESC
    LIMIT ?, ?
";

// Contar el total de documentos (Consulta simplificada para evitar error 1064)
try {
    // Usamos una selección simple para el COUNT
    $sql_count_total = "SELECT COUNT(*) FROM (
        (SELECT o.id FROM oficios o LEFT JOIN usuarios u ON o.creado_por = u.id {$where_sql_oficios})
        UNION ALL
        (SELECT c.id FROM constancias c LEFT JOIN usuarios u ON c.creado_por_id = u.id {$where_sql_constancias})
    ) AS subquery_count";
    
    // Parámetros duplicados para el COUNT
    $params_count = array_merge($params_oficios, $params_constancias); 

    $stmt_count = $pdo->prepare($sql_count_total);
    $stmt_count->execute($params_count);
    $total_documentos = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_documentos / $documentos_por_pagina);
    
} catch (PDOException $e) {
    $total_documentos = 0;
    $total_paginas = 1;
    $error_oficios = "Error SQL (Contador): " . $e->getMessage(); 
}


// --- CONSULTA FINAL DE DATOS (CON PAGINACIÓN) ---
try {
    // Parámetros para la consulta final: Filtros (duplicados) + OFFSET + LIMIT
    $params_final = array_merge($params_oficios, $params_constancias); 
    $params_final[] = $offset; // OFFSET (LIMIT start)
    $params_final[] = $documentos_por_pagina; // LIMIT count

    $stmt = $pdo->prepare($union_sql);
    
    // El bindeo se hace por posición, usando el array combinado
    $stmt->execute($params_final);
    $documentos = $stmt->fetchAll();

} catch (PDOException $e) {
    $documentos = [];
    $error_oficios = "Error SQL (Oficios/Constancias): " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="dashboard.php">Registro de Oficios</a>
    
    <button type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="crear_oficio.php">Crear Oficio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="crear_constancia.php">Crear Constancia</a>
            </li>
            <li class="nav-item active">
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
    <h3 class="mb-4">Trámites del Sistema</h3>
    
    <form method="GET" class="form-inline mb-4">
        <input type="text" name="busqueda" class="form-control mr-2" placeholder="Buscar por Nombre/Referencia o Elaborador" value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <a href="dashboard.php" class="btn btn-secondary ml-2">Limpiar Filtro</a>
    </form>

    <?php if (isset($error_oficios) && !empty($error_oficios)): ?>
        <div class="alert alert-danger">Error: <?php echo $error_oficios; ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Tipo Doc.</th>
                    <th>Documento/Referencia</th>
                    <th>Registrado/Difunto</th>
                    <th>Elaborado Por</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($documentos)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No se encontraron documentos con los criterios de búsqueda.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                        // Ordenar el array combinado por la fecha de creación de forma descendente (más reciente primero)
                        usort($documentos, function($a, $b) {
                            $time_a = strtotime($a['fecha']);
                            $time_b = strtotime($b['fecha']);
                            return $time_b <=> $time_a; // Orden descendente
                        });
                    ?>
                    <?php foreach ($documentos as $doc): 
                        $es_oficio = ($doc['tipo_documento'] === 'OFICIO');
                        $id_key = $doc['id'];
                        $referencia = $doc['referencia']; // Trae REF o TIPO_CONSTANCIA
                        $nombre_registro = $doc['registrado_a']; 
                        $estado = $doc['estado_validacion'];
                        $aprobado = ($estado === 'APROBADO');
                        $pendiente = ($estado === 'PENDIENTE');
                        $puede_revisar = ($rol === 'administrador' || $rol === 'supervisor');
                        $es_enviado = (bool)($doc['enviado_correo'] ?? 0);
                        
                        $reimprimir_url = $es_oficio ? 'reimprimir_pdf.php?ref=' . urlencode($referencia) : 'reimprimir_constancia_pdf.php?id=' . $id_key;
                        $editar_url = $es_oficio ? 'editar_oficio.php?id=' . $id_key : 'editar_constancia.php?id=' . $id_key;
                        $habilitar_btn_type = $doc['tipo_documento'];
                        $municipio_id = htmlspecialchars($doc['municipio_destino_id'] ?? 0);
                        
                        $btn_enviar_disabled = ($aprobado && $es_enviado) ? 'disabled' : '';
                        $btn_enviar_text = $es_enviado ? 'Correo Enviado ✅' : 'Enviar Correo';
                    ?>
                        <tr id="doc-row-<?php echo $id_key; ?>">
                            <td><?php echo $doc['tipo_documento']; ?></td>
                            <td><?php echo htmlspecialchars($referencia); ?></td>
                            <td><?php echo htmlspecialchars($nombre_registro); ?></td>
                            <td><?php echo htmlspecialchars($doc['elaborado_por']); ?></td>
                            <td>
                                <?php 
                                    if ($estado === 'APROBADO') {
                                        echo '<span class="badge badge-success">Aprobado</span>';
                                    } elseif ($estado === 'RECHAZADO') {
                                        echo '<span class="badge badge-danger">Rechazado</span>';
                                    } else {
                                        echo '<span class="badge badge-warning">Pendiente</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php if ($pendiente && $puede_revisar): ?>
                                    <button class="btn btn-sm btn-primary btn-habilitar" 
                                            data-id="<?php echo $id_key; ?>" 
                                            data-type="<?php echo $habilitar_btn_type; ?>">
                                        Habilitar PDF
                                    </button>
                                <?php endif; ?>

                                <?php if ($puede_revisar || ($rol === 'normal' && $pendiente)): ?>
                                    <a href="<?php echo $editar_url; ?>" class="btn btn-sm btn-info">
                                        <?php echo ($rol === 'normal' && $pendiente) ? 'Revisar Datos' : 'Editar'; ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($aprobado): ?>
                                    <a href="<?php echo $reimprimir_url; ?>" target="_blank" 
                                       class="btn btn-sm btn-success">Reimprimir PDF</a>
                                    
                                    <button class="btn btn-sm btn-warning btn-send-email" 
                                            id="btn-mail-<?php echo $referencia; ?>"
                                            data-toggle="modal" data-target="#emailModal" 
                                            data-referencia="<?php echo htmlspecialchars($referencia); ?>"
                                            data-municipio-id="<?php echo $municipio_id; ?>" 
                                            <?php echo $btn_enviar_disabled; ?>>
                                        <?php echo $btn_enviar_text; ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($rol === 'administrador'): ?>
                                    <a href="eliminar_documento.php?id=<?php echo $id_key; ?>&type=<?php echo $doc['tipo_documento']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar este documento?');">Eliminar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if ($rol === 'administrador'): ?>
        <hr class="my-5">
        <h3 id="usuarios" class="mb-4">Gestión de Usuarios</h3>
        <a href="crear_usuario.php" class="btn btn-success mb-3">Crear Nuevo Usuario</a>
    <?php endif; ?>
</div>

<div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalLabel">Enviar Oficio por Correo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formEnvioCorreo">
                    <div class="form-group">
                        <label for="modalReferencia">Referencia del Oficio:</label>
                        <input type="text" class="form-control" id="modalReferencia" name="referencia" readonly>
                        <input type="hidden" id="modalMunicipioId" name="municipio_destino_id">
                    </div>
                    <div class="form-group">
                        <label for="modalEmail">Correo del Destinatario (Dejar vacío para usar correo por defecto):</label>
                        <input type="email" class="form-control" id="modalEmail" name="email" placeholder="Ej: destinatario@ejemplo.com">
                    </div>
                    <div id="mensajeEnvio" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnEnviarCorreo">Enviar PDF</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    // 1. Lógica para habilitar el PDF (Oficios y Constancias)
    $('.btn-habilitar').on('click', function() {
        var docId = $(this).data('id');
        var type = $(this).data('type'); 
        var url = (type === 'OFICIO') ? 'habilitar_pdf.php' : 'habilitar_constancia.php'; // Se asume que habilitar_constancia.php existe
        var $btn = $(this);
        
        if (confirm('¿Está seguro de habilitar este documento? Esto lo hará imprimible y observable.')) {
            $btn.prop('disabled', true).text('Procesando...');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: { id: docId },
                success: function(response) {
                    if (response.success) {
                        alert('Documento habilitado exitosamente.');
                        window.location.reload(); 
                    } else {
                        alert('Fallo al habilitar: ' + response.message);
                        $btn.prop('disabled', false).text('Habilitar PDF');
                    }
                },
                error: function() {
                    alert('Error de conexión con el servidor.');
                    $btn.prop('disabled', false).text('Habilitar PDF');
                }
            });
        }
    });

    // 2. Lógica de Modal para Correo (Manejada por AJAX)
    $('#emailModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var referencia = button.data('referencia');
        var municipioId = button.data('municipio-id');
        var modal = $(this);
        
        modal.find('#modalReferencia').val(referencia);
        modal.find('#modalMunicipioId').val(municipioId);
        $('#mensajeEnvio').empty(); 
        $('#modalEmail').val(''); // Limpiar el campo de email manual
    });

    // 3. Manejar el envío de AJAX
    $('#btnEnviarCorreo').on('click', function() {
        var $btn = $(this);
        var referencia = $('#modalReferencia').val();
        var email = $('#modalEmail').val();
        var municipioId = $('#modalMunicipioId').val(); 
        var $mensaje = $('#mensajeEnvio');
        var selectorBoton = 'btn-mail-' + referencia; // Selector basado en la referencia

        if (!referencia) {
             $mensaje.html('<div class="alert alert-warning">Falta la referencia del oficio.</div>');
             return;
        }

        $btn.prop('disabled', true).text('Enviando...');
        $mensaje.empty();

        $.ajax({
            url: 'enviar_pdf.php',
            type: 'POST',
            dataType: 'json',
            data: {
                referencia: referencia,
                email: email, 
                municipio_destino_id: municipioId
            },
            success: function(response) {
                if (response.success) {
                    $mensaje.html('<div class="alert alert-success">' + response.message + '</div>');
                    
                    // LÓGICA CLAVE: DESHABILITAR BOTÓN EN LA TABLA
                    $('#' + selectorBoton).prop('disabled', true).text('Correo Enviado ✅').removeClass('btn-warning').addClass('btn-secondary');
                    
                    setTimeout(function() { $('#emailModal').modal('hide'); }, 2000);
                } else {
                    $mensaje.html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $mensaje.html('<div class="alert alert-danger">Error del servidor al intentar el envío.</div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Enviar PDF');
            }
        });
    });
});
</script>
</body>
</html>