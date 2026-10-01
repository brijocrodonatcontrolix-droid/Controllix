<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $sql = "INSERT INTO Empleados (nombres, apellidos, cedula, cargo, salarioBase, fechaIngreso) 
                VALUES (?, ?, ?, ?, ?, ?)";
        execute($sql, [
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['cedula'],
            $_POST['cargo'],
            $_POST['salarioBase'],
            $_POST['fechaIngreso']
        ]);
        $mensaje = "Empleado creado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        $sql = "UPDATE Empleados SET 
                nombres = ?, apellidos = ?, cedula = ?, cargo = ?, 
                salarioBase = ?, fechaIngreso = ?, estado = ?
                WHERE idEmpleado = ?";
        execute($sql, [
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['cedula'],
            $_POST['cargo'],
            $_POST['salarioBase'],
            $_POST['fechaIngreso'],
            $_POST['estado'],
            $_POST['idEmpleado']
        ]);
        $mensaje = "Empleado actualizado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("UPDATE Empleados SET estado = 0 WHERE idEmpleado = ?", [$_POST['id']]);
        $mensaje = "Empleado eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener empleados
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
?>

<header class="header">
    <div>
        <h2>Gestión de Empleados</h2>
        <p>Administrar los empleados de la empresa</p>
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

    <div class="flex-between mb-3">
        <h2 class="section-title">Lista de Empleados</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Empleado
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Cédula</th>
                        <th>Cargo</th>
                        <th>Salario Base</th>
                        <th>Fecha Ingreso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($empleados)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No hay empleados registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($empleados as $emp): ?>
                            <tr>
                                <td><?php echo $emp['idEmpleado']; ?></td>
                                <td><?php echo htmlspecialchars($emp['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($emp['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($emp['cedula']); ?></td>
                                <td><?php echo htmlspecialchars($emp['cargo']); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($emp['salarioBase'], 2); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($emp['fechaIngreso'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $emp['estado'] ? 'paid' : 'pending'; ?>">
                                        <?php echo $emp['estado_texto']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarEmpleado(<?php echo $emp['idEmpleado']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarEmpleado(<?php echo $emp['idEmpleado']; ?>)">
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

    <!-- Modal para empleados -->
    <div id="modalEmpleado" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nuevo Empleado</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idEmpleado" id="idEmpleado" value="">
                
                <div class="form-group">
                    <label>Nombres</label>
                    <input type="text" name="nombres" id="nombres" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" id="apellidos" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cédula</label>
                    <input type="text" name="cedula" id="cedula" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cargo</label>
                    <input type="text" name="cargo" id="cargo" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Salario Base (C$)</label>
                    <input type="number" name="salarioBase" id="salarioBase" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Fecha de Ingreso</label>
                    <input type="date" name="fechaIngreso" id="fechaIngreso" class="form-control" required>
                </div>
                <div class="form-group" id="estadoGroupEmpleado" style="display:none;">
                    <label>Estado</label>
                    <select name="estado" id="estadoEmpleado" class="form-control">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-danger" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function mostrarModal() {
    document.getElementById('modalTitulo').textContent = 'Nuevo Empleado';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idEmpleado').value = '';
    document.getElementById('nombres').value = '';
    document.getElementById('apellidos').value = '';
    document.getElementById('cedula').value = '';
    document.getElementById('cargo').value = '';
    document.getElementById('salarioBase').value = '';
    document.getElementById('fechaIngreso').value = '';
    document.getElementById('estadoGroupEmpleado').style.display = 'none';
    document.getElementById('modalEmpleado').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalEmpleado').style.display = 'none';
}

function editarEmpleado(id) {
    // Obtener datos del empleado
    fetch(`../api/empleados.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const emp = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Empleado';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idEmpleado').value = emp.idEmpleado;
                document.getElementById('nombres').value = emp.nombres;
                document.getElementById('apellidos').value = emp.apellidos;
                document.getElementById('cedula').value = emp.cedula;
                document.getElementById('cargo').value = emp.cargo;
                document.getElementById('salarioBase').value = emp.salarioBase;
                document.getElementById('fechaIngreso').value = emp.fechaIngreso;
                document.getElementById('estadoEmpleado').value = emp.estado;
                document.getElementById('estadoGroupEmpleado').style.display = 'block';
                document.getElementById('modalEmpleado').style.display = 'flex';
            }
        });
}

function eliminarEmpleado(id) {
    if (confirm('¿Estás seguro de eliminar este empleado?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="eliminar">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>