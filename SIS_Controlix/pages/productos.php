<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Obtener datos para selects
$categorias = fetchAll("SELECT idCategoria, nombreCategoria FROM Categorias ORDER BY nombreCategoria");
$marcas = fetchAll("SELECT idMarca, nombreMarca FROM Marcas ORDER BY nombreMarca");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $sql = "INSERT INTO Productos (idCategoria, idMarca, codigoBarra, nombreProducto, descripcion, 
                precioCompra, precioVenta, stockMinimo, stockActual) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        execute($sql, [
            $_POST['idCategoria'],
            $_POST['idMarca'],
            $_POST['codigoBarra'],
            $_POST['nombreProducto'],
            $_POST['descripcion'],
            $_POST['precioCompra'],
            $_POST['precioVenta'],
            $_POST['stockMinimo'],
            $_POST['stockActual']
        ]);
        $mensaje = "Producto creado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'editar') {
        $sql = "UPDATE Productos SET 
                idCategoria = ?, idMarca = ?, codigoBarra = ?, nombreProducto = ?, 
                descripcion = ?, precioCompra = ?, precioVenta = ?, stockMinimo = ?, 
                stockActual = ?, estado = ?
                WHERE idProducto = ?";
        execute($sql, [
            $_POST['idCategoria'],
            $_POST['idMarca'],
            $_POST['codigoBarra'],
            $_POST['nombreProducto'],
            $_POST['descripcion'],
            $_POST['precioCompra'],
            $_POST['precioVenta'],
            $_POST['stockMinimo'],
            $_POST['stockActual'],
            $_POST['estado'],
            $_POST['idProducto']
        ]);
        $mensaje = "Producto actualizado correctamente";
        $tipo_mensaje = "success";
    } elseif ($action === 'eliminar') {
        execute("UPDATE Productos SET estado = 0 WHERE idProducto = ?", [$_POST['id']]);
        $mensaje = "Producto eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

$productos = fetchAll("
    SELECT 
        P.*,
        C.nombreCategoria,
        M.nombreMarca
    FROM Productos P
    LEFT JOIN Categorias C ON P.idCategoria = C.idCategoria
    LEFT JOIN Marcas M ON P.idMarca = M.idMarca
    WHERE P.estado = 1
    ORDER BY P.nombreProducto
");
?>

<header class="header">
    <div>
        <h2>Gestión de Productos</h2>
        <p>Administrar productos del inventario</p>
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
        <h2 class="section-title">Lista de Productos</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Precio Venta</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr><td colspan="9" class="text-center">No hay productos registrados</td></tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td><?php echo $p['idProducto']; ?></td>
                                <td><?php echo htmlspecialchars($p['codigoBarra'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['nombreProducto']); ?></td>
                                <td><?php echo htmlspecialchars($p['nombreCategoria'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['nombreMarca'] ?? ''); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($p['precioVenta'], 2); ?></td>
                                <td>
                                    <span class="<?php echo $p['stockActual'] <= $p['stockMinimo'] ? 'badge pending' : ''; ?>">
                                        <?php echo $p['stockActual']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['estado'] ? 'paid' : 'pending'; ?>">
                                        <?php echo $p['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarProducto(<?php echo $p['idProducto']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarProducto(<?php echo $p['idProducto']; ?>)">
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
    <div id="modalProducto" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:600px; width:100%; max-height:80vh; overflow-y:auto;">
            <h3 id="modalTitulo">Nuevo Producto</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idProducto" id="idProducto" value="">
                
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="idCategoria" id="idCategoria" class="form-control" required>
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo $c['idCategoria']; ?>">
                                <?php echo htmlspecialchars($c['nombreCategoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Marca</label>
                    <select name="idMarca" id="idMarca" class="form-control" required>
                        <option value="">Seleccione una marca</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?php echo $m['idMarca']; ?>">
                                <?php echo htmlspecialchars($m['nombreMarca']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Código de Barra</label>
                    <input type="text" name="codigoBarra" id="codigoBarra" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Nombre del Producto</label>
                    <input type="text" name="nombreProducto" id="nombreProducto" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Precio Compra (C$)</label>
                        <input type="number" name="precioCompra" id="precioCompra" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Precio Venta (C$)</label>
                        <input type="number" name="precioVenta" id="precioVenta" class="form-control" step="0.01" required>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Stock Mínimo</label>
                        <input type="number" name="stockMinimo" id="stockMinimo" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Stock Actual</label>
                        <input type="number" name="stockActual" id="stockActual" class="form-control" value="0">
                    </div>
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
    document.getElementById('modalTitulo').textContent = 'Nuevo Producto';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idProducto').value = '';
    document.getElementById('idCategoria').value = '';
    document.getElementById('idMarca').value = '';
    document.getElementById('codigoBarra').value = '';
    document.getElementById('nombreProducto').value = '';
    document.getElementById('descripcion').value = '';
    document.getElementById('precioCompra').value = '';
    document.getElementById('precioVenta').value = '';
    document.getElementById('stockMinimo').value = '0';
    document.getElementById('stockActual').value = '0';
    document.getElementById('estadoGroup').style.display = 'none';
    document.getElementById('modalProducto').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalProducto').style.display = 'none';
}

function editarProducto(id) {
    fetch(`../api/productos.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Producto';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idProducto').value = p.idProducto;
                document.getElementById('idCategoria').value = p.idCategoria;
                document.getElementById('idMarca').value = p.idMarca;
                document.getElementById('codigoBarra').value = p.codigoBarra || '';
                document.getElementById('nombreProducto').value = p.nombreProducto;
                document.getElementById('descripcion').value = p.descripcion || '';
                document.getElementById('precioCompra').value = p.precioCompra || '';
                document.getElementById('precioVenta').value = p.precioVenta;
                document.getElementById('stockMinimo').value = p.stockMinimo || 0;
                document.getElementById('stockActual').value = p.stockActual || 0;
                document.getElementById('estado').value = p.estado;
                document.getElementById('estadoGroup').style.display = 'block';
                document.getElementById('modalProducto').style.display = 'flex';
            }
        });
}

function eliminarProducto(id) {
    if (confirm('¿Estás seguro de eliminar este producto?')) {
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