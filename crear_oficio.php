<?php
// Asegura que la sesión esté iniciada y el usuario esté autenticado
include 'check_session.php';
// Incluye la configuración de la base de datos
include 'db_config.php';

// Establece la zona horaria a El Salvador para la hora de inserción
date_default_timezone_set('America/El_Salvador');

// Configura la localización a español para la fecha
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_SV', 'spanish');

// Lógica para generar la referencia correlativa y la fecha
$anio_actual = date('Y');
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM oficios WHERE YEAR(fecha) = ?");
$stmt->execute([$anio_actual]);
$total_oficios = $stmt->fetchColumn();
$nuevo_numero = $total_oficios + 1;
$referencia_correlativa = "REFSSC-" . $anio_actual . "-" . str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);

// Usamos strftime para obtener el mes en español
$fecha_actual = strftime('%d de %B de %Y'); 

// Obtener la lista de departamentos para el primer select
$stmt_depto = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre");
$departamentos = $stmt_depto->fetchAll();

// Obtener el rol y nombre del usuario de la sesión
$rol = $_SESSION['user_rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Oficio</title>
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
            <li class="nav-item active">
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
    <h2 class="text-center mb-4">Crear Nuevo Oficio</h2>
    <form id="oficioForm" action="generar_pdf.php" method="POST" enctype="multipart/form-data"> 
        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Referencia:</label>
                <input type="text" class="form-control" name="referencia" value="<?php echo $referencia_correlativa; ?>" readonly>
            </div>
            <div class="form-group col-md-6">
                <label>Fecha:</label>
                <input type="text" class="form-control" name="fecha" value="<?php echo $fecha_actual; ?>" readonly>
            </div>
        </div>

        <h4 class="mt-4">Datos del Destinatario</h4>
        <div class="form-group">
            <label>Departamento:</label>
            <select class="form-control" id="departamento_destino" name="departamento_destino" required>
                <option value="">Seleccione un departamento</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?php echo $depto['id']; ?>"><?php echo $depto['nombre']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Municipio:</label>
            <select class="form-control" id="municipio_destino" name="municipio_destino" disabled required>
                <option value="">Seleccione un municipio</option>
            </select>
        </div>
        <div class="form-group">
            <label>Distrito:</label>
            <select class="form-control" id="distrito_destino" name="distrito_destino" disabled required>
                <option value="">Seleccione un distrito</option>
            </select>
        </div>
        <div class="form-group">
            <label>Nombre del Licenciado(a):</label>
            <input type="text" class="form-control" id="nombre_licenciado" name="nombre_licenciado" readonly>
        </div>
        <div class="form-group">
            <label>Cargo del Licenciado(a):</label>
            <input type="text" class="form-control" id="cargo_licenciado" name="cargo_licenciado" readonly>
        </div>
        
        <h4 class="mt-4">Datos del Difunto</h4>
        <div class="form-group">
            <label>Nombre del Difunto:</label>
            <input type="text" class="form-control" name="nombre_difunto" required>
        </div>
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Número de Partida:</label>
                <input type="text" class="form-control" name="numero_partida" required>
            </div>
            <div class="form-group col-md-4">
                <label>Folio:</label>
                <input type="text" class="form-control" name="folio" required>
            </div>
            <div class="form-group col-md-4">
                <label>Libro:</label>
                <input type="text" class="form-control" name="libro" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Distrito de Inscripción:</label>
                <select class="form-control" id="distrito_inscripcion" name="distrito_inscripcion">
                    <option value="">Seleccione un distrito</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Año de Inscripción:</label>
                <input type="number" class="form-control" name="anio_inscripcion" value="<?php echo date('Y'); ?>" required>
            </div>
        </div>
        
        <h4 class="mt-4">Lugar de Origen del Difunto</h4>
        <div class="form-group">
            <label>Departamento:</label>
            <select class="form-control" id="departamento_origen" name="departamento_origen" required>
                <option value="">Seleccione un departamento</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?php echo $depto['id']; ?>"><?php echo $depto['nombre']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Municipio:</label>
            <select class="form-control" id="municipio_origen" name="municipio_origen" disabled required>
                <option value="">Seleccione un municipio</option>
            </select>
        </div>
        <div class="form-group">
            <label>Distrito:</label>
            <select class="form-control" id="distrito_origen" name="distrito_origen" disabled required>
                <option value="">Seleccione un distrito</option>
            </select>
        </div>
        
        <h4 class="mt-4">Anexar Documento PDF</h4>
        <div class="form-group">
            <label for="archivo_anexo">Seleccionar PDF a Anexar (Opcional):</label>
            <input type="file" class="form-control-file" id="archivo_anexo" name="archivo_anexo" accept="application/pdf">
            <small class="form-text text-muted">El archivo PDF se fusionará con el oficio generado.</small>
        </div>

        <button type="submit" class="btn btn-success btn-block mt-4">Generar Oficio y PDF</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        // La redirección por JavaScript ha sido ELIMINADA aquí, 
        // ya que el archivo generar_pdf.php ahora maneja la redirección HTTP directa.

        // Carga de distritos de inscripción al cargar la página
        fetch('get_distritos_inscripcion.php')
            .then(response => response.json())
            .then(distritos => {
                const selectElement = $('#distrito_inscripcion');
                distritos.forEach(distrito => {
                    const option = $('<option></option>').val(distrito).text(distrito);
                    selectElement.append(option);
                });
            })
            .catch(error => console.error('Error al cargar distritos:', error));
            
        function loadMunicipios(departamentoId, targetSelect, nextSelect) {
            if (departamentoId) {
                $.ajax({
                    url: 'get_data.php',
                    type: 'POST',
                    data: { action: 'get_municipios', departamento_id: departamentoId },
                    success: function(data) {
                        targetSelect.html('<option value="">Seleccione un municipio</option>' + data).prop('disabled', false).trigger('change');
                        if (nextSelect) {
                            nextSelect.html('<option value="">Seleccione un distrito</option>').prop('disabled', true);
                        }
                    }
                });
            } else {
                targetSelect.html('<option value="">Seleccione un municipio</option>').prop('disabled', true);
                if (nextSelect) {
                    nextSelect.html('<option value="">Seleccione un distrito</option>').prop('disabled', true);
                }
            }
        }

        function loadDistritos(municipioId, targetSelect) {
            if (municipioId) {
                $.ajax({
                    url: 'get_data.php',
                    type: 'POST',
                    data: { action: 'get_distritos', municipio_id: municipioId },
                    success: function(data) {
                        targetSelect.html('<option value="">Seleccione un distrito</option>' + data).prop('disabled', false);
                    }
                });
            } else {
                targetSelect.html('<option value="">Seleccione un distrito</option>').prop('disabled', true);
            }
        }
        
        // Lógica de carga para DESTINATARIO
        $('#departamento_destino').change(function() {
            loadMunicipios($(this).val(), $('#municipio_destino'), $('#distrito_destino'));
        });
        
        // Al seleccionar MUNICIPIO, se cargan los distritos Y los datos del oficiante
        $('#municipio_destino').change(function() {
            var municipioId = $(this).val();
            loadDistritos(municipioId, $('#distrito_destino')); // Carga de distritos

            // Carga de datos del oficiante (Asociado al MUNICIPIO)
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

        // Lógica de carga para LUGAR DE ORIGEN
        $('#departamento_origen').change(function() {
            loadMunicipios($(this).val(), $('#municipio_origen'), $('#distrito_origen'));
        });
        $('#municipio_origen').change(function() {
            loadDistritos($(this).val(), $('#distrito_origen'));
        });
    });
</script>
</body>
</html>