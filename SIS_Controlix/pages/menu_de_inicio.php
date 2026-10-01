<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// ============================================
// INICIALIZAR VARIABLES CON VALORES POR DEFECTO
// ============================================

$totalProductos = 0;
$totalClientes = 0;
$ventasMes = 0;
$totalStockBajo = 0;
$totalComprasMes = 0;
$totalEmpleados = 0;
$ultimasVentas = [];
$ultimasCompras = [];
$planilla = null;
$productosStockBajo = [];
$alertasNoLeidas = 0;

// ============================================
// OBTENER DATOS DE LA BASE DE DATOS
// ============================================

try {
    // Totales
    $productos = fetchOne("SELECT COUNT(*) as total FROM Productos WHERE estado = 1");
    $totalProductos = $productos['total'] ?? 0;

    $clientes = fetchOne("SELECT COUNT(*) as total FROM Clientes");
    $totalClientes = $clientes['total'] ?? 0;

    $ventas = fetchOne("SELECT SUM(total) as total FROM Ventas WHERE MONTH(fechaVenta) = MONTH(CURDATE()) AND YEAR(fechaVenta) = YEAR(CURDATE())");
    $ventasMes = $ventas['total'] ?? 0;

    $stockBajo = fetchOne("SELECT COUNT(*) as total FROM Productos WHERE stockActual <= stockMinimo AND estado = 1");
    $totalStockBajo = $stockBajo['total'] ?? 0;

    $compras = fetchOne("SELECT SUM(total) as total FROM Compras WHERE MONTH(fechaCompra) = MONTH(CURDATE()) AND YEAR(fechaCompra) = YEAR(CURDATE())");
    $totalComprasMes = $compras['total'] ?? 0;

    $empleados = fetchOne("SELECT COUNT(*) as total FROM Empleados WHERE estado = 1");
    $totalEmpleados = $empleados['total'] ?? 0;

    // Últimas ventas
    $ultimasVentas = fetchAll("
        SELECT 
            V.idVenta, 
            C.nombres as cliente_nombre,
            C.apellidos as cliente_apellidos,
            V.fechaVenta, 
            V.total,
            F.numeroFactura,
            CASE WHEN F.idFactura IS NOT NULL THEN 'Pagada' ELSE 'Pendiente' END as estado
        FROM Ventas V
        LEFT JOIN Clientes C ON V.idCliente = C.idCliente
        LEFT JOIN Facturas F ON V.idVenta = F.idVenta
        ORDER BY V.fechaVenta DESC
        LIMIT 5
    ");

    // Últimas compras
    $ultimasCompras = fetchAll("
        SELECT 
            C.idCompra,
            C.fechaCompra,
            C.total,
            P.nombreEmpresa,
            C.numeroFactura
        FROM Compras C
        LEFT JOIN Proveedores P ON C.idProveedor = P.idProveedor
        ORDER BY C.fechaCompra DESC
        LIMIT 5
    ");

    // Última planilla
    $planilla = fetchOne("
        SELECT 
            periodo, 
            totalBruto, 
            totalINSS, 
            totalIR, 
            totalNeto,
            estado,
            (SELECT COUNT(*) FROM DetallePlanilla WHERE idPlanilla = P.idPlanilla) as empleados
        FROM Planillas P
        ORDER BY idPlanilla DESC
        LIMIT 1
    ");

    // Productos con stock bajo
    $productosStockBajo = fetchAll("
        SELECT nombreProducto, stockActual, stockMinimo 
        FROM Productos 
        WHERE estado = 1 AND stockActual <= stockMinimo 
        ORDER BY stockActual ASC 
        LIMIT 5
    ");

    // Alertas no leídas
    $alertasNoLeidas = fetchOne("SELECT COUNT(*) as total FROM Alertas WHERE leida = 0")['total'] ?? 0;

} catch (PDOException $e) {
    // Si hay error, mantener valores por defecto
    // Opcional: registrar error en log
}

// ============================================
// OBTENER NOMBRE DEL ROL
// ============================================

$nombreRol = 'Usuario';
if (isset($_SESSION['usuario_rol_id'])) {
    $roles = [
        1 => 'Administrador',
        2 => 'Gerente',
        3 => 'Supervisor',
        4 => 'Vendedor',
        5 => 'Almacenista',
        6 => 'Cajero'
    ];
    $nombreRol = $roles[$_SESSION['usuario_rol_id']] ?? 'Usuario';
}
?>

<header class="header">
    <div>
        <h2>Panel Principal</h2>
        <p>Resumen general de SIS_Controlix - <?php echo $nombreRol; ?></p>
    </div>
    <div class="header-right">
        <div class="notification">
            <i class="fas fa-bell"></i>
            <span><?php echo $alertasNoLeidas; ?></span>
        </div>
        <div class="user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'AD', 0, 2)); ?></div>
            <div class="user-info">
                <strong><?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></strong>
                <small><?php echo $nombreRol; ?></small>
            </div>
        </div>
    </div>
</header>

<section class="content">
    <!-- Bienvenida -->
    <div class="welcome">
        <h2>👋 Bienvenido, <?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></h2>
        <p>Administra el inventario, compras, ventas, empleados y planillas de tu empresa desde un solo sistema.</p>
        <div style="margin-top:10px; font-size:13px; color:#93c5fd;">
            <i class="fas fa-calendar-alt"></i> Hoy es <?php echo date('d/m/Y'); ?> | 
            <i class="fas fa-clock"></i> <?php echo date('h:i A'); ?> |
            <i class="fas fa-user-tag"></i> Rol: <?php echo $nombreRol; ?>
        </div>
    </div>

    <!-- ============================================
         ESTADÍSTICAS
    ============================================= -->
    <div class="stats">
        <?php if (tienePermiso('productos')): ?>
        <div class="stat">
            <div><h4>Productos registrados</h4><strong><?php echo number_format($totalProductos); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-box"></i></div>
        </div>
        <?php endif; ?>

        <?php if (tienePermiso('clientes')): ?>
        <div class="stat">
            <div><h4>Clientes</h4><strong><?php echo number_format($totalClientes); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <?php endif; ?>

        <?php if (tienePermiso('ventas')): ?>
        <div class="stat">
            <div><h4>Ventas del mes</h4><strong><?php echo MONEDA . ' ' . number_format($ventasMes, 2); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        <?php endif; ?>

        <?php if (tienePermiso('compras')): ?>
        <div class="stat">
            <div><h4>Compras del mes</h4><strong><?php echo MONEDA . ' ' . number_format($totalComprasMes, 2); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
        <?php endif; ?>

        <?php if (tienePermiso('empleados')): ?>
        <div class="stat">
            <div><h4>Empleados activos</h4><strong><?php echo number_format($totalEmpleados); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        </div>
        <?php endif; ?>

        <?php if (tienePermiso('movimientos')): ?>
        <div class="stat">
            <div><h4>Productos con stock bajo</h4><strong style="color:#ef4444;"><?php echo number_format($totalStockBajo); ?></strong></div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================
         MÓDULOS DEL SISTEMA
    ============================================= -->
    <h2 class="section-title">Módulos del sistema</h2>
    <div class="modules">
        <?php if (tienePermiso('productos')): ?>
        <a href="productos.php" class="module">
            <div class="module-icon"><i class="fas fa-box"></i></div>
            <h3>Productos</h3>
            <p>Registrar, modificar y consultar productos.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('movimientos')): ?>
        <a href="movimientos.php" class="module">
            <div class="module-icon"><i class="fas fa-chart-bar"></i></div>
            <h3>Inventario</h3>
            <p>Controlar entradas, salidas, ajustes y mermas.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('compras')): ?>
        <a href="compras.php" class="module">
            <div class="module-icon"><i class="fas fa-shopping-cart"></i></div>
            <h3>Compras</h3>
            <p>Gestionar compras y proveedores.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('ventas')): ?>
        <a href="ventas.php" class="module">
            <div class="module-icon"><i class="fas fa-coins"></i></div>
            <h3>Ventas</h3>
            <p>Registrar ventas y generar facturas.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('clientes')): ?>
        <a href="clientes.php" class="module">
            <div class="module-icon"><i class="fas fa-users"></i></div>
            <h3>Clientes</h3>
            <p>Administrar información de clientes.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('proveedores')): ?>
        <a href="proveedores.php" class="module">
            <div class="module-icon"><i class="fas fa-truck"></i></div>
            <h3>Proveedores</h3>
            <p>Gestionar proveedores y contactos.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('empleados')): ?>
        <a href="empleados.php" class="module">
            <div class="module-icon"><i class="fas fa-user-tie"></i></div>
            <h3>Empleados</h3>
            <p>Administrar los empleados de la empresa.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('planilla')): ?>
        <a href="planilla.php" class="module">
            <div class="module-icon"><i class="fas fa-money-bill-wave"></i></div>
            <h3>Planilla</h3>
            <p>Generar y consultar planillas de empleados.</p>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('reportes')): ?>
        <a href="reportes.php" class="module">
            <div class="module-icon"><i class="fas fa-chart-line"></i></div>
            <h3>Reportes</h3>
            <p>Consultar información y estadísticas.</p>
        </a>
        <?php endif; ?>
    </div>

    <!-- ============================================
         PLANILLA
    ============================================= -->
    <?php if (tienePermiso('planilla') && $planilla): ?>
    <h2 class="section-title">Gestión de Planilla</h2>
    <div class="payroll-card">
        <div class="payroll-header">
            <div><h3>Planilla del mes</h3></div>
            <div class="period"><?php echo $planilla['periodo'] ?? date('Y-m'); ?></div>
        </div>
        <div class="payroll-data">
            <div class="payroll-item"><span>Empleados activos</span><strong><?php echo $planilla['empleados'] ?? 0; ?></strong></div>
            <div class="payroll-item"><span>Total bruto</span><strong><?php echo MONEDA . ' ' . number_format($planilla['totalBruto'] ?? 0, 2); ?></strong></div>
            <div class="payroll-item"><span>Total INSS</span><strong><?php echo MONEDA . ' ' . number_format($planilla['totalINSS'] ?? 0, 2); ?></strong></div>
            <div class="payroll-item"><span>Total IR</span><strong><?php echo MONEDA . ' ' . number_format($planilla['totalIR'] ?? 0, 2); ?></strong></div>
            <div class="payroll-item"><span>Total neto</span><strong><?php echo MONEDA . ' ' . number_format($planilla['totalNeto'] ?? 0, 2); ?></strong></div>
            <div class="payroll-item"><span>Estado</span><strong style="color:#16a34a;"><?php echo $planilla['estado'] ?? 'Generada'; ?></strong></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         DASHBOARD: Últimas Ventas + Alertas
    ============================================= -->
    <div class="dashboard" style="margin-top:25px;">
        <!-- ÚLTIMAS VENTAS -->
        <?php if (tienePermiso('ventas')): ?>
        <div class="panel">
            <div class="panel-header">
                <h3>Últimas ventas</h3>
                <a href="ventas.php">Ver todas</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Factura</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimasVentas)): ?>
                            <tr><td colspan="5" class="text-center">No hay ventas registradas</td></tr>
                        <?php else: ?>
                            <?php foreach ($ultimasVentas as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['numeroFactura'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($v['cliente_nombre'] ?? '') . ' ' . ($v['cliente_apellidos'] ?? '')); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($v['fechaVenta'])); ?></td>
                                    <td><?php echo MONEDA . ' ' . number_format($v['total'], 2); ?></td>
                                    <td><span class="badge <?php echo $v['estado'] == 'Pagada' ? 'paid' : 'pending'; ?>"><?php echo $v['estado']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ALERTAS -->
        <div class="panel">
            <div class="panel-header">
                <h3>Alertas del sistema</h3>
                <?php if (tienePermiso('mermas') || tienePermiso('alertas')): ?>
                <a href="mermas-alertas.php">Ver todas</a>
                <?php endif; ?>
            </div>

            <?php if (tienePermiso('movimientos') && $totalStockBajo > 0): ?>
            <div class="alert">
                <div class="alert-icon"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i></div>
                <div>
                    <strong>Stock bajo</strong>
                    <small><?php echo $totalStockBajo; ?> productos requieren reposición.</small>
                    <?php if (!empty($productosStockBajo)): ?>
                        <div style="margin-top:5px; font-size:11px; color:#64748b;">
                            <?php foreach ($productosStockBajo as $p): ?>
                                <div>• <?php echo htmlspecialchars($p['nombreProducto']); ?> (Stock: <?php echo $p['stockActual']; ?>)</div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (tienePermiso('planilla') && isset($planilla['periodo'])): ?>
            <div class="alert">
                <div class="alert-icon"><i class="fas fa-money-bill-wave" style="color:#22c55e;"></i></div>
                <div>
                    <strong>Planilla generada</strong>
                    <small>Planilla de <?php echo $planilla['periodo']; ?> generada correctamente.</small>
                </div>
            </div>
            <?php endif; ?>

            <?php if (tienePermiso('ventas') && $ventasMes > 0): ?>
            <div class="alert">
                <div class="alert-icon"><i class="fas fa-chart-line" style="color:#2563eb;"></i></div>
                <div>
                    <strong>Ventas del mes</strong>
                    <small>Total acumulado: <?php echo MONEDA . ' ' . number_format($ventasMes, 2); ?></small>
                </div>
            </div>
            <?php endif; ?>

            <div class="alert">
                <div class="alert-icon"><i class="fas fa-shield-alt" style="color:#8b5cf6;"></i></div>
                <div>
                    <strong>Seguridad</strong>
                    <small>Último inicio de sesión: <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         ÚLTIMAS COMPRAS
    ============================================= -->
    <?php if (tienePermiso('compras') && !empty($ultimasCompras)): ?>
    <div style="margin-top:25px;">
        <h2 class="section-title">Últimas compras</h2>
        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Factura</th><th>Proveedor</th><th>Fecha</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimasCompras as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['numeroFactura'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($c['nombreEmpresa'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($c['fechaCompra'])); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($c['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php require_once '../includes/footer.php'; ?>
</section>