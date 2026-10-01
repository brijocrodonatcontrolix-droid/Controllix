<?php
require_once '../includes/header.php';
require_once '../config/database.php';

redirigirSiNoTienePermiso('reportes');

// Datos para gráficos
$ventasMensuales = fetchAll("
    SELECT 
        DATE_FORMAT(fechaVenta, '%Y-%m') as mes,
        DATE_FORMAT(fechaVenta, '%b') as mes_corto,
        COUNT(*) as cantidad,
        SUM(total) as total
    FROM Ventas
    WHERE fechaVenta >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fechaVenta, '%Y-%m'), DATE_FORMAT(fechaVenta, '%b')
    ORDER BY mes
");

$productosTop = fetchAll("
    SELECT 
        P.nombreProducto,
        SUM(DF.cantidad) as total_vendido,
        SUM(DF.subtotal) as total_ventas
    FROM DetalleFacturas DF
    INNER JOIN Productos P ON DF.idProducto = P.idProducto
    GROUP BY P.idProducto, P.nombreProducto
    ORDER BY total_vendido DESC
    LIMIT 10
");

$ventasPorCategoria = fetchAll("
    SELECT 
        C.nombreCategoria,
        SUM(DF.cantidad) as total_vendido
    FROM DetalleFacturas DF
    INNER JOIN Productos P ON DF.idProducto = P.idProducto
    INNER JOIN Categorias C ON P.idCategoria = C.idCategoria
    GROUP BY C.idCategoria, C.nombreCategoria
    ORDER BY total_vendido DESC
");

$stockGeneral = fetchAll("
    SELECT 
        'Stock Actual' as tipo,
        SUM(stockActual) as total
    FROM Productos
    WHERE estado = 1
    UNION ALL
    SELECT 
        'Stock Mínimo',
        SUM(stockMinimo)
    FROM Productos
    WHERE estado = 1
");

$ventasPorVendedor = fetchAll("
    SELECT 
        U.nombres,
        U.apellidos,
        COUNT(V.idVenta) as total_ventas,
        SUM(V.total) as total_monto
    FROM Ventas V
    INNER JOIN Usuarios U ON V.idUsuario = U.idUsuario
    GROUP BY U.idUsuario, U.nombres, U.apellidos
    ORDER BY total_monto DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .chart-container h3 {
            color: #0f172a;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .chart-full {
            grid-column: 1 / -1;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-card h4 {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #0f172a;
        }
        .stat-card .number.blue { color: #2563eb; }
        .stat-card .number.green { color: #22c55e; }
        .stat-card .number.yellow { color: #f59e0b; }
        .stat-card .number.red { color: #ef4444; }
        
        @media (max-width: 900px) {
            .chart-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="header">
    <div>
        <h2>Reportes con Gráficos</h2>
        <p>Visualización de datos y estadísticas</p>
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
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Ventas (12 meses)</h4>
            <div class="number blue">C$ <?php echo number_format(array_sum(array_column($ventasMensuales, 'total')), 0); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Productos</h4>
            <div class="number green"><?php echo count($productosTop); ?>+</div>
        </div>
        <div class="stat-card">
            <h4>Productos más vendido</h4>
            <div class="number yellow"><?php echo !empty($productosTop) ? htmlspecialchars($productosTop[0]['nombreProducto']) : 'N/A'; ?></div>
        </div>
        <div class="stat-card">
            <h4>Stock disponible</h4>
            <div class="number red"><?php echo $stockGeneral[0]['total'] ?? 0; ?></div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="chart-grid">
        <!-- Ventas Mensuales -->
        <div class="chart-container chart-full">
            <h3>📊 Ventas Mensuales (Último año)</h3>
            <div class="chart-wrapper">
                <canvas id="ventasChart"></canvas>
            </div>
        </div>

        <!-- Productos más vendidos -->
        <div class="chart-container">
            <h3>🏆 Productos más vendidos</h3>
            <div class="chart-wrapper">
                <canvas id="productosChart"></canvas>
            </div>
        </div>

        <!-- Ventas por categoría -->
        <div class="chart-container">
            <h3>📁 Ventas por Categoría</h3>
            <div class="chart-wrapper">
                <canvas id="categoriaChart"></canvas>
            </div>
        </div>

        <!-- Ventas por vendedor -->
        <div class="chart-container chart-full">
            <h3>👤 Ventas por Vendedor</h3>
            <div class="chart-wrapper">
                <canvas id="vendedorChart"></canvas>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Colores
    const colors = ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

    // 1. Ventas Mensuales
    const ctx1 = document.getElementById('ventasChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($ventasMensuales, 'mes_corto')); ?>,
            datasets: [{
                label: 'Monto (C$)',
                data: <?php echo json_encode(array_column($ventasMensuales, 'total')); ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.6)',
                borderColor: '#2563eb',
                borderWidth: 2,
                borderRadius: 4
            }, {
                label: 'Cantidad de Ventas',
                data: <?php echo json_encode(array_column($ventasMensuales, 'cantidad')); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                borderColor: '#22c55e',
                borderWidth: 2,
                borderRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'C$ ' + value.toLocaleString(); }
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        callback: function(value) { return value + ' ventas'; }
                    }
                }
            }
        }
    });

    // 2. Productos más vendidos
    const ctx2 = document.getElementById('productosChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($productosTop, 'nombreProducto')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($productosTop, 'total_vendido')); ?>,
                backgroundColor: colors.slice(0, <?php echo count($productosTop); ?>),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 11 }
                    }
                }
            }
        }
    });

    // 3. Ventas por categoría
    const ctx3 = document.getElementById('categoriaChart').getContext('2d');
    new Chart(ctx3, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($ventasPorCategoria, 'nombreCategoria')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($ventasPorCategoria, 'total_vendido')); ?>,
                backgroundColor: ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 11 }
                    }
                }
            }
        }
    });

    // 4. Ventas por vendedor
    const ctx4 = document.getElementById('vendedorChart').getContext('2d');
    new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($v) { return $v['nombres'] . ' ' . $v['apellidos']; }, $ventasPorVendedor)); ?>,
            datasets: [{
                label: 'Ventas (C$)',
                data: <?php echo json_encode(array_column($ventasPorVendedor, 'total_monto')); ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.6)',
                borderColor: '#8b5cf6',
                borderWidth: 2,
                borderRadius: 4
            }, {
                label: 'Cantidad de Ventas',
                data: <?php echo json_encode(array_column($ventasPorVendedor, 'total_ventas')); ?>,
                backgroundColor: 'rgba(6, 182, 212, 0.6)',
                borderColor: '#06b6d4',
                borderWidth: 2,
                borderRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'C$ ' + value.toLocaleString(); }
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        callback: function(value) { return value + ' ventas'; }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>