<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $proveedores = fetchAll("SELECT * FROM Proveedores WHERE estado = 1 ORDER BY nombreEmpresa");
            echo json_encode(['success' => true, 'data' => $proveedores]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $proveedor = fetchOne("SELECT * FROM Proveedores WHERE idProveedor = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $proveedor]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>