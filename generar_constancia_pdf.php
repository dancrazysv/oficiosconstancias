<?php
// Habilitar la visualización de errores (Quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CRÍTICO: Asegurar que la sesión se inicie antes de cualquier salida o uso de $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_config.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Establecer la zona horaria y localización para PHP
date_default_timezone_set('America/El_Salvador');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es_SV', 'spanish');

// Obtener el ID del usuario logueado
$user_id = $_SESSION['user_id'] ?? 0; 
$user_rol = $_SESSION['user_rol'] ?? 'normal';

// --- VERIFICACIÓN DE SESIÓN ESTRICTA ---
if ($user_id == 0) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: index.php?error=sesion_expirada');
    exit; 
}
// ----------------------------------------


// Función de utilidad para obtener nombres a partir de un ID
function getNombreById($pdo, $tabla, $id) {
    if (empty($id)) return '';
    $stmt = $pdo->prepare("SELECT nombre FROM $tabla WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?? '';
}

/**
 * Mapea el código de la constancia (EJ: NO_REGISTRO_NAC) a un nombre legible.
 * @param string $code
 * @return string
 */
function mapTipoConstancia(string $code): string {
    $map = [
        'NO_REGISTRO_NAC' => 'Constancia de No Registro de Partida de Nacimiento',
        'NO_REGISTRO_DEF' => 'Constancia de No Registro de Defunción',
        'SOLTERIA' => 'Certificación de No Registro de Matrimonio (Soltería)',
        'SOLTERIA_DIV' => 'Certificación de Estado Familiar (Soltería por Divorcio)',
        'NO_REGISTRO_CED' => 'Certificación de No Registro de Cédula de Identidad Personal',
        'NO_REGISTRO_MAT' => 'Certificación de No Registro de Partida de Matrimonio',
    ];
    return $map[$code] ?? 'Constancia de Trámite No Especificado';
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdf_contenido_final = null;
    $ruta_final_almacenada = null;
    
    // Iniciar el buffer de salida CRÍTICO
    ob_start(); 
    
    try {
        // 1. Obtener y sanitizar datos comunes
        $nombre_solicitante = $_POST['nombre_solicitante'] ?? '';
        $tipo_documento_id = $_POST['tipo_documento_id'] ?? '';
        $numero_documento = $_POST['numero_documento'] ?? '';
        $tipo_constancia_code = $_POST['tipo_constancia_id'] ?? 'NO_ESPECIFICADO';
        
        // 2. Obtener datos dinámicos del formulario
        $tipo_soporte_nac = $_POST['nac_tipo_soporte'] ?? NULL; 
        $tipo_soporte_def = $_POST['def_tipo_soporte'] ?? NULL; 
        $nombre_hospital = $_POST['nac_nombre_hospital'] ?? $_POST['def_nombre_hospital'] ?? NULL; 
        
        $fecha_evento_str = $_POST['nac_fecha_nacimiento'] ?? $_POST['def_fecha_defuncion'] ?? NULL; 
        
        $distrito_nacimiento_id = $_POST['nac_distrito_nacimiento_id'] ?? NULL;
        $nombre_madre = $_POST['nac_nombre_madre'] ?? $_POST['def_nombre_madre'] ?? NULL;
        $incluir_padre = isset($_POST['nac_incluir_padre']) || isset($_POST['def_incluir_padre']);
        $nombre_padre = $_POST['nac_nombre_padre'] ?? $_POST['def_nombre_padre'] ?? NULL;
        
        // Datos de Partida de Nacimiento (Soltería/Divorcio)
        $partida_n = $_POST['sol_div_numero_partida'] ?? NULL;
        $folio_n = $_POST['sol_div_folio'] ?? NULL;
        $libro_n = $_POST['sol_div_libro'] ?? NULL;
        $anio_n = $_POST['sol_div_anio'] ?? NULL;


        // 3. CRÍTICO: Determinar el nombre del inscrito que NO se encontró, basado en el TIPO de constancia
        // Se capturan todos los posibles campos de nombre no registrado
        $nombre_no_registro = $_POST['nac_nombre_no_registro'] ?? $_POST['def_nombre_no_registro'] ?? $_POST['sol_div_nombre_inscrito'] ?? $_POST['ced_nombre_no_registro'] ?? $_POST['mat_nombre_no_registro'] ?? '';
        
        // 4. Conversión y Mapeo
        $tipo_documento_nombre = getNombreById($pdo, 'tipos_documento', $tipo_documento_id);
        
        // CRÍTICO: Asegurarse de que el distrito no sea nulo, ni vacío, ni '0'
        $distrito_nacimiento_id = empty($distrito_nacimiento_id) ? NULL : $distrito_nacimiento_id;
        $distrito_nacimiento_nombre = getNombreById($pdo, 'distritos', $distrito_nacimiento_id);

        // --- Manejo de Fechas y Textos ---
        $fecha_emision = date('Y-m-d H:i:s');
        $fecha_evento_texto = $fecha_evento_str ? strftime('%d de %B de %Y', strtotime($fecha_evento_str)) : 'N/A';
        $fecha_emision_texto = strftime('%d de %B del año %Y', strtotime($fecha_emision));
        $fecha_busqueda_corta = strftime('%d', strtotime($fecha_emision)) . " de " . strftime('%B', strtotime($fecha_emision)) . " del presente año";
        
        // Si el nombre no registrado está vacío, le asignamos un valor por defecto para el INSERT (ERROR 1048)
        $nombre_no_registro_para_insert = empty($nombre_no_registro) ? 'NO ESPECIFICADO' : $nombre_no_registro;
        
        // Nombre legible de la constancia para el párrafo introductorio
        $nombre_constancia_legible = mapTipoConstancia($tipo_constancia_code);


        // 5. CONSTRUCCIÓN DEL TEXTO ESPECÍFICO (switch)
        $titulo_constancia_pdf = $nombre_constancia_legible; 
        $parrafo_principal_texto = "";
        $texto_pie_documento = ""; 

        // Variables de ubicación (Solo para el PDF, no para la BDD)
        $municipio_nombre = 'N/A';
        $departamento_nombre = 'N/A';
        $texto_nomenclatura = '';

        if ($distrito_nacimiento_id) {
            $stmt_ubicacion = $pdo->prepare("
                SELECT 
                    m.nombre AS municipio, dpto.nombre AS departamento 
                FROM distritos d
                JOIN municipios m ON d.municipio_id = m.id
                JOIN departamentos dpto ON m.departamento_id = dpto.id
                WHERE d.id = ?
            ");
            $stmt_ubicacion->execute([$distrito_nacimiento_id]);
            $ubicacion = $stmt_ubicacion->fetch(PDO::FETCH_ASSOC);

            if ($ubicacion) {
                $municipio_nombre = $ubicacion['municipio'];
                $departamento_nombre = $ubicacion['departamento'];
                $texto_nomenclatura = "en el Distrito de <strong>{$distrito_nacimiento_nombre}</strong>; Municipio de <strong>{$municipio_nombre}</strong>, <strong>{$departamento_nombre}</strong>";
            }
        }


        switch ($tipo_constancia_code) {
            
            case 'NO_REGISTRO_NAC':
                $parrafo_soporte = '';
                if ($tipo_soporte_nac === 'constancia_rnpn') { $parrafo_soporte = "Certificado de no Registro de Partida de Nacimiento emitido por el Registro Nacional de las Personas Naturales"; }
                elseif (in_array($tipo_soporte_nac, ['constancia_hosp', 'ficha_medica', 'certificado_nac', 'cert_ficha', 'cert_cert'])) {
                     $parrafo_soporte = "Ficha Médica de Nacimiento emitida por " . htmlspecialchars($nombre_hospital);
                } elseif ($tipo_soporte_nac === 'manifestado') { $parrafo_soporte = "Datos Manifestados"; }
                
                $parrafo_filiacion = "siendo hijo(a) de <strong>" . htmlspecialchars($nombre_madre) . "</strong>";
                if (!empty($nombre_padre)) {
                    $parrafo_filiacion .= " y de <strong>" . htmlspecialchars($nombre_padre) . "</strong>.";
                } else {
                    $parrafo_filiacion .= ".";
                }

                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la búsqueda hasta el día **{$fecha_busqueda_corta}**, en los registros de nuestra base de datos que corresponde únicamente al Distrito San Salvador Sede NO aparece registrada ninguna partida de NACIMIENTO a nombre de <strong>{$nombre_no_registro_para_insert}</strong>, según **{$parrafo_soporte}**, nació el día **{$fecha_evento_texto}**, {$texto_nomenclatura}, {$parrafo_filiacion}</p>
                ";
                break;

            case 'NO_REGISTRO_DEF':
                $parrafo_soporte = '';
                if ($tipo_soporte_def === 'esquela_legal') { $parrafo_soporte = "Esquela de Medicina Legal"; }
                elseif (in_array($tipo_soporte_def, ['certificado_hosp', 'constancia_cert'])) { $parrafo_soporte = "Certificado de defunción emitido por **" . htmlspecialchars($nombre_hospital) . "**"; }
                elseif ($tipo_soporte_def === 'certificado_med') { $parrafo_soporte = "Certificado de Defunción Médico Particular"; }
                elseif ($tipo_soporte_def === 'manifestado') { $parrafo_soporte = "Datos Manifestados"; }

                $parrafo_filiacion = "hijo de <strong>" . htmlspecialchars($nombre_madre) . "</strong>";
                if (!empty($nombre_padre)) {
                    $parrafo_filiacion .= " y de <strong>" . htmlspecialchars($nombre_padre) . "</strong>.";
                } else {
                    $parrafo_filiacion .= ".";
                }

                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la respectiva búsqueda en los registros de nuestra base de datos, que corresponde únicamente al Distrito de San Salvador Sede, hasta el día **{$fecha_busqueda_corta}**. NO aparece registrada ninguna PARTIDA DE DEFUNCIÓN a nombre de: <strong>{$nombre_no_registro_para_insert}</strong>, quien, según **{$parrafo_soporte}**, falleció el día **{$fecha_evento_texto}**, {$parrafo_filiacion}.</p>
                ";
                break;

            case 'SOLTERIA':
                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la respectiva búsqueda en los registros de nuestra base de datos que corresponde únicamente al Distrito de San Salvador Sede, hasta el día **{$fecha_busqueda_corta}**. NO aparece registrada ninguna partida de MATRIMONIO ni MARGINACIÓN inscrita en la partida de NACIMIENTO a nombre de <strong>{$nombre_no_registro_para_insert}</strong>, según partida de nacimiento número **{$partida_n}**, folio **{$folio_n}**, del libro **{$libro_n}**, del año **{$anio_n}** del Registro del Estado Familiar de este Distrito.</p>
                ";
                break;

            case 'SOLTERIA_DIV':
                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la respectiva búsqueda en los registros de nuestra base de datos que corresponde únicamente al Distrito de San Salvador Sede, hasta el día **{$fecha_busqueda_corta}**. Se encontró Partida de Nacimiento marginada por MATRIMONIO y DIVORCIO a nombre de: <strong>{$nombre_no_registro_para_insert}</strong>, inscrita con el número **{$partida_n}**, folio **{$folio_n}**, del Libro **{$libro_n}**, del año **{$anio_n}**, del Registro del Estado Familiar del Distrito de San Salvador Sede, San Salvador Centro.</p>
                ";
                $texto_pie_documento = "
                    <p class='leyenda'>
                        DE CONFORMIDAD al Decreto Legislativo número 605 que reforma al artículo 186 del Código de Familia, referente al estado familiar de una persona, se expresa en su ordinal tercero que es “soltera o soltero, quien no ha contraído matrimonio o cuyo matrimonio ha sido anulado o disuelto por divorcio” disposición que entró en vigencia el 1 de marzo del año 2017.  POR TANTO: El Estado Familiar del(la) inscrito(a) es “SOLTERO(A)”.
                    </p>
                ";
                break;

            case 'NO_REGISTRO_CED':
                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la respectiva búsqueda de los registros de nuestra base de datos y archivos que corresponden únicamente al Distrito de San Salvador Sede, en fecha comprendida a partir del 28 de agosto de 1978 hasta el día 31 de octubre de 2002, período en que se extendió la Cédula de Identidad Personal. NO se ha encontrado registro de Cédula de Identidad Personal a nombre de: <strong>{$nombre_no_registro_para_insert}</strong>.</p>
                ";
                break;
                
            case 'NO_REGISTRO_MAT':
                $parrafo_principal_texto = "
                    <p class='content indent'>Habiéndose efectuado la respectiva búsqueda en los registros de nuestra base de datos que corresponde únicamente al Distrito de San Salvador Sede, hasta el día **{$fecha_busqueda_corta}**. NO aparece registrada ninguna Partida de MATRIMONIO a nombre de <strong>{$nombre_no_registro_para_insert}</strong>.</p>
                ";
                break;
                
            default:
                break;
        }

        // 6. Carga de Imágenes y Generación del PDF Base (Interno)
        $escudo_path = 'img/img_logo.png';
        $escudo_base64 = file_exists($escudo_path) ? ('data:image/png;base64,' . base64_encode(file_get_contents($escudo_path))) : ''; 
        $firma_path = 'img/FIRMAKARLACENTRO.png';
        $firma_base64_img = file_exists($firma_path) ? ('<img src="data:image/png;base64,' . base64_encode(file_get_contents($firma_path)) . '" style="width: 250px; height: auto;">') : '';
        
        // --- Carga y Reemplazo de Marcadores ---
        $html = file_get_contents('plantilla_constancia_no_registro.html');
        $replacements = [
            // UNIVERSALES
            '{{nombre_solicitante}}' => htmlspecialchars($nombre_solicitante), 
            '{{tipo_documento_nombre}}' => htmlspecialchars($tipo_documento_nombre),
            '{{numero_documento}}' => htmlspecialchars($numero_documento),
            '{{fecha_emision_texto}}' => $fecha_emision_texto, 
            '{{escudo_img}}' => '<img src="' . $escudo_base64 . '" alt="Logo" class="logo-img">',
            '{{imagen_firma}}' => $firma_base64_img,
            '{{fecha_busqueda_corta}}' => $fecha_busqueda_corta,
            
            // DINÁMICOS
            '{{titulo_constancia}}' => $titulo_constancia_pdf, 
            '{{parrafo_introductorio_dinamico}}' => "a solicitar " . htmlspecialchars($nombre_constancia_legible) . ".", 
            '{{parrafo_principal_texto}}' => $parrafo_principal_texto, 
            '{{leyenda_divorcio}}' => $texto_pie_documento 
        ];
        
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true); 
        $options->set('isRemoteEnabled', true); 
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();
        $pdf_contenido_final = $dompdf->output();


        // 7. Guardado e Inserción en BDD
        $output_filename = 'Constancia_' . $tipo_constancia_code . '_' . date('YmdHis') . '.pdf';
        $carpeta_final = __DIR__ . '/archivos_finales';
        if (!is_dir($carpeta_final)) { mkdir($carpeta_final, 0777, true); }
        
        $ruta_final_servidor = $carpeta_final . '/' . $output_filename;
        file_put_contents($ruta_final_servidor, $pdf_contenido_final);
        $ruta_final_almacenada = 'archivos_finales/' . $output_filename;
        
        $estado_inicial = ($user_rol === 'normal') ? 'PENDIENTE' : 'APROBADO';
        
        // Uso de $nombre_no_registro_para_insert para evitar el error 1048
        $stmt_insert = $pdo->prepare("INSERT INTO constancias (fecha_emision, tipo_constancia, nombre_solicitante, tipo_documento_id, numero_documento, nombre_no_registro, tipo_soporte, nombre_hospital, fecha_nacimiento, distrito_nacimiento_id, nombre_madre, nombre_padre, creado_por_id, estado_validacion, ruta_pdf_final, enviado_correo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([
            $fecha_emision, $tipo_constancia_code, $nombre_solicitante, $tipo_documento_id, 
            $numero_documento, $nombre_no_registro_para_insert, $tipo_soporte_nac, $nombre_hospital, 
            $fecha_evento_str, $distrito_id_para_insert, $nombre_madre, $nombre_padre, 
            $user_id, $estado_inicial, $ruta_final_almacenada, 0
        ]);

        // 8. Salida Final Condicional
        ob_end_clean(); 
        
        if ($user_rol === 'normal') {
            header('Location: dashboard.php?status=constancia_pending');
            exit;
        } else {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $output_filename . '"');
            echo $pdf_contenido_final;
            exit;
        }


    } catch (Exception $e) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        // Si el error es 1048 (NOT NULL), lo reportamos
        if (strpos($e->getMessage(), '1048 Column \'nombre_no_registro\' cannot be null') !== false) {
             die("Error al generar el PDF: SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'nombre_no_registro' cannot be null. Por favor, revise que ha llenado el campo 'No se encontró partida/cédula a nombre de:'");
        }
        die("Error al generar el PDF: " . $e->getMessage());
    }
} else {
    header('Location: crear_constancia.php');
    exit();
}