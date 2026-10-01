<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Obtener estadísticas generales
$totalProductos = fetchOne("SELECT COUNT(*) as total FROM Productos WHERE estado = 1")['total'] ?? 0;
$totalClientes = fetchOne("SELECT COUNT(*) as total FROM Clientes")['total'] ?? 0;
$totalVentas = fetchOne("SELECT COUNT(*) as total FROM Ventas")['total'] ?? 0;
$totalProveedores = fetchOne("SELECT COUNT(*) as total FROM Proveedores WHERE estado = 1")['total'] ?? 0;
$totalEmpleados = fetchOne("SELECT COUNT(*) as total FROM Empleados WHERE estado = 1")['total'] ?? 0;
$totalCompras = fetchOne("SELECT COUNT(*) as total FROM Compras")['total'] ?? 0;

// Ventas por mes (últimos 6 meses)
$ventasPorMes = fetchAll("
    SELECT 
        DATE_FORMAT(fechaVenta, '%Y-%m') as mes,
        DATE_FORMAT(fechaVenta, '%M') as nombre_mes,
        COUNT(*) as cantidad,
        SUM(total) as total
    FROM Ventas
    WHERE fechaVenta >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fechaVenta, '%Y-%m'), DATE_FORMAT(fechaVenta, '%M')
    ORDER BY mes DESC
");

// Productos más vendidos
$productosTop = fetchAll("
    SELECT 
        P.idProducto,
        P.nombreProducto,
        P.precioVenta,
        SUM(DF.cantidad) as total_vendido,
        SUM(DF.subtotal) as total_ventas
    FROM DetalleFacturas DF
    INNER JOIN Productos P ON DF.idProducto = P.idProducto
    GROUP BY P.idProducto, P.nombreProducto, P.precioVenta
    ORDER BY total_vendido DESC
    LIMIT 10
");

// Ventas por cliente
$ventasPorCliente = fetchAll("
    SELECT 
        C.idCliente,
        CONCAT(C.nombres, ' ', C.apellidos) as cliente,
        COUNT(V.idVenta) as total_compras,
        SUM(V.total) as total_gastado
    FROM Clientes C
    LEFT JOIN Ventas V ON C.idCliente = V.idCliente
    GROUP BY C.idCliente, C.nombres, C.apellidos
    HAVING total_compras > 0
    ORDER BY total_gastado DESC
    LIMIT 10
");

// Compras por proveedor
$comprasPorProveedor = fetchAll("
    SELECT 
        P.idProveedor,
        P.nombreEmpresa,
        COUNT(C.idCompra) as total_compras,
        SUM(C.total) as total_gastado
    FROM Proveedores P
    LEFT JOIN Compras C ON P.idProveedor = C.idProveedor
    GROUP BY P.idProveedor, P.nombreEmpresa
    HAVING total_compras > 0
    ORDER BY total_gastado DESC
    LIMIT 10
");

// Resumen de inventario
$resumenInventario = fetchAll("
    SELECT 
        'Stock disponible' as tipo,
        SUM(stockActual) as total
    FROM Productos
    WHERE estado = 1
    UNION ALL
    SELECT 
        'Stock mínimo',
        SUM(stockMinimo)
    FROM Productos
    WHERE estado = 1
    UNION ALL
    SELECT 
        'Productos con stock bajo',
        COUNT(*)
    FROM Productos
    WHERE estado = 1 AND stockActual <= stockMinimo
");

// Resumen de planilla
$resumenPlanilla = fetchOne("
    SELECT 
        COUNT(DISTINCT idEmpleado) as total_empleados,
        SUM(salarioBruto) as total_bruto,
        SUM(inss) as total_inss,
        SUM(irMensual) as total_ir,
        SUM(salarioNeto) as total_neto
    FROM DetallePlanilla
    WHERE idPlanilla = (SELECT MAX(idPlanilla) FROM Planillas)
");
?>

<header class="header">
    <div>
        <h2>Reportes y Estadísticas</h2>
        <p>Análisis completo de datos del sistema</p>
    </div>
    <div class="header-right">
        <div class="notification"><i class="fas fa-bell"></i><span>4</span></div>
        <div class="user">
            <div class="avatar">AD</div>
            <div class="user-info">
                <strong><?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></strong>
                <small>Administrador</small>
            </div>
        </div>
    </div>
</header>

<section class="content">
    <!-- Resumen General -->
    <h2 class="section-title">Resumen General</h2>
    <div class="stats">
        <div class="stat">
            <div>
                <h4>Productos</h4>
                <strong><?php echo $totalProductos; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-box" style="color:#2563eb;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Clientes</h4>
                <strong><?php echo $totalClientes; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-users" style="color:#22c55e;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Ventas</h4>
                <strong><?php echo $totalVentas; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-coins" style="color:#f59e0b;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Proveedores</h4>
                <strong><?php echo $totalProveedores; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-truck" style="color:#8b5cf6;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Empleados</h4>
                <strong><?php echo $totalEmpleados; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-user-tie" style="color:#ec4899;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Compras</h4>
                <strong><?php echo $totalCompras; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart" style="color:#06b6d4;"></i></div>
        </div>
    </div>

    <!-- Ventas por Mes -->
    <h2 class="section-title">Ventas por Mes</h2>
    <div class="card" style="margin-bottom:25px;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Cantidad de Ventas</th>
                        <th>Total Vendido</th>
                        <th>Promedio por Venta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventasPorMes)): ?>
                        <tr><td colspan="4" class="text-center">No hay datos de ventas</td></tr>
                    <?php else: ?>
                        <?php foreach ($ventasPorMes as $v): ?>
                            <tr>
                                <td><?php echo $v['nombre_mes'] . ' ' . substr($v['mes'], 0, 4); ?></td>
                                <td><?php echo $v['cantidad']; ?></td>
                                <td><strong><?php echo MONEDA . ' ' . number_format($v['total'], 2); ?></strong></td>
                                <td><?php echo MONEDA . ' ' . number_format($v['total'] / $v['cantidad'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos más Vendidos -->
    <h2 class="section-title">Productos más Vendidos</h2>
    <div class="card" style="margin-bottom:25px;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Precio Unitario</th>
                        <th>Unidades Vendidas</th>
                        <th>Total en Ventas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productosTop)): ?>
                        <tr><td colspan="5" class="text-center">No hay datos de productos vendidos</td></tr>
                    <?php else: ?>
                        <?php $i = 1; foreach ($productosTop as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($p['nombreProducto']); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['precioVenta'], 2); ?></td>
                                <td><strong><?php echo $p['total_vendido']; ?></strong></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['total_ventas'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mejores Clientes -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div>
            <h2 class="section-title">Mejores Clientes</h2>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Compras</th>
                                <th>Total Gastado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventasPorCliente)): ?>
                                <tr><td colspan="4" class="text-center">No hay datos</td></tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($ventasPorCliente as $c): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($c['cliente']); ?></td>
                                        <td><?php echo $c['total_compras']; ?></td>
                                        <td><strong><?php echo MONEDA . ' ' . number_format($c['total_gastado'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Mejores Proveedores -->
        <div>
            <h2 class="section-title">Mejores Proveedores</h2>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Proveedor</th>
                                <th>Compras</th>
                                <th>Total Gastado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comprasPorProveedor)): ?>
                                <tr><td colspan="4" class="text-center">No hay datos</td></tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($comprasPorProveedor as $p): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($p['nombreEmpresa']); ?></td>
                                        <td><?php echo $p['total_compras']; ?></td>
                                        <td><strong><?php echo MONEDA . ' ' . number_format($p['total_gastado'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen de Inventario -->
    <h2 class="section-title" style="margin-top:25px;">Resumen de Inventario</h2>
    <div class="card" style="margin-bottom:25px;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumenInventario as $r): ?>
                        <tr>
                            <td><?php echo $r['tipo']; ?></td>
                            <td><strong><?php echo $r['total']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Resumen de Planilla -->
    <?php if ($resumenPlanilla && $resumenPlanilla['total_empleados'] > 0): ?>
        <h2 class="section-title">Resumen de Última Planilla</h2>
        <div class="card">
            <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:15px;">
                <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <span style="font-size:11px; color:#64748b;">Empleados</span>
                    <div style="font-size:20px; font-weight:bold;"><?php echo $resumenPlanilla['total_empleados']; ?></div>
                </div>
                <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <span style="font-size:11px; color:#64748b;">Total Bruto</span>
                    <div style="font-size:20px; font-weight:bold; color:#2563eb;"><?php echo MONEDA . ' ' . number_format($resumenPlanilla['total_bruto'], 2); ?></div>
                </div>
                <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <span style="font-size:11px; color:#64748b;">Total INSS</span>
                    <div style="font-size:20px; font-weight:bold; color:#f59e0b;"><?php echo MONEDA . ' ' . number_format($resumenPlanilla['total_inss'], 2); ?></div>
                </div>
                <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <span style="font-size:11px; color:#64748b;">Total IR</span>
                    <div style="font-size:20px; font-weight:bold; color:#ef4444;"><?php echo MONEDA . ' ' . number_format($resumenPlanilla['total_ir'], 2); ?></div>
                </div>
                <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <span style="font-size:11px; color:#64748b;">Total Neto</span>
                    <div style="font-size:20px; font-weight:bold; color:#22c55e;"><?php echo MONEDA . ' ' . number_format($resumenPlanilla['total_neto'], 2); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Botones de exportación -->
    <div style="display:flex; gap:10px; margin-top:25px; flex-wrap:wrap;">
        <button class="btn btn-success" onclick="exportarReporte('excel')">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
        <button class="btn btn-danger" onclick="exportarReporte('pdf')">
            <i class="fas fa-file-pdf"></i> Exportar a PDF
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir Reporte
        </button>
    </div>
</section>

<script>
function exportarReporte(tipo) {
    alert('Funcionalidad en desarrollo: Exportar a ' + tipo.toUpperCase());
    // Aquí se implementaría la exportación real
}
</script>

<?php require_once '../includes/footer.php'; ?>