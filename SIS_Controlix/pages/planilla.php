<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Generar nueva planilla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generar') {
    $periodo = $_POST['periodo'] ?? date('Y-m');
    
    // Verificar si ya existe
    $existe = fetchOne("SELECT COUNT(*) as total FROM Planillas WHERE periodo = ?", [$periodo]);
    
    if ($existe['total'] > 0) {
        $mensaje = "Ya existe una planilla para el período $periodo";
        $tipo_mensaje = "warning";
    } else {
        // Obtener empleados activos
        $empleados = fetchAll("SELECT idEmpleado, salarioBase FROM Empleados WHERE estado = 1");
        
        if (empty($empleados)) {
            $mensaje = "No hay empleados activos para generar la planilla";
            $tipo_mensaje = "warning";
        } else {
            // Crear planilla
            $sqlPlanilla = "INSERT INTO Planillas (periodo) VALUES (?)";
            $idPlanilla = insert($sqlPlanilla, [$periodo]);
            
            // Insertar detalles
            foreach ($empleados as $emp) {
                $sqlDetalle = "INSERT INTO DetallePlanilla (idPlanilla, idEmpleado, salarioBruto) 
                               VALUES (?, ?, ?)";
                execute($sqlDetalle, [$idPlanilla, $emp['idEmpleado'], $emp['salarioBase']]);
            }
            
            // Calcular INSS
            execute("UPDATE DetallePlanilla SET inss = ROUND(salarioBruto * 0.07, 2) WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular renta mensual
            execute("UPDATE DetallePlanilla SET rentaNetaMensual = salarioBruto - inss WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular renta anual
            execute("UPDATE DetallePlanilla SET rentaNetaAnual = rentaNetaMensual * 12 WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular IR usando la función de MySQL equivalente
            $irCalculos = fetchAll("
                SELECT 
                    idDetallePlanilla,
                    rentaNetaAnual,
                    (SELECT impuestoBase + ((rentaNetaAnual - sobreExceso) * (porcentaje / 100))
                     FROM TablaIR 
                     WHERE rentaNetaAnual >= limiteInferior 
                       AND (limiteSuperior IS NULL OR rentaNetaAnual <= limiteSuperior)
                     ORDER BY limiteInferior DESC LIMIT 1) as ir_anual
                FROM DetallePlanilla 
                WHERE idPlanilla = ?
            ", [$idPlanilla]);
            
            foreach ($irCalculos as $ir) {
                $irAnual = max(0, $ir['ir_anual'] ?? 0);
                execute("UPDATE DetallePlanilla SET irAnual = ?, irMensual = ROUND(? / 12, 2) WHERE idDetallePlanilla = ?", 
                       [$irAnual, $irAnual, $ir['idDetallePlanilla']]);
            }
            
            // Calcular salario neto
            execute("UPDATE DetallePlanilla SET salarioNeto = salarioBruto - inss - irMensual WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Actualizar totales
            $totales = fetchOne("
                SELECT 
                    SUM(salarioBruto) as totalBruto,
                    SUM(inss) as totalINSS,
                    SUM(irMensual) as totalIR,
                    SUM(salarioNeto) as totalNeto
                FROM DetallePlanilla
                WHERE idPlanilla = ?
            ", [$idPlanilla]);
            
            execute("UPDATE Planillas SET 
                    totalBruto = ?, totalINSS = ?, totalIR = ?, totalNeto = ? 
                    WHERE idPlanilla = ?", 
                   [$totales['totalBruto'], $totales['totalINSS'], $totales['totalIR'], $totales['totalNeto'], $idPlanilla]);
            
            $mensaje = "Planilla generada correctamente para el período $periodo";
            $tipo_mensaje = "success";
        }
    }
}

// Obtener planillas (CORREGIDO: LIMIT en lugar de TOP)
$planillas = fetchAll("
    SELECT 
        idPlanilla,
        periodo,
        fechaGeneracion,
        totalBruto,
        totalINSS,
        totalIR,
        totalNeto,
        estado,
        (SELECT COUNT(*) FROM DetallePlanilla WHERE idPlanilla = P.idPlanilla) as empleados
    FROM Planillas P
    ORDER BY idPlanilla DESC
");

// Obtener detalle de la última planilla (CORREGIDO: LIMIT en lugar de TOP)
$ultimaPlanilla = fetchOne("
    SELECT idPlanilla, periodo 
    FROM Planillas 
    ORDER BY idPlanilla DESC 
    LIMIT 1
");

$detallePlanilla = [];
if ($ultimaPlanilla) {
    $detallePlanilla = fetchAll("
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
    ", [$ultimaPlanilla['idPlanilla']]);
}
?>

<header class="header">
    <div>
        <h2>Gestión de Planilla</h2>
        <p>Generar y consultar planillas de empleados</p>
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
    <?php if (isset($mensaje)): ?>
        <div class="alert-system alert-<?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <!-- Generar Planilla -->
    <div class="card">
        <h3>Generar Nueva Planilla</h3>
        <form method="POST" action="" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="action" value="generar">
            <div class="form-group" style="flex:1; min-width:200px;">
                <label>Período (YYYY-MM)</label>
                <input type="text" name="periodo" class="form-control" value="<?php echo date('Y-m'); ?>" 
                       pattern="\d{4}-\d{2}" placeholder="2026-08" required>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-plus"></i> Generar Planilla
            </button>
        </form>
    </div>

    <!-- Lista de Planillas -->
    <h2 class="section-title">Planillas Generadas</h2>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Período</th>
                        <th>Fecha Generación</th>
                        <th>Total Bruto</th>
                        <th>Total INSS</th>
                        <th>Total IR</th>
                        <th>Total Neto</th>
                        <th>Empleados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($planillas)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No hay planillas generadas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($planillas as $p): ?>
                            <tr>
                                <td><?php echo $p['idPlanilla']; ?></td>
                                <td><strong><?php echo $p['periodo']; ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['fechaGeneracion'])); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['totalBruto'] ?? 0, 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['totalINSS'] ?? 0, 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['totalIR'] ?? 0, 2); ?></td>
                                <td><strong><?php echo MONEDA . ' ' . number_format($p['totalNeto'] ?? 0, 2); ?></strong></td>
                                <td><?php echo $p['empleados']; ?></td>
                                <td>
                                    <span class="badge paid"><?php echo $p['estado']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="verDetalle(<?php echo $p['idPlanilla']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarPlanilla(<?php echo $p['idPlanilla']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detalle de la última planilla -->
    <?php if (!empty($detallePlanilla)): ?>
        <h2 class="section-title">Detalle de Última Planilla (<?php echo $ultimaPlanilla['periodo']; ?>)</h2>
        <div class="card">
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
                        <?php foreach ($detallePlanilla as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['empleado']); ?></td>
                                <td><?php echo htmlspecialchars($d['cargo']); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($d['salarioBruto'], 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($d['inss'], 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($d['rentaNetaMensual'], 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($d['ir'], 2); ?></td>
                                <td><strong><?php echo MONEDA . ' ' . number_format($d['salarioNeto'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
function verDetalle(id) {
    window.open(`planilla_detalle.php?id=${id}`, '_blank', 'width=900,height=600');
}

function eliminarPlanilla(id) {
    if (confirm('¿Estás seguro de eliminar esta planilla?')) {
        fetch(`../api/planilla.php?action=eliminar&id=${id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la planilla');
                }
            });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>