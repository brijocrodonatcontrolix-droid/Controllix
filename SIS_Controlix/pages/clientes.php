<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $sql = "INSERT INTO Clientes (nombres, apellidos, cedula, telefono, correo, direccion) 
                VALUES (?, ?, ?, ?, ?, ?)";
        execute($sql, [
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['cedula'],
            $_POST['telefono'],
            $_POST['correo'],
            $_POST['direccion']
        ]);
        $mensaje = "Cliente creado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        $sql = "UPDATE Clientes SET 
                nombres = ?, apellidos = ?, cedula = ?, telefono = ?, correo = ?, direccion = ?
                WHERE idCliente = ?";
        execute($sql, [
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['cedula'],
            $_POST['telefono'],
            $_POST['correo'],
            $_POST['direccion'],
            $_POST['idCliente']
        ]);
        $mensaje = "Cliente actualizado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("DELETE FROM Clientes WHERE idCliente = ?", [$_POST['id']]);
        $mensaje = "Cliente eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

$clientes = fetchAll("SELECT * FROM Clientes ORDER BY apellidos, nombres");
?>

<header class="header">
    <div>
        <h2>Gestión de Clientes</h2>
        <p>Administrar clientes de la empresa</p>
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
        <h2 class="section-title">Lista de Clientes</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Cliente
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
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr><td colspan="7" class="text-center">No hay clientes registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td><?php echo $c['idCliente']; ?></td>
                                <td><?php echo htmlspecialchars($c['nombres'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['apellidos'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['cedula'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['telefono'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['correo'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarCliente(<?php echo $c['idCliente']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarCliente(<?php echo $c['idCliente']; ?>)">
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

    <!-- Modal -->
    <div id="modalCliente" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nuevo Cliente</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idCliente" id="idCliente" value="">
                
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
                    <input type="text" name="cedula" id="cedula" class="form-control">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="telefono" class="form-control">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" id="correo" class="form-control">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <textarea name="direccion" id="direccion" class="form-control" rows="2"></textarea>
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
    document.getElementById('modalTitulo').textContent = 'Nuevo Cliente';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idCliente').value = '';
    document.getElementById('nombres').value = '';
    document.getElementById('apellidos').value = '';
    document.getElementById('cedula').value = '';
    document.getElementById('telefono').value = '';
    document.getElementById('correo').value = '';
    document.getElementById('direccion').value = '';
    document.getElementById('modalCliente').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalCliente').style.display = 'none';
}

function editarCliente(id) {
    fetch(`../api/clientes.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const c = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Cliente';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idCliente').value = c.idCliente;
                document.getElementById('nombres').value = c.nombres || '';
                document.getElementById('apellidos').value = c.apellidos || '';
                document.getElementById('cedula').value = c.cedula || '';
                document.getElementById('telefono').value = c.telefono || '';
                document.getElementById('correo').value = c.correo || '';
                document.getElementById('direccion').value = c.direccion || '';
                document.getElementById('modalCliente').style.display = 'flex';
            }
        });
}

function eliminarCliente(id) {
    if (confirm('¿Estás seguro de eliminar este cliente?')) {
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