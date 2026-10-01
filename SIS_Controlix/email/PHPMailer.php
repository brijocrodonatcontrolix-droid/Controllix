<?php
// ============================================
// CONFIGURACIÓN DE PHPMailer
// ============================================

require_once '../vendor/autoload.php'; // Si usas Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function enviarCorreo($destinatario, $asunto, $mensajeHTML, $mensajeTexto = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rodriguezonell2005@gmail.com';
        $mail->Password   = 'melilodas';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Remitente y destinatario
        $mail->setFrom('rodriguezonell2005@gmail.com', 'SIS_Controlix');
        $mail->addAddress($destinatario);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHTML;
        $mail->AltBody = $mensajeTexto ?: strip_tags($mensajeHTML);
        
        $mail->send();
        return ['success' => true, 'message' => 'Correo enviado correctamente'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $mail->ErrorInfo];
    }
}
?>