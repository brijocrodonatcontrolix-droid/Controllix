<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        execute("INSERT INTO Marcas (nombreMarca) VALUES (?)", [$_POST['nombreMarca']]);
        $mensaje = "Marca creada correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        execute("UPDATE Marcas SET nombreMarca = ? WHERE idMarca = ?", [
            $_POST['nombreMarca'],
            $_POST['idMarca']
        ]);
        $mensaje = "Marca actualizada correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("DELETE FROM Marcas WHERE idMarca = ?", [$_POST['id']]);
        $mensaje = "Marca eliminada correctamente";
        $tipo_mensaje = "success";
    }
}

$marcas = fetchAll("SELECT * FROM Marcas ORDER BY nombreMarca");
?>

<header class="header">
    <div>
        <h2>Gestión de Marcas</h2>
        <p>Administrar marcas de productos</p>
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
        <h2 class="section-title">Lista de Marcas</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nueva Marca
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de la Marca</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($marcas)): ?>
                        <tr><td colspan="3" class="text-center">No hay marcas registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($marcas as $m): ?>
                            <tr>
                                <td><?php echo $m['idMarca']; ?></td>
                                <td><?php echo htmlspecialchars($m['nombreMarca']); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarMarca(<?php echo $m['idMarca']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarMarca(<?php echo $m['idMarca']; ?>)">
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
    <div id="modalMarca" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nueva Marca</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idMarca" id="idMarca" value="">
                
                <div class="form-group">
                    <label>Nombre de la Marca</label>
                    <input type="text" name="nombreMarca" id="nombreMarca" class="form-control" required>
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
    document.getElementById('modalTitulo').textContent = 'Nueva Marca';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idMarca').value = '';
    document.getElementById('nombreMarca').value = '';
    document.getElementById('modalMarca').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalMarca').style.display = 'none';
}

function editarMarca(id) {
    fetch(`../api/marcas.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const m = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Marca';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idMarca').value = m.idMarca;
                document.getElementById('nombreMarca').value = m.nombreMarca;
                document.getElementById('modalMarca').style.display = 'flex';
            }
        });
}

function eliminarMarca(id) {
    if (confirm('¿Estás seguro de eliminar esta marca?')) {
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