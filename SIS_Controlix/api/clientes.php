<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $clientes = fetchAll("SELECT * FROM Clientes ORDER BY apellidos, nombres");
            echo json_encode(['success' => true, 'data' => $clientes]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $cliente = fetchOne("SELECT * FROM Clientes WHERE idCliente = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $cliente]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>