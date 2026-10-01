<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;

$compra = fetchOne("
    SELECT 
        C.*,
        P.nombreEmpresa,
        U.nombres as usuario_nombre
    FROM Compras C
    LEFT JOIN Proveedores P ON C.idProveedor = P.idProveedor
    LEFT JOIN Usuarios U ON C.idUsuario = U.idUsuario
    WHERE C.idCompra = ?
", [$id]);

$detalle = fetchAll("
    SELECT 
        DC.*,
        P.nombreProducto
    FROM DetalleCompras DC
    INNER JOIN Productos P ON DC.idProducto = P.idProducto
    WHERE DC.idCompra = ?
", [$id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detalle de Compra #<?php echo $id; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: white; padding: 30px; }
        .print-header { text-align: center; margin-bottom: 20px; }
        .print-header h1 { color: #0f172a; }
        .print-header p { color: #64748b; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
        <button class="btn btn-danger" onclick="window.close()">Cerrar</button>
    </div>
    
    <div class="print-header">
        <h1>SIS_Controlix</h1>
        <p>Detalle de Compra #<?php echo $id; ?></p>
        <p>Fecha: <?php echo date('d/m/Y H:i', strtotime($compra['fechaCompra'])); ?></p>
        <p>Proveedor: <?php echo htmlspecialchars($compra['nombreEmpresa']); ?></p>
        <p>Factura: <?php echo htmlspecialchars($compra['numeroFactura'] ?? 'N/A'); ?></p>
        <p>Usuario: <?php echo htmlspecialchars($compra['usuario_nombre'] ?? ''); ?></p>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; ?>
                <?php foreach ($detalle as $d): 
                    $total += $d['subtotal'];
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['nombreProducto']); ?></td>
                        <td><?php echo $d['cantidad']; ?></td>
                        <td>C$ <?php echo number_format($d['precioCompra'], 2); ?></td>
                        <td>C$ <?php echo number_format($d['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="3" style="text-align:right;">Subtotal:</td>
                    <td>C$ <?php echo number_format($compra['subtotal'], 2); ?></td>
                </tr>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="3" style="text-align:right;">IVA (15%):</td>
                    <td>C$ <?php echo number_format($compra['iva'], 2); ?></td>
                </tr>
                <tr style="background:#dbeafe; font-weight:bold; font-size:16px;">
                    <td colspan="3" style="text-align:right;">TOTAL:</td>
                    <td>C$ <?php echo number_format($compra['total'], 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>