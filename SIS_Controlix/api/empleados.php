<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $empleados = fetchAll("
                SELECT 
                    idEmpleado, 
                    nombres, 
                    apellidos, 
                    cedula, 
                    cargo, 
                    salarioBase, 
                    fechaIngreso,
                    CASE WHEN estado = 1 THEN 'Activo' ELSE 'Inactivo' END as estado_texto,
                    estado
                FROM Empleados 
                ORDER BY apellidos, nombres
            ");
            echo json_encode(['success' => true, 'data' => $empleados]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $empleado = fetchOne("SELECT * FROM Empleados WHERE idEmpleado = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $empleado]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO Empleados (nombres, apellidos, cedula, cargo, salarioBase, fechaIngreso) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $id = insert($sql, [
                $data['nombres'],
                $data['apellidos'],
                $data['cedula'],
                $data['cargo'],
                $data['salarioBase'],
                $data['fechaIngreso']
            ]);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE Empleados SET 
                    nombres = ?, apellidos = ?, cedula = ?, cargo = ?, 
                    salarioBase = ?, fechaIngreso = ?, estado = ? 
                    WHERE idEmpleado = ?";
            execute($sql, [
                $data['nombres'],
                $data['apellidos'],
                $data['cedula'],
                $data['cargo'],
                $data['salarioBase'],
                $data['fechaIngreso'],
                $data['estado'],
                $data['idEmpleado']
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'eliminar':
            $id = $_GET['id'] ?? 0;
            execute("UPDATE Empleados SET estado = 0 WHERE idEmpleado = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>