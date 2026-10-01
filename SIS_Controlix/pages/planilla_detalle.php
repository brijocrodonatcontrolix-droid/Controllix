<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;
$planilla = fetchOne("SELECT * FROM Planillas WHERE idPlanilla = ?", [$id]);
$detalle = fetchAll("
    SELECT 
        E.idEmpleado,
        CONCAT(E.nombres, ' ', E.apellidos) as empleado,
        E.cargo,
        DP.salarioBruto,
        DP.inss,
        DP.rentaNetaMensual,
        DP.rentaNetaAnual,
        DP.irMensual as ir,
        DP.salarioNeto
    FROM DetallePlanilla DP
    INNER JOIN Empleados E ON DP.idEmpleado = E.idEmpleado
    WHERE DP.idPlanilla = ?
    ORDER BY DP.salarioBruto DESC
", [$id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detalle de Planilla #<?php echo $id; ?></title>
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
        <p>Detalle de Planilla #<?php echo $id; ?> - Período: <?php echo $planilla['periodo']; ?></p>
        <p>Fecha Generación: <?php echo date('d/m/Y H:i', strtotime($planilla['fechaGeneracion'])); ?></p>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cargo</th>
                    <th>Salario Bruto</th>
                    <th>INSS</th>
                    <th>Renta Neta</th>
                    <th>IR</th>
                    <th>Salario Neto</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalBruto = 0; $totalINSS = 0; $totalIR = 0; $totalNeto = 0; ?>
                <?php foreach ($detalle as $d): 
                    $totalBruto += $d['salarioBruto'];
                    $totalINSS += $d['inss'];
                    $totalIR += $d['ir'];
                    $totalNeto += $d['salarioNeto'];
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['empleado']); ?></td>
                        <td><?php echo htmlspecialchars($d['cargo']); ?></td>
                        <td>C$ <?php echo number_format($d['salarioBruto'], 2); ?></td>
                        <td>C$ <?php echo number_format($d['inss'], 2); ?></td>
                        <td>C$ <?php echo number_format($d['rentaNetaMensual'], 2); ?></td>
                        <td>C$ <?php echo number_format($d['ir'], 2); ?></td>
                        <td><strong>C$ <?php echo number_format($d['salarioNeto'], 2); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="2" style="text-align:right;">TOTALES:</td>
                    <td>C$ <?php echo number_format($totalBruto, 2); ?></td>
                    <td>C$ <?php echo number_format($totalINSS, 2); ?></td>
                    <td>-</td>
                    <td>C$ <?php echo number_format($totalIR, 2); ?></td>
                    <td>C$ <?php echo number_format($totalNeto, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>