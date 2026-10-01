<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $ventas = fetchAll("
                SELECT 
                    V.*,
                    CONCAT(C.nombres, ' ', C.apellidos) as cliente,
                    U.nombres as usuario_nombre
                FROM Ventas V
                LEFT JOIN Clientes C ON V.idCliente = C.idCliente
                LEFT JOIN Usuarios U ON V.idUsuario = U.idUsuario
                ORDER BY V.fechaVenta DESC
            ");
            echo json_encode(['success' => true, 'data' => $ventas]);
            break;

        case 'detalle':
            $id = $_GET['id'] ?? 0;
            $venta = fetchOne("
                SELECT 
                    V.*,
                    CONCAT(C.nombres, ' ', C.apellidos) as cliente,
                    U.nombres as usuario_nombre
                FROM Ventas V
                LEFT JOIN Clientes C ON V.idCliente = C.idCliente
                LEFT JOIN Usuarios U ON V.idUsuario = U.idUsuario
                WHERE V.idVenta = ?
            ", [$id]);
            
            $detalle = fetchAll("
                SELECT 
                    DF.*,
                    P.nombreProducto
                FROM DetalleFacturas DF
                INNER JOIN Productos P ON DF.idProducto = P.idProducto
                WHERE DF.idFactura = (SELECT idFactura FROM Facturas WHERE idVenta = ?)
            ", [$id]);
            
            echo json_encode(['success' => true, 'venta' => $venta, 'detalle' => $detalle]);
            break;

        case 'eliminar':
            $id = $_GET['id'] ?? 0;
            
            // Obtener factura
            $factura = fetchOne("SELECT idFactura FROM Facturas WHERE idVenta = ?", [$id]);
            if ($factura) {
                // Obtener productos para restaurar stock
                $productos = fetchAll("SELECT idProducto, cantidad FROM DetalleFacturas WHERE idFactura = ?", [$factura['idFactura']]);
                foreach ($productos as $p) {
                    execute("UPDATE Productos SET stockActual = stockActual + ? WHERE idProducto = ?", [
                        $p['cantidad'],
                        $p['idProducto']
                    ]);
                }
                execute("DELETE FROM DetalleFacturas WHERE idFactura = ?", [$factura['idFactura']]);
                execute("DELETE FROM Facturas WHERE idFactura = ?", [$factura['idFactura']]);
            }
            
            execute("DELETE FROM Ventas WHERE idVenta = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>