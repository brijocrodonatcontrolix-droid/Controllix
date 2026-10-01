<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $compras = fetchAll("
                SELECT 
                    C.*,
                    P.nombreEmpresa,
                    U.nombres as usuario_nombre
                FROM Compras C
                LEFT JOIN Proveedores P ON C.idProveedor = P.idProveedor
                LEFT JOIN Usuarios U ON C.idUsuario = U.idUsuario
                ORDER BY C.fechaCompra DESC
            ");
            echo json_encode(['success' => true, 'data' => $compras]);
            break;

        case 'detalle':
            $id = $_GET['id'] ?? 0;
            $detalle = fetchAll("
                SELECT 
                    DC.*,
                    P.nombreProducto
                FROM DetalleCompras DC
                INNER JOIN Productos P ON DC.idProducto = P.idProducto
                WHERE DC.idCompra = ?
            ", [$id]);
            echo json_encode(['success' => true, 'data' => $detalle]);
            break;

        case 'eliminar':
            $id = $_GET['id'] ?? 0;
            // Obtener productos para restaurar stock
            $productos = fetchAll("SELECT idProducto, cantidad FROM DetalleCompras WHERE idCompra = ?", [$id]);
            foreach ($productos as $p) {
                execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
                    $p['cantidad'],
                    $p['idProducto']
                ]);
            }
            execute("DELETE FROM DetalleCompras WHERE idCompra = ?", [$id]);
            execute("DELETE FROM Compras WHERE idCompra = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>