<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
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
            echo json_encode(['success' => true, 'data' => $planillas]);
            break;

        case 'detalle':
            $idPlanilla = $_GET['id'] ?? 0;
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
            ", [$idPlanilla]);
            echo json_encode(['success' => true, 'data' => $detalle]);
            break;

        case 'ultima':
            $planilla = fetchOne("
                SELECT 
                    idPlanilla,
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
            echo json_encode(['success' => true, 'data' => $planilla]);
            break;

        case 'generar':
            $periodo = $_POST['periodo'] ?? date('Y-m');
            
            // Verificar si ya existe
            $existe = fetchOne("SELECT COUNT(*) as total FROM Planillas WHERE periodo = ?", [$periodo]);
            if ($existe['total'] > 0) {
                echo json_encode(['success' => false, 'error' => 'Ya existe una planilla para este período']);
                break;
            }
            
            // Obtener empleados activos
            $empleados = fetchAll("SELECT idEmpleado, salarioBase FROM Empleados WHERE estado = 1");
            if (empty($empleados)) {
                echo json_encode(['success' => false, 'error' => 'No hay empleados activos']);
                break;
            }
            
            // Crear planilla
            $idPlanilla = insert("INSERT INTO Planillas (periodo) VALUES (?)", [$periodo]);
            
            // Insertar detalles
            foreach ($empleados as $emp) {
                insert("INSERT INTO DetallePlanilla (idPlanilla, idEmpleado, salarioBruto) VALUES (?, ?, ?)", 
                       [$idPlanilla, $emp['idEmpleado'], $emp['salarioBase']]);
            }
            
            // Calcular INSS
            execute("UPDATE DetallePlanilla SET inss = ROUND(salarioBruto * 0.07, 2) WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular renta mensual
            execute("UPDATE DetallePlanilla SET rentaNetaMensual = salarioBruto - inss WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular renta anual
            execute("UPDATE DetallePlanilla SET rentaNetaAnual = rentaNetaMensual * 12 WHERE idPlanilla = ?", [$idPlanilla]);
            
            // Calcular IR anual
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
            
            echo json_encode(['success' => true, 'id' => $idPlanilla]);
            break;

        case 'eliminar':
            $id = $_GET['id'] ?? 0;
            execute("DELETE FROM DetallePlanilla WHERE idPlanilla = ?", [$id]);
            execute("DELETE FROM Planillas WHERE idPlanilla = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>