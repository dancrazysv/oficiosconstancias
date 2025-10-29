<?php
// Incluir archivos necesarios
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 
include 'db_config.php';

header('Content-Type: application/json');

$referencia = $_POST['referencia'] ?? '';
$destinatario_email_manual = $_POST['email'] ?? '';
$municipio_destino_id = $_POST['municipio_destino_id'] ?? null; 

if (empty($referencia)) {
    echo json_encode(['success' => false, 'message' => 'Falta la referencia del oficio.']);
    exit;
}

try {
    // 1. Obtener la ruta del PDF guardado y el nombre del difunto
    $stmt_oficio = $pdo->prepare("SELECT ruta_pdf_final, nombre_difunto FROM oficios WHERE referencia = ?");
    $stmt_oficio->execute([$referencia]);
    $oficio = $stmt_oficio->fetch(PDO::FETCH_ASSOC);

    if (empty($oficio) || empty($oficio['ruta_pdf_final'])) {
        echo json_encode(['success' => false, 'message' => 'El archivo PDF no se encontró en el servidor.']);
        exit;
    }

    $ruta_absoluta = __DIR__ . '/' . $oficio['ruta_pdf_final'];
    $nombre_archivo = basename($ruta_absoluta);

    // 2. Determinar el correo del Destinatario
    $correo_destino = $destinatario_email_manual; 

    // Si el usuario no proporcionó un email, buscar el correo por defecto del oficiante
    if (empty($correo_destino) && $municipio_destino_id) {
        $stmt_correo = $pdo->prepare("SELECT email FROM oficiantes WHERE municipio_id = ?");
        $stmt_correo->execute([$municipio_destino_id]);
        $correo_db = $stmt_correo->fetchColumn();
        if ($correo_db) {
            $correo_destino = $correo_db;
        }
    }
    
    if (empty($correo_destino)) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionó una dirección de correo válida para el envío.']);
        exit;
    }


    // 3. Configuración y Envío de Correo (PHPMailer - Configuración Gmail)
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Host de Gmail
    $mail->SMTPAuth = true;
    
    // *** CLAVES PARA EL ACCESO ***
    $mail->Username = 'hdezd2499@gmail.com'; // <--- CAMBIA ESTO
    $mail->Password = 'oeqt ripp ktjg pixo'; // <--- CLAVE DE APLICACIÓN GENERADA
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8'; // Crucial para caracteres en español

    $mail->setFrom('hdezd2499@gmail.com', 'Registro de Oficios'); // Debe ser el mismo Username
    $mail->addAddress($correo_destino);
    $mail->addAttachment($ruta_absoluta, $nombre_archivo);

    $mail->isHTML(true);
    $mail->Subject = 'Oficio de Certificación REF: ' . $referencia;
    $mail->Body    = "Estimado usuario,<br><br>Se adjunta el Oficio de Certificación con referencia <b>$referencia</b>, correspondiente a **" . htmlspecialchars($oficio['nombre_difunto']) . "**.<br><br>Este documento es para su archivo y control.<br><br>Atentamente,<br>Sistema de Registro de Oficios.";
    $mail->AltBody = "Se adjunta el Oficio de Certificación con referencia $referencia.";

    $mail->send();
    
    // 4. Marcar el Oficio como Enviado en la BDD
    $pdo->prepare("UPDATE oficios SET enviado_correo = 1 WHERE referencia = ?")->execute([$referencia]);

    echo json_encode(['success' => true, 'message' => 'El oficio ha sido enviado correctamente a ' . $correo_destino, 'referencia' => $referencia]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Error al enviar el correo. Por favor, verifique el correo del destinatario y su contraseña de aplicación. Error: {$mail->ErrorInfo}"]);
}