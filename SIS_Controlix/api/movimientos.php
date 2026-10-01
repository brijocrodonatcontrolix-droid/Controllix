<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $movimientos = fetchAll("
                SELECT 
                    M.*,
                    P.nombreProducto,
                    U.nombres as usuario_nombre
                FROM MovimientosInventario M
                LEFT JOIN Productos P ON M.idProducto = P.idProducto
                LEFT JOIN Usuarios U ON M.idUsuario = U.idUsuario
                ORDER BY M.fecha DESC
                LIMIT 100
            ");
            echo json_encode(['success' => true, 'data' => $movimientos]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $movimiento = fetchOne("SELECT * FROM MovimientosInventario WHERE idMovimiento = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $movimiento]);
            break;

        case 'eliminar':
            $id = $_GET['id'] ?? 0;
            
            // Obtener movimiento para restaurar stock
            $movimiento = fetchOne("SELECT * FROM MovimientosInventario WHERE idMovimiento = ?", [$id]);
            
            // Restaurar stock
            $cantidadStock = ($movimiento['tipoMovimiento'] === 'Salida' || $movimiento['tipoMovimiento'] === 'Ajuste') ? -$movimiento['cantidad'] : $movimiento['cantidad'];
            execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
                $cantidadStock,
                $movimiento['idProducto']
            ]);
            
            // Eliminar movimiento
            execute("DELETE FROM MovimientosInventario WHERE idMovimiento = ?", [$id]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>