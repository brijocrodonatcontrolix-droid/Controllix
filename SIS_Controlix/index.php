<?php
// Redirigir al login o al dashboard según sesión
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/menu_de_inicio.php');
} else {
    header('Location: pages/login.php');
}
exit();
?>