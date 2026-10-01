<?php
require_once '../includes/header.php';
require_once '../config/database.php';



// Datos para el dashboard ejecutivo
$resumenGeneral = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM Productos WHERE estado = 1) as total_productos,
        (SELECT COUNT(*) FROM Clientes) as total_clientes,
        (SELECT COUNT(*) FROM Ventas WHERE MONTH(fechaVenta) = MONTH(CURDATE())) as ventas_mes,
        (SELECT SUM(total) FROM Ventas WHERE MONTH(fechaVenta) = MONTH(CURDATE())) as monto_ventas_mes,
        (SELECT COUNT(*) FROM Usuarios WHERE estado = 1) as total_usuarios,
        (SELECT COUNT(*) FROM Empleados WHERE estado = 1) as total_empleados
");

$ventasPorMes = fetchAll("
    SELECT 
        DATE_FORMAT(fechaVenta, '%b') as mes,
        SUM(total) as total
    FROM Ventas
    WHERE fechaVenta >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fechaVenta, '%Y-%m'), DATE_FORMAT(fechaVenta, '%b')
    ORDER BY fechaVenta
");

$ventasPorVendedor = fetchAll("
    SELECT 
        CONCAT(U.nombres, ' ', U.apellidos) as vendedor,
        COUNT(V.idVenta) as cantidad,
        SUM(V.total) as total
    FROM Ventas V
    INNER JOIN Usuarios U ON V.idUsuario = U.idUsuario
    GROUP BY U.idUsuario, U.nombres, U.apellidos
    ORDER BY total DESC
    LIMIT 10
");

$productosMasVendidos = fetchAll("
    SELECT 
        P.nombreProducto,
        SUM(DF.cantidad) as cantidad,
        SUM(DF.subtotal) as total
    FROM DetalleFacturas DF
    INNER JOIN Productos P ON DF.idProducto = P.idProducto
    GROUP BY P.idProducto, P.nombreProducto
    ORDER BY cantidad DESC
    LIMIT 10
");

$clientesTop = fetchAll("
    SELECT 
        CONCAT(C.nombres, ' ', C.apellidos) as cliente,
        COUNT(V.idVenta) as compras,
        SUM(V.total) as total_gastado
    FROM Clientes C
    INNER JOIN Ventas V ON C.idCliente = V.idCliente
    GROUP BY C.idCliente, C.nombres, C.apellidos
    ORDER BY total_gastado DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #2563eb;
        }
        .kpi-card .kpi-value {
            font-size: 28px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-card .kpi-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .kpi-card.ventas { border-left-color: #2563eb; }
        .kpi-card.clientes { border-left-color: #22c55e; }
        .kpi-card.productos { border-left-color: #f59e0b; }
        .kpi-card.empleados { border-left-color: #8b5cf6; }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .chart-card h3 {
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 15px;
        }
        .chart-wrapper {
            position: relative;
            height: 250px;
        }
        @media (max-width: 900px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="header">
    <div>
        <h2>📊 Dashboard Ejecutivo</h2>
        <p>Visión general del negocio</p>
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
    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card ventas">
            <div class="kpi-value">C$ <?php echo number_format($resumenGeneral['monto_ventas_mes'] ?? 0, 0); ?></div>
            <div class="kpi-label">Ventas del mes</div>
        </div>
        <div class="kpi-card clientes">
            <div class="kpi-value"><?php echo $resumenGeneral['total_clientes']; ?></div>
            <div class="kpi-label">Clientes activos</div>
        </div>
        <div class="kpi-card productos">
            <div class="kpi-value"><?php echo $resumenGeneral['total_productos']; ?></div>
            <div class="kpi-label">Productos en inventario</div>
        </div>
        <div class="kpi-card empleados">
            <div class="kpi-value"><?php echo $resumenGeneral['total_empleados']; ?></div>
            <div class="kpi-label">Empleados activos</div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="chart-grid">
        <div class="chart-card">
            <h3>📈 Ventas por Mes</h3>
            <div class="chart-wrapper">
                <canvas id="ventasMesChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>👤 Ventas por Vendedor</h3>
            <div class="chart-wrapper">
                <canvas id="vendedorChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>🏆 Productos más vendidos</h3>
            <div class="chart-wrapper">
                <canvas id="productosChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>💎 Mejores clientes</h3>
            <div class="chart-wrapper">
                <canvas id="clientesChart"></canvas>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const colors = ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

    // 1. Ventas por mes
    new Chart(document.getElementById('ventasMesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($ventasPorMes, 'mes')); ?>,
            datasets: [{
                label: 'Ventas (C$)',
                data: <?php echo json_encode(array_column($ventasPorMes, 'total')); ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.6)',
                borderColor: '#2563eb',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'C$ ' + v.toLocaleString() }
                }
            }
        }
    });

    // 2. Ventas por vendedor
    new Chart(document.getElementById('vendedorChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($ventasPorVendedor, 'vendedor')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($ventasPorVendedor, 'total')); ?>,
                backgroundColor: colors.slice(0, <?php echo count($ventasPorVendedor); ?>),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 } } }
            }
        }
    });

    // 3. Productos más vendidos
    new Chart(document.getElementById('productosChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($productosMasVendidos, 'nombreProducto')); ?>,
            datasets: [{
                label: 'Cantidad vendida',
                data: <?php echo json_encode(array_column($productosMasVendidos, 'cantidad')); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                borderColor: '#22c55e',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // 4. Mejores clientes
    new Chart(document.getElementById('clientesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($clientesTop, 'cliente')); ?>,
            datasets: [{
                label: 'Total gastado (C$)',
                data: <?php echo json_encode(array_column($clientesTop, 'total_gastado')); ?>,
                backgroundColor: 'rgba(245, 158, 11, 0.6)',
                borderColor: '#f59e0b',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'C$ ' + v.toLocaleString() }
                }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>