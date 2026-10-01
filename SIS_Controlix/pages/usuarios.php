<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO Usuarios (idRol, nombres, apellidos, usuario, correo, password, telefono) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        execute($sql, [
            $_POST['idRol'],
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['usuario'],
            $_POST['correo'],
            $password_hash,
            $_POST['telefono']
        ]);
        $mensaje = "Usuario creado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        $sql = "UPDATE Usuarios SET 
                idRol = ?, nombres = ?, apellidos = ?, usuario = ?, 
                correo = ?, telefono = ?, estado = ?";
        $params = [
            $_POST['idRol'],
            $_POST['nombres'],
            $_POST['apellidos'],
            $_POST['usuario'],
            $_POST['correo'],
            $_POST['telefono'],
            $_POST['estado']
        ];
        
        // Si se envió nueva contraseña, actualizarla
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE idUsuario = ?";
        $params[] = $_POST['idUsuario'];
        
        execute($sql, $params);
        $mensaje = "Usuario actualizado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("UPDATE Usuarios SET estado = 0 WHERE idUsuario = ?", [$_POST['id']]);
        $mensaje = "Usuario eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener usuarios
$usuarios = fetchAll("
    SELECT 
        U.*,
        R.nombreRol
    FROM Usuarios U
    LEFT JOIN Roles R ON U.idRol = R.idRol
    ORDER BY U.idUsuario
");

// Obtener roles
$roles = fetchAll("SELECT * FROM Roles ORDER BY nombreRol");
?>

<header class="header">
    <div>
        <h2>Gestión de Usuarios</h2>
        <p>Administrar usuarios del sistema</p>
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
        <h2 class="section-title">Lista de Usuarios</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Usuario
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
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="8" class="text-center">No hay usuarios registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo $u['idUsuario']; ?></td>
                                <td><?php echo htmlspecialchars($u['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($u['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                                <td><?php echo htmlspecialchars($u['correo'] ?? ''); ?></td>
                                <td>
                                    <span class="badge" style="background:#dbeafe; color:#1d4ed8;">
                                        <?php echo htmlspecialchars($u['nombreRol'] ?? 'Sin rol'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $u['estado'] ? 'paid' : 'pending'; ?>">
                                        <?php echo $u['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarUsuario(<?php echo $u['idUsuario']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarUsuario(<?php echo $u['idUsuario']; ?>)">
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
    <div id="modalUsuario" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%; max-height:80vh; overflow-y:auto;">
            <h3 id="modalTitulo">Nuevo Usuario</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idUsuario" id="idUsuario" value="">
                
                <div class="form-group">
                    <label>Rol</label>
                    <select name="idRol" id="idRol" class="form-control" required>
                        <option value="">Seleccione un rol</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['idRol']; ?>">
                                <?php echo htmlspecialchars($r['nombreRol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nombres</label>
                    <input type="text" name="nombres" id="nombres" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" id="apellidos" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" name="usuario" id="usuario" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" id="correo" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="telefono" class="form-control">
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label>Contraseña</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                    <small style="color:#64748b;">Mínimo 6 caracteres</small>
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
    document.getElementById('modalTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idUsuario').value = '';
    document.getElementById('idRol').value = '';
    document.getElementById('nombres').value = '';
    document.getElementById('apellidos').value = '';
    document.getElementById('usuario').value = '';
    document.getElementById('correo').value = '';
    document.getElementById('telefono').value = '';
    document.getElementById('password').value = '';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('estadoGroup').style.display = 'none';
    document.getElementById('modalUsuario').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

function editarUsuario(id) {
    fetch(`../api/usuarios.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const u = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Usuario';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idUsuario').value = u.idUsuario;
                document.getElementById('idRol').value = u.idRol;
                document.getElementById('nombres').value = u.nombres;
                document.getElementById('apellidos').value = u.apellidos;
                document.getElementById('usuario').value = u.usuario;
                document.getElementById('correo').value = u.correo || '';
                document.getElementById('telefono').value = u.telefono || '';
                document.getElementById('password').value = '';
                document.getElementById('passwordGroup').querySelector('label').textContent = 'Nueva Contraseña (dejar vacío para no cambiar)';
                document.getElementById('password').required = false;
                document.getElementById('estado').value = u.estado;
                document.getElementById('estadoGroup').style.display = 'block';
                document.getElementById('modalUsuario').style.display = 'flex';
            }
        });
}

function eliminarUsuario(id) {
    if (confirm('¿Estás seguro de eliminar este usuario?')) {
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