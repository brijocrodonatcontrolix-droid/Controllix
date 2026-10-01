<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $usuarios = fetchAll("
                SELECT 
                    U.*,
                    R.nombreRol
                FROM Usuarios U
                LEFT JOIN Roles R ON U.idRol = R.idRol
                ORDER BY U.idUsuario
            ");
            echo json_encode(['success' => true, 'data' => $usuarios]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $usuario = fetchOne("SELECT * FROM Usuarios WHERE idUsuario = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $usuario]);
            break;

        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            $usuario = fetchOne("SELECT * FROM Usuarios WHERE usuario = ? AND estado = 1", [$data['usuario']]);
            
            if ($usuario && password_verify($data['password'], $usuario['password'])) {
                unset($usuario['password']);
                echo json_encode(['success' => true, 'data' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>