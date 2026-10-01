<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        execute("INSERT INTO Categorias (nombreCategoria, descripcion) VALUES (?, ?)", [
            $_POST['nombreCategoria'],
            $_POST['descripcion']
        ]);
        $mensaje = "Categoría creada correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        execute("UPDATE Categorias SET nombreCategoria = ?, descripcion = ? WHERE idCategoria = ?", [
            $_POST['nombreCategoria'],
            $_POST['descripcion'],
            $_POST['idCategoria']
        ]);
        $mensaje = "Categoría actualizada correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("DELETE FROM Categorias WHERE idCategoria = ?", [$_POST['id']]);
        $mensaje = "Categoría eliminada correctamente";
        $tipo_mensaje = "success";
    }
}

$categorias = fetchAll("SELECT * FROM Categorias ORDER BY nombreCategoria");
?>

<header class="header">
    <div>
        <h2>Gestión de Categorías</h2>
        <p>Administrar categorías de productos</p>
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
        <h2 class="section-title">Lista de Categorías</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nueva Categoría
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr><td colspan="4" class="text-center">No hay categorías registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $c): ?>
                            <tr>
                                <td><?php echo $c['idCategoria']; ?></td>
                                <td><?php echo htmlspecialchars($c['nombreCategoria']); ?></td>
                                <td><?php echo htmlspecialchars($c['descripcion'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarCategoria(<?php echo $c['idCategoria']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarCategoria(<?php echo $c['idCategoria']; ?>)">
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
    <div id="modalCategoria" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nueva Categoría</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idCategoria" id="idCategoria" value="">
                
                <div class="form-group">
                    <label>Nombre de la Categoría</label>
                    <input type="text" name="nombreCategoria" id="nombreCategoria" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
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
    document.getElementById('modalTitulo').textContent = 'Nueva Categoría';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idCategoria').value = '';
    document.getElementById('nombreCategoria').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('modalCategoria').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalCategoria').style.display = 'none';
}

function editarCategoria(id) {
    // Obtener datos de la categoría
    fetch(`../api/categorias.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const c = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Categoría';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idCategoria').value = c.idCategoria;
                document.getElementById('nombreCategoria').value = c.nombreCategoria;
                document.getElementById('descripcion').value = c.descripcion || '';
                document.getElementById('modalCategoria').style.display = 'flex';
            }
        });
}

function eliminarCategoria(id) {
    if (confirm('¿Estás seguro de eliminar esta categoría?')) {
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