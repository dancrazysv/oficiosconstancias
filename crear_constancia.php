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

// Establece la zona horaria a El Salvador
date_default_timezone_set('America/El_Salvador');

// Obtener datos para selectores
try {
    $stmt_doc = $pdo->query("SELECT id, nombre FROM tipos_documento ORDER BY nombre");
    $tipos_documento = $stmt_doc->fetchAll(PDO::FETCH_ASSOC);

    // CRÍTICO: 1. Obtener todos los DEPARTAMENTOS
    $stmt_depto = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre");
    $departamentos = $stmt_depto->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error de base de datos al cargar datos iniciales: " . $e->getMessage());
}

// Obtener el rol y nombre del usuario de la sesión
$rol = $_SESSION['user_rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Constancia</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; max-width: 800px; padding: 30px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-section { border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .form-group-toggle { margin-bottom: 5px; }
    </style>
</head>
<body>

<datalist id="lista_hospitales">
    </datalist>

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
            <li class="nav-item active">
                <a class="nav-link" href="crear_constancia.php">Crear Constancia</a>
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
    <h2>Crear Constancia</h2>
    <form id="constanciaForm" action="generar_constancia_pdf.php" method="POST">
        
        <h4 class="mt-4">Tipo de Trámite</h4>
        <div class="form-group">
            <label for="tipo_constancia">Seleccione la Constancia a Emitir:</label>
            <select class="form-control" id="tipo_constancia" name="tipo_constancia_id" required>
                <option value="">-- Seleccione una opción --</option>
                <option value="NO_REGISTRO_NAC">Constancia de NO Registro de Partida de Nacimiento</option>
                <option value="NO_REGISTRO_DEF">Constancia de NO Registro de Defunción</option>
                <option value="SOLTERIA">Constancia de Soltería</option>
                <option value="SOLTERIA_DIV">Constancia de Soltería por Divorcio</option>
                <option value="NO_REGISTRO_CED">Constancia de NO Registro de Cédula</option>
                <option value="NO_REGISTRO_MAT">Constancia de NO Registro de Matrimonio</option>
            </select>
        </div>
        
        <h4 class="mt-4">Datos del Solicitante</h4>
        <div class="form-group">
            <label for="nombre_solicitante">A esta oficina se presentó (Ciudadano):</label>
            <input type="text" class="form-control" id="nombre_solicitante" name="nombre_solicitante" required readonly>
            <small id="nombre_encontrado_msg" class="text-muted">Busque al solicitante por documento.</small>
        </div>
        
        <div class="form-row align-items-end">
            <div class="form-group col-md-4">
                <label>Tipo de Documento:</label>
                <select class="form-control" id="tipo_documento_id" name="tipo_documento_id" required>
                    <option value="">-- Seleccione Tipo --</option>
                    <?php foreach ($tipos_documento as $tipo): ?>
                        <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group col-md-5">
                <label>Número de Documento:</label>
                <input type="text" class="form-control" id="numero_documento" name="numero_documento" required>
            </div>
            <div class="form-group col-md-3">
                <button type="button" class="btn btn-primary btn-block" id="btnBuscarSolicitante">Buscar</button>
            </div>
        </div>

        <div class="form-group text-right">
             <button type="button" class="btn btn-warning btn-sm" id="btnGuardarSolicitante" style="display: none;">
                Guardar Solicitante Nuevo
            </button>
        </div>
        
        <div id="campos_dinamicos">

            <div class="form-section" id="seccion_nacimiento" style="display: none;">
                <h4 class="mt-2">Datos de Nacimiento</h4>
                
                <div class="form-group">
                    <label>No se encontró partida de nacimiento a nombre de:</label>
                    <input type="text" class="form-control" name="nac_nombre_no_registro">
                </div>

                <div class="form-group form-group-toggle">
                    <label for="nac_tipo_soporte">Según:</label>
                    <select class="form-control" id="nac_tipo_soporte" name="nac_tipo_soporte">
                        <option value="constancia_rnpn">1. Constancia de no registro RNPN</option>
                        <option value="constancia_hosp">2. Constancia Hospitalaria</option>
                        <option value="ficha_medica">3. Ficha Médica de Nacimiento</option>
                        <option value="certificado_nac">4. Certificado de Nacimiento</option>
                        <option value="cert_ficha">5. Certificación de Ficha Médica de Nacimiento</option>
                        <option value="cert_cert">6. Certificación de Certificado de Nacimiento</option>
                        <option value="manifestado">7. Datos Manifestados</option>
                    </select>
                </div>

                <div class="form-group" id="nac_contenedor_hospital" style="display: none;">
                    <label for="nac_nombre_hospital">Emitido por (Hospital/Clínica):</label>
                    <input class="form-control" list="lista_hospitales" id="nac_nombre_hospital" name="nac_nombre_hospital">
                </div>
                
                <div id="nac_campos_dinamicos_filiacion">
                    <div class="form-group">
                        <label for="nac_fecha_nacimiento">Nació el día (Seleccionar Fecha):</label>
                        <input type="date" class="form-control" id="nac_fecha_nacimiento" name="nac_fecha_nacimiento">
                        <div class="form-check nac-opcional-item" style="display: none;">
                            <input type="checkbox" class="form-check-input nac_opt_check" id="nac_check_fecha_opcional" data-target="nac_fecha_nacimiento" data-type="input">
                            <label class="form-check-label" for="nac_check_fecha_opcional">Omitir Fecha (Datos Manifestados)</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="nac_departamento_id">Departamento:</label>
                            <select class="form-control" id="nac_departamento_id" name="nac_departamento_id">
                                <option value="">-- Seleccione Dpto --</option>
                                <?php foreach ($departamentos as $depto): ?>
                                    <option value="<?php echo $depto['id']; ?>"><?php echo htmlspecialchars($depto['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="nac_municipio_id">Municipio:</label>
                            <select class="form-control" id="nac_municipio_id" name="nac_municipio_id" disabled>
                                <option value="">-- Seleccione Mpio --</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="nac_distrito_nacimiento_id">Distrito de Nacimiento:</label>
                            <select class="form-control" id="nac_distrito_nacimiento_id" name="nac_distrito_nacimiento_id" disabled>
                                <option value="">-- Seleccione Distrito --</option>
                            </select>
                            <div class="form-check nac-opcional-item" style="display: none;">
                                <input type="checkbox" class="form-check-input nac_opt_check" id="nac_check_distrito_opcional" data-target="nac_distrito_nacimiento_id" data-type="select">
                                <label class="form-check-label" for="nac_check_distrito_opcional">Omitir Distrito (Datos Manifestados)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nac_nombre_madre">Hijo/a de (Madre):</label>
                        <input type="text" class="form-control" id="nac_nombre_madre" name="nac_nombre_madre">
                        <div class="form-check nac-opcional-item" style="display: none;">
                            <input type="checkbox" class="form-check-input nac_opt_check" id="nac_check_madre_opcional" data-target="nac_nombre_madre" data-type="input">
                            <label class="form-check-label" for="nac_check_madre_opcional">Omitir Madre (Datos Manifestados)</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="nac_check_padre" name="nac_incluir_padre">
                    <label class="form-check-label" for="nac_check_padre">Incluir Nombre del Padre</label>
                </div>
                <div class="form-group" id="nac_contenedor_padre" style="display: none;">
                    <label for="nac_nombre_padre">Nombre del Padre:</label>
                    <input type="text" class="form-control" id="nac_nombre_padre" name="nac_nombre_padre">
                </div>
            </div>

            <div class="form-section" id="seccion_defuncion" style="display: none;">
                <h4 class="mt-2">Datos de Defunción</h4>
                
                <div class="form-group">
                    <label>No se encontró partida de defunción a nombre de:</label>
                    <input type="text" class="form-control" name="def_nombre_no_registro">
                </div>
                
                <div class="form-group form-group-toggle">
                    <label for="def_tipo_soporte">Según:</label>
                    <select class="form-control" id="def_tipo_soporte" name="def_tipo_soporte">
                        <option value="esquela_legal">1. Esquela de Medicina Legal</option>
                        <option value="certificado_hosp">2. Certificado de Defunción Hospitalario</option>
                        <option value="certificado_med">3. Certificado de Defunción Médico Particular</option>
                        <option value="constancia_cert">4. Constancia de Certificado de Defunción Hospitalaria</option>
                        <option value="manifestado">5. Datos Manifestados</option>
                    </select>
                </div>
                
                <div class="form-group" id="def_contenedor_hospital" style="display: none;">
                    <label for="def_nombre_hospital">Emitido por (Hospital/Clínica):</label>
                    <input class="form-control" list="lista_hospitales" id="def_nombre_hospital" name="def_nombre_hospital">
                </div>
                
                <div id="def_campos_dinamicos_fecha">
                    <div class="form-group">
                        <label for="def_fecha_defuncion">Falleció el día (Seleccionar Fecha):</label>
                        <input type="date" class="form-control" id="def_fecha_defuncion" name="def_fecha_defuncion">
                        <div class="form-check def-opcional-item" style="display: none;">
                            <input type="checkbox" class="form-check-input def_opt_check" id="def_check_fecha_opcional" data-target="def_fecha_defuncion" data-type="input">
                            <label class="form-check-label" for="def_check_fecha_opcional">Omitir Fecha (Datos Manifestados)</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="def_departamento_id">Departamento:</label>
                        <select class="form-control" id="def_departamento_id" name="def_departamento_id">
                            <option value="">-- Seleccione Dpto --</option>
                            <?php foreach ($departamentos as $depto): ?>
                                <option value="<?php echo $depto['id']; ?>"><?php echo htmlspecialchars($depto['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="def_municipio_id">Municipio:</label>
                        <select class="form-control" id="def_municipio_id" name="def_municipio_id" disabled>
                            <option value="">-- Seleccione Mpio --</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="def_distrito_defuncion_id">Distrito de Defunción:</label>
                        <select class="form-control" id="def_distrito_defuncion_id" name="def_distrito_defuncion_id" disabled>
                            <option value="">-- Seleccione Distrito --</option>
                        </select>
                    </div>
                </div>


                <h4 class="mt-4">Datos de Filiación</h4>
                <div class="form-group">
                    <label>Hijo/a de (Madre):</label>
                    <input type="text" class="form-control" id="def_nombre_madre" name="def_nombre_madre">
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="def_check_padre" name="def_incluir_padre">
                    <label class="form-check-label" for="def_check_padre">Incluir Nombre del Padre</label>
                </div>
                <div class="form-group" id="def_contenedor_padre" style="display: none;">
                    <label for="def_nombre_padre">Nombre del Padre:</label>
                    <input type="text" class="form-control" id="def_nombre_padre" name="def_nombre_padre">
                </div>
            </div>

            <div class="form-section" id="seccion_solteria_base" style="display: none;">
                <h4 class="mt-2" id="solteria_titulo">Datos de Soltería</h4>
                
                <div class="form-group">
                    <label id="solteria_label"></label>
                    <input type="text" class="form-control" name="sol_div_nombre_inscrito" id="sol_div_nombre_inscrito">
                </div>

                <h5 class="mt-4">Según Partida de Nacimiento:</h5>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Partida N°:</label>
                        <input type="text" class="form-control" name="sol_div_numero_partida" id="sol_div_numero_partida">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Folio N°:</label>
                        <input type="text" class="form-control" name="sol_div_folio" id="sol_div_folio">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Libro N°:</label>
                        <input type="text" class="form-control" name="sol_div_libro" id="sol_div_libro">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Año:</label>
                        <input type="number" class="form-control" name="sol_div_anio" id="sol_div_anio">
                    </div>
                </div>
            </div>
            
            <div class="form-section" id="seccion_no_cedula" style="display: none;">
                <h4 class="mt-2">Datos de Búsqueda</h4>
                <div class="form-group">
                    <label>NO se ha encontrado registro de Cédula de Identidad Personal a nombre de:</label>
                    <input type="text" class="form-control" name="ced_nombre_no_registro" id="ced_nombre_no_registro">
                </div>
            </div>
            
            <div class="form-section" id="seccion_no_matrimonio" style="display: none;">
                <h4 class="mt-2">Datos de Búsqueda</h4>
                <div class="form-group">
                    <label>NO aparece registrada ninguna Partida de MATRIMONIO a nombre de:</label>
                    <input type="text" class="form-control" name="mat_nombre_no_registro">
                </div>
            </div>
            
        </div>
        
        <button type="submit" class="btn btn-success btn-block mt-4" id="btnGenerarConstancia" disabled>Generar Constancia</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    
    // --- LÓGICA AJAX PARA CARGAR HOSPITALES (Autocomplete) ---
    $.ajax({
        url: 'get_data_constancia.php', 
        type: 'POST',
        data: { action: 'get_hospitales' },
        success: function(data) {
            // Inyecta el HTML de las opciones en el datalist global
            $('#lista_hospitales').html(data);
        },
        error: function(xhr, status, error) {
            console.error("Error al cargar la lista de hospitales: ", status, error);
        }
    });
    
    // --- LÓGICA DE CARGA JERÁRQUICA DE UBICACIÓN (CORREGIDA) ---

    // Función para limpiar y deshabilitar selects
    function resetSelects(prefix, municipio = true, distrito = true) {
        if (municipio) {
            $(`#${prefix}_municipio_id`).html('<option value="">-- Seleccione Mpio --</option>').prop('disabled', true).val('');
        }
        if (distrito) {
            $(`#${prefix}_distrito_nacimiento_id, #${prefix}_distrito_defuncion_id`).html('<option value="">-- Seleccione Distrito --</option>').prop('disabled', true).val('');
        }
        aplicarRequisitos();
    }

    // CRÍTICO 1: Carga de municipios
    $('#nac_departamento_id, #def_departamento_id').on('change', function() {
        const deptoId = $(this).val();
        const prefix = this.id.startsWith('nac') ? 'nac' : 'def';
        resetSelects(prefix, true, true);

        if (deptoId) {
            $.ajax({
                url: 'get_data_constancia.php',
                type: 'POST',
                data: { action: 'get_municipios', depto_id: deptoId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.municipios.length > 0) {
                        let options = '<option value="">-- Seleccione Mpio --</option>';
                        response.municipios.forEach(m => {
                            options += `<option value="${m.id}">${m.nombre}</option>`;
                        });
                        $(`#${prefix}_municipio_id`).html(options).prop('disabled', false);
                    } else {
                        console.error('No se encontraron municipios o la respuesta fue inválida.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`Error cargando municipios para ${prefix}:`, status, error);
                }
            });
        }
    });

    // CRÍTICO 2: Carga de distritos
    $('#nac_municipio_id, #def_municipio_id').on('change', function() {
        const municipioId = $(this).val();
        const prefix = this.id.startsWith('nac') ? 'nac' : 'def';
        const targetDistritoId = (prefix === 'nac') ? '#nac_distrito_nacimiento_id' : '#def_distrito_defuncion_id';

        // Limpiar solo el select de distrito relevante
        $(targetDistritoId).html('<option value="">-- Seleccione Distrito --</option>').prop('disabled', true).val(''); 

        if (municipioId) {
            $.ajax({
                url: 'get_data_constancia.php',
                type: 'POST',
                data: { action: 'get_distritos', municipio_id: municipioId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.distritos.length > 0) {
                        let options = '<option value="">-- Seleccione Distrito --</option>';
                        response.distritos.forEach(d => {
                            options += `<option value="${d.id}">${d.nombre}</option>`;
                        });
                        $(targetDistritoId).html(options).prop('disabled', false);
                    } else {
                        console.error('No se encontraron distritos o la respuesta fue inválida.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error cargando distritos:', status, error);
                }
            });
        }
        aplicarRequisitos();
    });
    
    // --- FUNCIÓN PRINCIPAL DE APLICAR REQUISITOS ---
    function aplicarRequisitos() {
        // 1. Quitar el atributo 'required' de todos los campos dinámicos
        $('#campos_dinamicos').find('input:not([type="checkbox"]), select').prop('required', false).removeClass('is-invalid');
        
        // 2. Obtener el tipo de constancia actual
        const tipo = $('#tipo_constancia').val();
        
        // 3. Aplicar 'required' a los campos visibles y no omitidos
        if (tipo === 'NO_REGISTRO_NAC') {
            const isManifestado = ($('#nac_tipo_soporte').val() === 'manifestado');
            const requiredNacFields = ['nac_nombre_no_registro', 'nac_tipo_soporte', 'nac_departamento_id', 'nac_municipio_id', 'nac_distrito_nacimiento_id'];

            // Reglas de Requerimiento
            if (isManifestado) {
                if (!$('#nac_check_fecha_opcional').is(':checked')) requiredNacFields.push('nac_fecha_nacimiento');
                if (!$('#nac_check_distrito_opcional').is(':checked')) requiredNacFields.push('nac_distrito_nacimiento_id');
                if (!$('#nac_check_madre_opcional').is(':checked')) requiredNacFields.push('nac_nombre_madre');
            } else {
                 // Si NO es manifestado, los 3 campos son SIEMPRE requeridos
                 requiredNacFields.push('nac_fecha_nacimiento', 'nac_distrito_nacimiento_id', 'nac_nombre_madre');
            }
            
            // Si requiere hospital (Ítems 2 a 6)
            const soporteNac = $('#nac_tipo_soporte').val();
            const requiresHospital = (soporteNac !== 'constancia_rnpn' && soporteNac !== 'manifestado');
            if (requiresHospital) {
                 requiredNacFields.push('nac_nombre_hospital');
            }
            
            // Aplicar los 'required'
            requiredNacFields.forEach(name => $(`[name="${name}"]`).prop('required', true));
            if($('#nac_check_padre').is(':checked')) { $(`[name="nac_nombre_padre"]`).prop('required', true); }

        } else if (tipo === 'NO_REGISTRO_DEF') {
            const isManifestado = ($('#def_tipo_soporte').val() === 'manifestado');
            const requiredDefFields = ['def_nombre_no_registro', 'def_tipo_soporte', 'def_nombre_madre', 'def_departamento_id', 'def_municipio_id', 'def_distrito_defuncion_id'];
            
            // Fecha (opcional solo si Manifestado está chequeado)
            if (!isManifestado || !$('#def_check_fecha_opcional').is(':checked')) {
                 requiredDefFields.push('def_fecha_defuncion');
            }
            
            // Hospital
            const requiereHospitalDef = ($('#def_tipo_soporte').val() === 'certificado_hosp' || $('#def_tipo_soporte').val() === 'constancia_cert');
            if (requiereHospitalDef) {
                 requiredDefFields.push('def_nombre_hospital');
            }
            
            requiredDefFields.forEach(name => $(`[name="${name}"]`).prop('required', true));
            if($('#def_check_padre').is(':checked')) { $(`[name="def_nombre_padre"]`).prop('required', true); }
            
        } else if (tipo === 'SOLTERIA' || tipo === 'SOLTERIA_DIV') {
            // Campos de Soltería y Divorcio son siempre requeridos
            ['sol_div_nombre_inscrito', 'sol_div_numero_partida', 'sol_div_folio', 'sol_div_libro', 'sol_div_anio']
                .forEach(name => $(`[name="${name}"]`).prop('required', true));
                
        } else if (tipo === 'NO_REGISTRO_CED') {
            $('[name="ced_nombre_no_registro"]').prop('required', true); // CRÍTICO: Requerido
            
        } else if (tipo === 'NO_REGISTRO_MAT') {
            $('[name="mat_nombre_no_registro"]').prop('required', true); // CRÍTICO: Requerido
        }
        
        // 4. Control del botón Generar
        const isConstanciaSelected = $('#tipo_constancia').val() !== '';
        const isSolicitanteReady = $('#nombre_solicitante').val().trim() !== '';
        
        // Verificar si TODOS los campos requeridos visibles están llenos
        const allRequiredFilled = $('#campos_dinamicos').find('input[required]:visible, select[required]:visible').filter(function() {
             return $(this).val().trim() === '';
        }).length === 0;

        if (isConstanciaSelected && isSolicitanteReady && allRequiredFilled) {
             $('#btnGenerarConstancia').prop('disabled', false).text('Generar Constancia');
        } else {
             $('#btnGenerarConstancia').prop('disabled', true).text('Genere Constancia');
        }
        
        // Control del botón Guardar Solicitante
        if ($('#nombre_solicitante').val().trim() !== '' && !$('#nombre_solicitante').prop('readonly')) {
            $('#btnGuardarSolicitante').show().prop('disabled', false);
        } else {
            $('#btnGuardarSolicitante').hide();
        }
    }
    
    // --- LÓGICA DE BÚSQUEDA DEL SOLICITANTE ---
    $('#btnBuscarSolicitante').on('click', function() {
        const tipoDocId = $('#tipo_documento_id').val();
        const documento = $('#numero_documento').val();
        const nombreSolicitanteInput = $('#nombre_solicitante');
        const nombreActual = nombreSolicitanteInput.val().trim(); // Valor actual, sea readonly o no

        if (!tipoDocId || !documento) {
            alert('Por favor, seleccione el Tipo y Número de Documento.');
            return;
        }

        // Llamada AJAX a get_data_constancia.php
        $.ajax({
            url: 'get_data_constancia.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'buscar_solicitante', 
                tipo_documento_id: tipoDocId, 
                numero_documento: documento,
                nombre_solicitante_manual: nombreSolicitanteInput.prop('readonly') ? '' : nombreActual
            },
            success: function(response) {
                if (response.success) {
                    // Si se encuentra O se registra
                    nombreSolicitanteInput.val(response.nombre).prop('readonly', true);
                    $('#nombre_encontrado_msg').html('<strong class="text-success">✅ Ciudadano encontrado/registrado.</strong>');
                    $('#btnGuardarSolicitante').hide(); // Ocultar el botón una vez guardado/encontrado
                } else {
                    // Si no se encontró (y no se ha digitado el nombre, o hubo error de búsqueda)
                    nombreSolicitanteInput.val(nombreActual).prop('readonly', false).focus(); // Mantener el nombre si lo había
                    $('#nombre_encontrado_msg').html('<strong class="text-danger">❌ Ciudadano no encontrado. Ingrese el nombre manualmente para continuar.</strong>');
                }
                aplicarRequisitos();
            },
            error: function() {
                alert('Error de comunicación con el servidor de búsqueda.');
            }
        });
    });

    // --- LÓGICA PARA GUARDAR SOLICITANTE MANUALMENTE ---
    $('#btnGuardarSolicitante').on('click', function() {
        const tipoDocId = $('#tipo_documento_id').val();
        const documento = $('#numero_documento').val();
        const nombre = $('#nombre_solicitante').val().trim();
        
        if (!nombre || !tipoDocId || !documento) {
            alert('Por favor, complete los campos de Documento y Nombre del Solicitante.');
            return;
        }
        
        // Llamada AJAX para forzar el registro 
        $.ajax({
            url: 'get_data_constancia.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'buscar_solicitante', 
                tipo_documento_id: tipoDocId, 
                numero_documento: documento,
                nombre_solicitante_manual: nombre 
            },
            success: function(response) {
                if (response.success) {
                    $('#nombre_solicitante').val(response.nombre).prop('readonly', true);
                    $('#nombre_encontrado_msg').html('<strong class="text-success">✅ Solicitante guardado y listo.</strong>');
                    $('#btnGuardarSolicitante').hide(); 
                } else {
                    $('#nombre_encontrado_msg').html('<strong class="text-danger">Error: No se pudo guardar el solicitante.</strong>');
                }
                aplicarRequisitos();
            },
            error: function() {
                alert('Error de comunicación con el servidor al intentar guardar.');
            }
        });
    });


    // --- FUNCIÓN PRINCIPAL: MOSTRAR/OCULTAR SECCIONES ---
    function toggleFields(tipo) {
        // 1. Ocultar todas las secciones
        $('.form-section').hide();
        
        // 2. Mostrar la sección correspondiente y definir requisitos
        if (tipo === 'NO_REGISTRO_NAC') {
            $('#seccion_nacimiento').show();
            $('#nac_tipo_soporte').trigger('change'); 

        } else if (tipo === 'NO_REGISTRO_DEF') {
            $('#seccion_defuncion').show();
            $('#def_tipo_soporte').trigger('change');

        } else if (tipo === 'SOLTERIA' || tipo === 'SOLTERIA_DIV') {
            $('#seccion_solteria_base').show();
            const esDivorcio = (tipo === 'SOLTERIA_DIV');
            $('#solteria_titulo').text(esDivorcio ? 'Constancia de Soltería por Divorcio' : 'Constancia de Soltería');
            $('#solteria_label').text(esDivorcio ? 'Se encontró Partida de Nacimiento marginada por MATRIMIO y DIVORCIO a nombre de:' : 'NO aparece registrada ninguna partida de MATRIMIO ni MARGINACIÓN inscrita en la partida de NACIMIENTO a nombre de:');

        } else if (tipo === 'NO_REGISTRO_CED') {
            $('#seccion_no_cedula').show();
        } else if (tipo === 'NO_REGISTRO_MAT') {
            $('#seccion_no_matrimonio').show();
        }
        
        aplicarRequisitos();
    }
    
    // --- LÓGICA DE SOPORTE NACIMIENTO ---
    $('#nac_tipo_soporte').on('change', function() {
        const soporte = $(this).val();
        const contenedorHospital = $('#nac_contenedor_hospital');
        const isManifestado = (soporte === 'manifestado');
        const requiresHospital = (soporte !== 'constancia_rnpn' && !isManifestado);
        
        // 1. Mostrar/Ocultar Hospital
        if (requiresHospital) {
            contenedorHospital.show().find('input').prop('required', true);
        } else {
            contenedorHospital.hide().find('input').prop('required', false).val('');
        }
        
        // 2. Control de Campos Opcionales (solo si es Datos Manifestados)
        $('.nac-opcional-item').toggle(isManifestado);
        
        if (!isManifestado) {
            // Si NO es manifestado, los campos son requeridos y los checks desmarcados/deshabilitados.
            $('.nac_opt_check').prop('checked', false).prop('disabled', true);
            $('[name="nac_fecha_nacimiento"], [name="nac_distrito_nacimiento_id"], [name="nac_nombre_madre"]').prop('required', true).prop('disabled', false);
        } else {
            // Si es manifestado, los campos son opcionales por defecto (sin required), y los checks están activos.
            $('[name="nac_fecha_nacimiento"], [name="nac_distrito_nacimiento_id"], [name="nac_nombre_madre"]').prop('required', false).prop('disabled', false);
            $('.nac_opt_check').prop('disabled', false);
        }
        
        aplicarRequisitos();
    });
    
    // Manejo de Checkboxes de Omisión (Datos Manifestados - NACIMIENTO)
    $('.nac_opt_check').on('change', function() {
        const targetName = $(this).data('target');
        const $targetField = $(`[name="${targetName}"]`);

        if ($(this).is(':checked')) {
            $targetField.val('').prop('required', false).prop('disabled', true);
        } else {
            $targetField.prop('disabled', false);
            if ($('#nac_tipo_soporte').val() === 'manifestado') {
                $targetField.prop('required', true);
            }
        }
        aplicarRequisitos();
    });


    // --- LÓGICA DE SOPORTE DEFUNCIÓN ---
    $('#def_tipo_soporte').on('change', function() {
        const soporte = $(this).val();
        const contenedorHospital = $('#def_contenedor_hospital');
        const isManifestado = (soporte === 'manifestado');
        const requiresHospital = (soporte === 'certificado_hosp' || soporte === 'constancia_cert');

        // 1. Mostrar/Ocultar Hospital
        if (requiresHospital) {
            contenedorHospital.show().find('input').prop('required', true);
        } else {
            contenedorHospital.hide().find('input').prop('required', false).val('');
        }
        
        // 2. Control de Campos Opcionales (Solo Fecha si es Datos Manifestados)
        $('.def-opcional-item').toggle(isManifestado);

        // 3. Aplicar Requisitos de Fecha
        if (!isManifestado) {
            $('[name="def_fecha_defuncion"]').prop('required', true).prop('disabled', false);
            $('.def_opt_check').prop('checked', false).prop('disabled', true);
        } else {
            $('[name="def_fecha_defuncion"]').prop('required', false).prop('disabled', false);
            $('.def_opt_check').prop('disabled', false);
        }
        
        aplicarRequisitos();
    });
    
    // Manejo de Checkboxes de Omisión (Datos Manifestados - DEFUNCIÓN)
    $('.def_opt_check').on('change', function() {
        const targetName = $(this).data('target');
        const $targetField = $(`[name="${targetName}"]`);

        if ($(this).is(':checked')) {
            $targetField.val('').prop('required', false).prop('disabled', true);
        } else {
            $targetField.prop('disabled', false);
            if ($('#def_tipo_soporte').val() === 'manifestado') {
                $targetField.prop('required', true);
            }
        }
        aplicarRequisitos();
    });


    // Lógica de check padre para ambas secciones
    $('#nac_check_padre').on('change', function() {
        if ($(this).is(':checked')) {
            $('#nac_contenedor_padre').show().find('input').prop('required', true);
        } else {
            $('#nac_contenedor_padre').hide().find('input').prop('required', false).val('');
        }
    });
    $('#def_check_padre').on('change', function() {
        if ($(this).is(':checked')) {
            $('#def_contenedor_padre').show().find('input').prop('required', true);
        } else {
            $('#def_contenedor_padre').hide().find('input').prop('required', false).val('');
        }
    });

    // Evento principal
    $('#tipo_constancia').on('change', function() {
        toggleFields($(this).val());
        $('#nombre_solicitante').val('').prop('readonly', true); // Limpiar solicitante al cambiar
        $('#btnGenerarConstancia').prop('disabled', true);
        $('#nombre_encontrado_msg').empty();
        $('#btnGuardarSolicitante').hide();
    });
    
    // Disparar carga inicial
    toggleFields($('#tipo_constancia').val()); 
    
    
    // --- LÓGICA DE CAMBIOS EN INPUTS (para habilitar el botón) ---
    $('#nombre_solicitante, #numero_documento, #tipo_documento_id').on('input change', aplicarRequisitos);
    $('#campos_dinamicos').on('input change', 'input, select', aplicarRequisitos);
});
</script>
</body>
</html>