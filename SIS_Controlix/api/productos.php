<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $productos = fetchAll("
                SELECT 
                    idProducto,
                    nombreProducto,
                    precioVenta,
                    precioCompra,
                    stockActual,
                    stockMinimo
                FROM Productos 
                WHERE estado = 1 
                ORDER BY nombreProducto
            ");
            echo json_encode(['success' => true, 'data' => $productos]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $producto = fetchOne("SELECT * FROM Productos WHERE idProducto = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $producto]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>