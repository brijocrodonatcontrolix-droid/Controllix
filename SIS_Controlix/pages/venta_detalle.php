<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id == 0) {
    die("ID de venta no válido");
}

// Obtener datos de la venta
$venta = fetchOne("
    SELECT 
        V.*,
        CONCAT(C.nombres, ' ', C.apellidos) as cliente,
        U.nombres as usuario_nombre,
        F.idFactura,
        F.numeroFactura,
        F.estado as estado_factura
    FROM Ventas V
    LEFT JOIN Clientes C ON V.idCliente = C.idCliente
    LEFT JOIN Usuarios U ON V.idUsuario = U.idUsuario
    LEFT JOIN Facturas F ON V.idVenta = F.idVenta
    WHERE V.idVenta = ?
", [$id]);

if (!$venta) {
    die("Venta no encontrada");
}

// Obtener detalles de la factura
$detalle = fetchAll("
    SELECT 
        DF.*,
        P.nombreProducto
    FROM DetalleFacturas DF
    INNER JOIN Productos P ON DF.idProducto = P.idProducto
    WHERE DF.idFactura = ?
", [$venta['idFactura']]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Factura - <?php echo htmlspecialchars($venta['numeroFactura'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { 
            background: #f1f5f9; 
            padding: 20px;
            font-family: Arial, Helvetica, sans-serif;
        }
        .factura-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .factura-header {
            text-align: center;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .factura-header h1 {
            color: #0f172a;
            font-size: 28px;
            margin: 0;
        }
        .factura-header .subtitle {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }
        .factura-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        .factura-info-item {
            font-size: 13px;
        }
        .factura-info-item strong {
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
        }
        .factura-info-item span {
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        thead th {
            background: #1d4ed8;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }
        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        tfoot td {
            padding: 12px;
            font-weight: bold;
            border-top: 2px solid #1d4ed8;
        }
        .text-right {
            text-align: right;
        }
        .totales {
            margin-top: 20px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        .totales-line {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
        }
        .totales-line.total {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            border-top: 2px solid #1d4ed8;
            padding-top: 10px;
            margin-top: 5px;
        }
        .btn-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        @media print {
            body { background: white; padding: 0; }
            .factura-container { box-shadow: none; padding: 20px; }
            .btn-actions { display: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="factura-container">
    <!-- HEADER -->
    <div class="factura-header">
        <h1>SIS_Controlix</h1>
        <div class="subtitle">Sistema de Control Empresarial</div>
        <div style="margin-top:10px; font-size:16px; font-weight:bold; color:#1d4ed8;">
            <?php echo htmlspecialchars($venta['numeroFactura'] ?? 'FACTURA'); ?>
        </div>
    </div>

    <!-- INFO -->
    <div class="factura-info">
        <div class="factura-info-item">
            <strong>Cliente</strong>
            <span><?php echo htmlspecialchars($venta['cliente'] ?? 'Cliente General'); ?></span>
        </div>
        <div class="factura-info-item">
            <strong>Fecha</strong>
            <span><?php echo date('d/m/Y H:i', strtotime($venta['fechaVenta'])); ?></span>
        </div>
        <div class="factura-info-item">
            <strong>Vendedor</strong>
            <span><?php echo htmlspecialchars($venta['usuario_nombre'] ?? 'Administrador'); ?></span>
        </div>
        <div class="factura-info-item">
            <strong>Estado</strong>
            <span class="badge paid"><?php echo htmlspecialchars($venta['estado_factura'] ?? 'Pagada'); ?></span>
        </div>
    </div>

    <!-- DETALLE -->
    <table>
        <thead>
            <tr>
                <th style="width:50%;">Producto</th>
                <th style="width:15%; text-align:center;">Cantidad</th>
                <th style="width:17%; text-align:right;">Precio Unit.</th>
                <th style="width:18%; text-align:right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detalle)): ?>
                <tr>
                    <td colspan="4" class="text-center" style="padding:30px; color:#94a3b8;">
                        No hay productos en esta factura
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['nombreProducto']); ?></td>
                        <td style="text-align:center;"><?php echo $d['cantidad']; ?></td>
                        <td style="text-align:right;">C$ <?php echo number_format($d['precioUnitario'], 2); ?></td>
                        <td style="text-align:right;">C$ <?php echo number_format($d['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- TOTALES -->
    <div class="totales">
        <div class="totales-line">
            <span>Subtotal</span>
            <span>C$ <?php echo number_format($venta['subtotal'], 2); ?></span>
        </div>
        <?php if ($venta['descuento'] > 0): ?>
        <div class="totales-line">
            <span>Descuento</span>
            <span style="color:#dc2626;">- C$ <?php echo number_format($venta['descuento'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="totales-line">
            <span>IVA (15%)</span>
            <span>C$ <?php echo number_format($venta['iva'], 2); ?></span>
        </div>
        <div class="totales-line total">
            <span>TOTAL</span>
            <span>C$ <?php echo number_format($venta['total'], 2); ?></span>
        </div>
    </div>

    <!-- BOTONES -->
    <div class="btn-actions">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button class="btn btn-success" onclick="window.location.href='facturas.php'">
            <i class="fas fa-arrow-left"></i> Volver
        </button>
        <button class="btn btn-danger" onclick="window.close()">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>
</div>

</body>
</html>