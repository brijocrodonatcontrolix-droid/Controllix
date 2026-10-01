<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $sql = "INSERT INTO Proveedores (nombreEmpresa, contacto, telefono, correo, direccion) 
                VALUES (?, ?, ?, ?, ?)";
        execute($sql, [
            $_POST['nombreEmpresa'],
            $_POST['contacto'],
            $_POST['telefono'],
            $_POST['correo'],
            $_POST['direccion']
        ]);
        $mensaje = "Proveedor creado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        $sql = "UPDATE Proveedores SET 
                nombreEmpresa = ?, contacto = ?, telefono = ?, correo = ?, direccion = ?, estado = ?
                WHERE idProveedor = ?";
        execute($sql, [
            $_POST['nombreEmpresa'],
            $_POST['contacto'],
            $_POST['telefono'],
            $_POST['correo'],
            $_POST['direccion'],
            $_POST['estado'],
            $_POST['idProveedor']
        ]);
        $mensaje = "Proveedor actualizado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("UPDATE Proveedores SET estado = 0 WHERE idProveedor = ?", [$_POST['id']]);
        $mensaje = "Proveedor eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener proveedores
$proveedores = fetchAll("SELECT * FROM Proveedores ORDER BY nombreEmpresa");
?>

<header class="header">
    <div>
        <h2>Gestión de Proveedores</h2>
        <p>Administrar proveedores de la empresa</p>
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
        <h2 class="section-title">Lista de Proveedores</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Proveedor
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proveedores)): ?>
                        <tr><td colspan="7" class="text-center">No hay proveedores registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($proveedores as $p): ?>
                            <tr>
                                <td><?php echo $p['idProveedor']; ?></td>
                                <td><?php echo htmlspecialchars($p['nombreEmpresa']); ?></td>
                                <td><?php echo htmlspecialchars($p['contacto'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['telefono'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['correo'] ?? ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $p['estado'] ? 'paid' : 'pending'; ?>">
                                        <?php echo $p['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarProveedor(<?php echo $p['idProveedor']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarProveedor(<?php echo $p['idProveedor']; ?>)">
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
    <div id="modalProveedor" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nuevo Proveedor</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idProveedor" id="idProveedor" value="">
                
                <div class="form-group">
                    <label>Nombre de la Empresa</label>
                    <input type="text" name="nombreEmpresa" id="nombreEmpresa" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Contacto</label>
                    <input type="text" name="contacto" id="contacto" class="form-control">
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
                <div class="form-group" id="estadoGroup" style="display:none;">
                    <label>Estado</label>
                    <select name="estado" id="estado" class="form-control">
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
    document.getElementById('modalTitulo').textContent = 'Nuevo Proveedor';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idProveedor').value = '';
    document.getElementById('nombreEmpresa').value = '';
    document.getElementById('contacto').value = '';
    document.getElementById('telefono').value = '';
    document.getElementById('correo').value = '';
    document.getElementById('direccion').value = '';
    document.getElementById('estadoGroup').style.display = 'none';
    document.getElementById('modalProveedor').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalProveedor').style.display = 'none';
}

function editarProveedor(id) {
    // Obtener datos del proveedor
    fetch(`../api/proveedores.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Proveedor';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idProveedor').value = p.idProveedor;
                document.getElementById('nombreEmpresa').value = p.nombreEmpresa;
                document.getElementById('contacto').value = p.contacto || '';
                document.getElementById('telefono').value = p.telefono || '';
                document.getElementById('correo').value = p.correo || '';
                document.getElementById('direccion').value = p.direccion || '';
                document.getElementById('estado').value = p.estado;
                document.getElementById('estadoGroup').style.display = 'block';
                document.getElementById('modalProveedor').style.display = 'flex';
            }
        });
}

function eliminarProveedor(id) {
    if (confirm('¿Estás seguro de eliminar este proveedor?')) {
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