<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'eliminar':
            $idFactura = $_GET['idFactura'] ?? 0;
            $idVenta = $_GET['idVenta'] ?? 0;
            
            // Obtener productos para restaurar stock
            $productos = fetchAll("SELECT idProducto, cantidad FROM DetalleFacturas WHERE idFactura = ?", [$idFactura]);
            foreach ($productos as $p) {
                execute("UPDATE Productos SET stockActual = stockActual + ? WHERE idProducto = ?", [
                    $p['cantidad'],
                    $p['idProducto']
                ]);
            }
            
            // Eliminar detalles
            execute("DELETE FROM DetalleFacturas WHERE idFactura = ?", [$idFactura]);
            // Eliminar factura
            execute("DELETE FROM Facturas WHERE idFactura = ?", [$idFactura]);
            // Eliminar venta
            execute("DELETE FROM Ventas WHERE idVenta = ?", [$idVenta]);
            
            echo json_encode(['success' => true]);
            break;

        case 'enviar_email':
            $idVenta = $_GET['id'] ?? 0;
            
            // Obtener datos de la venta
            $venta = fetchOne("
                SELECT 
                    V.*,
                    CONCAT(C.nombres, ' ', C.apellidos) as cliente,
                    C.correo as email_cliente,
                    F.numeroFactura
                FROM Ventas V
                LEFT JOIN Clientes C ON V.idCliente = C.idCliente
                LEFT JOIN Facturas F ON V.idVenta = F.idVenta
                WHERE V.idVenta = ?
            ", [$idVenta]);
            
            if (!$venta || empty($venta['email_cliente'])) {
                echo json_encode(['success' => false, 'error' => 'Cliente sin correo electrónico']);
                break;
            }
            
            // Obtener detalles
            $detalle = fetchAll("
                SELECT 
                    DF.*,
                    P.nombreProducto
                FROM DetalleFacturas DF
                INNER JOIN Productos P ON DF.idProducto = P.idProducto
                WHERE DF.idFactura = (SELECT idFactura FROM Facturas WHERE idVenta = ?)
            ", [$idVenta]);
            
            // Construir email
            $html = "<h2>Factura {$venta['numeroFactura']}</h2>";
            $html .= "<p><strong>Cliente:</strong> {$venta['cliente']}</p>";
            $html .= "<p><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($venta['fechaVenta'])) . "</p>";
            $html .= "<hr>";
            $html .= "<table border='1' cellpadding='5'>";
            $html .= "<tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr>";
            foreach ($detalle as $d) {
                $html .= "<tr><td>{$d['nombreProducto']}</td><td>{$d['cantidad']}</td><td>C$ " . number_format($d['precioUnitario'], 2) . "</td><td>C$ " . number_format($d['subtotal'], 2) . "</td></tr>";
            }
            $html .= "</table>";
            $html .= "<hr>";
            $html .= "<p><strong>Subtotal:</strong> C$ " . number_format($venta['subtotal'], 2) . "</p>";
            $html .= "<p><strong>IVA:</strong> C$ " . number_format($venta['iva'], 2) . "</p>";
            $html .= "<p style='font-size:18px;'><strong>TOTAL:</strong> C$ " . number_format($venta['total'], 2) . "</p>";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: SIS_Controlix <facturas@siscontrolix.com>\r\n";
            
            if (mail($venta['email_cliente'], "Factura " . $venta['numeroFactura'] . " - SIS_Controlix", $html, $headers)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al enviar correo']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>