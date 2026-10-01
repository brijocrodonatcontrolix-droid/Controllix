<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// ============================================
// PROCESAR ENVÍO DE CORREO (VERSIÓN MEJORADA)
// ============================================

$mensaje = '';
$tipo_mensaje = '';

if (isset($_GET['enviar_email']) && isset($_GET['id'])) {
    $idVenta = intval($_GET['id']);
    
    // Obtener datos de la venta
    $venta = fetchOne("
        SELECT 
            V.*,
            CONCAT(C.nombres, ' ', C.apellidos) as cliente,
            C.correo as email_cliente,
            F.numeroFactura,
            U.nombres as usuario_nombre
        FROM Ventas V
        LEFT JOIN Clientes C ON V.idCliente = C.idCliente
        LEFT JOIN Facturas F ON V.idVenta = F.idVenta
        LEFT JOIN Usuarios U ON V.idUsuario = U.idUsuario
        WHERE V.idVenta = ?
    ", [$idVenta]);
    
    if (!$venta) {
        $mensaje = "Error: Venta no encontrada";
        $tipo_mensaje = "danger";
    } elseif (empty($venta['email_cliente'])) {
        $mensaje = "Error: El cliente no tiene correo electrónico registrado";
        $tipo_mensaje = "danger";
    } else {
        // Obtener detalles
        $detalle = fetchAll("
            SELECT 
                DF.*,
                P.nombreProducto
            FROM DetalleFacturas DF
            INNER JOIN Productos P ON DF.idProducto = P.idProducto
            WHERE DF.idFactura = (SELECT idFactura FROM Facturas WHERE idVenta = ?)
        ", [$idVenta]);
        
        // Construir HTML del correo
        $html = construirHTMLFactura($venta, $detalle);
        
        // ============================================
        // INTENTAR ENVIAR CORREO CON MAIL()
        // ============================================
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: SIS_Controlix <siscontrolix@localhost>\r\n";
        $headers .= "Reply-To: siscontrolix@localhost\r\n";
        
        // Intentar enviar
        $enviado = @mail($venta['email_cliente'], "Factura " . $venta['numeroFactura'] . " - SIS_Controlix", $html, $headers);
        
        if ($enviado) {
            $mensaje = "✅ Factura enviada correctamente a " . htmlspecialchars($venta['email_cliente']);
            $tipo_mensaje = "success";
        } else {
            // ============================================
            // SI FALLA, GUARDAR EN CARPETA Y MOSTRAR MENSAJE
            // ============================================
            
            // Crear carpeta si no existe
            $carpeta = __DIR__ . '/../facturas_enviadas/';
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }
            
            // Guardar factura en archivo HTML
            $nombreArchivo = 'factura_' . $venta['numeroFactura'] . '_' . date('Y-m-d_H-i-s') . '.html';
            $rutaArchivo = $carpeta . $nombreArchivo;
            file_put_contents($rutaArchivo, $html);
            
            // También guardar en formato TXT
            $rutaTxt = $carpeta . 'factura_' . $venta['numeroFactura'] . '_' . date('Y-m-d_H-i-s') . '.txt';
            file_put_contents($rutaTxt, strip_tags($html));
            
            $mensaje = "⚠️ No se pudo enviar el correo electrónico. La factura se ha guardado en la carpeta 'facturas_enviadas' del sistema.<br>
                        <strong>Cliente:</strong> " . htmlspecialchars($venta['email_cliente']) . "<br>
                        <strong>Archivo:</strong> " . $nombreArchivo;
            $tipo_mensaje = "warning";
        }
    }
    
    // Redirigir con mensaje
    header("Location: facturas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
    exit();
}

// ============================================
// FUNCIÓN PARA CONSTRUIR HTML DE FACTURA
// ============================================

function construirHTMLFactura($venta, $detalle) {
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Factura {$venta['numeroFactura']}</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 20px; }
            .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 20px; margin-bottom: 25px; }
            .header h1 { color: #0f172a; font-size: 24px; margin: 0; }
            .header p { color: #64748b; font-size: 13px; margin-top: 5px; }
            .header .factura-num { margin-top:10px; font-size:18px; font-weight:bold; color:#1d4ed8; }
            .info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .info-item { font-size: 13px; }
            .info-item strong { color: #0f172a; display: block; }
            .info-item span { color: #475569; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #1d4ed8; color: white; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; }
            td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            .totales { background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 20px; }
            .total-line { display: flex; justify-content: space-between; padding: 5px 0; font-size: 14px; }
            .total-line.total { font-size: 18px; font-weight: bold; color: #0f172a; border-top: 2px solid #1d4ed8; padding-top: 10px; margin-top: 5px; }
            .footer { text-align: center; color: #64748b; font-size: 12px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
            .btn { display: inline-block; background: #1d4ed8; color: white; padding: 10px 25px; text-decoration: none; border-radius: 6px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SIS_Controlix</h1>
                <p>Sistema de Control Empresarial</p>
                <div class='factura-num'>FACTURA: {$venta['numeroFactura']}</div>
            </div>
            
            <div class='info'>
                <div class='info-item'><strong>Cliente</strong><span>{$venta['cliente']}</span></div>
                <div class='info-item'><strong>Fecha</strong><span>" . date('d/m/Y H:i', strtotime($venta['fechaVenta'])) . "</span></div>
                <div class='info-item'><strong>Vendedor</strong><span>{$venta['usuario_nombre']}</span></div>
                <div class='info-item'><strong>Estado</strong><span>Pagada</span></div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style='width:50%;'>Producto</th>
                        <th style='width:15%; text-align:center;'>Cantidad</th>
                        <th style='width:17%; text-align:right;'>Precio Unit.</th>
                        <th style='width:18%; text-align:right;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($detalle as $d) {
        $html .= "
                    <tr>
                        <td>{$d['nombreProducto']}</td>
                        <td style='text-align:center;'>{$d['cantidad']}</td>
                        <td style='text-align:right;'>C$ " . number_format($d['precioUnitario'], 2) . "</td>
                        <td style='text-align:right;'>C$ " . number_format($d['subtotal'], 2) . "</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
            
            <div class='totales'>
                <div class='total-line'><span>Subtotal</span><span>C$ " . number_format($venta['subtotal'], 2) . "</span></div>";
    
    if ($venta['descuento'] > 0) {
        $html .= "  <div class='total-line'><span>Descuento</span><span style='color:#dc2626;'>- C$ " . number_format($venta['descuento'], 2) . "</span></div>";
    }
    
    $html .= "
                <div class='total-line'><span>IVA (15%)</span><span>C$ " . number_format($venta['iva'], 2) . "</span></div>
                <div class='total-line total'><span>TOTAL</span><span>C$ " . number_format($venta['total'], 2) . "</span></div>
            </div>
            
            <div style='text-align:center; margin-top:25px;'>
                <a href='http://localhost/SIS_Controlix/pages/venta_detalle.php?id={$venta['idVenta']}' class='btn'>Ver Factura en Línea</a>
            </div>
            
            <div class='footer'>
                <p>SIS_Controlix © " . date('Y') . " | Sistema de Control Empresarial</p>
                <p>Moneda: Córdobas Nicaragüenses (C$)</p>
                <p>Este correo es generado automáticamente. Por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

// ============================================
// MOSTRAR MENSAJES
// ============================================

if (isset($_GET['mensaje'])) {
    $mensaje = urldecode($_GET['mensaje']);
    $tipo_mensaje = $_GET['tipo'] ?? 'success';
}

// ============================================
// OBTENER FACTURAS
// ============================================

$facturas = fetchAll("
    SELECT 
        F.*,
        V.idVenta,
        CONCAT(C.nombres, ' ', C.apellidos) as cliente,
        C.correo as email_cliente
    FROM Facturas F
    INNER JOIN Ventas V ON F.idVenta = V.idVenta
    LEFT JOIN Clientes C ON V.idCliente = C.idCliente
    ORDER BY F.fecha DESC
");
?>

<header class="header">
    <div>
        <h2>Gestión de Facturas</h2>
        <p>Administrar facturas emitidas</p>
    </div>
    <div class="header-right">
        <div class="notification"><i class="fas fa-bell"></i><span>4</span></div>
        <div class="user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'AD', 0, 2)); ?></div>
            <div class="user-info">
                <strong><?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></strong>
                <small>Administrador</small>
            </div>
        </div>
    </div>
</header>

<section class="content">
    <?php if ($mensaje): ?>
        <div class="alert-system alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="flex-between mb-3">
        <h2 class="section-title">Lista de Facturas</h2>
        <button class="btn btn-primary" onclick="window.location.href='ventas.php'">
            <i class="fas fa-plus"></i> Nueva Factura
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>N° Factura</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facturas)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay facturas registradas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td><?php echo $f['idFactura']; ?></td>
                                <td><strong><?php echo htmlspecialchars($f['numeroFactura']); ?></strong></td>
                                <td><?php echo htmlspecialchars($f['cliente'] ?? 'Cliente General'); ?></td>
                                <td>
                                    <?php if (!empty($f['email_cliente'])): ?>
                                        <small style="color:#64748b;"><?php echo htmlspecialchars($f['email_cliente']); ?></small>
                                    <?php else: ?>
                                        <span class="badge pending">Sin email</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($f['fecha'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $f['estado'] == 'Pagada' ? 'paid' : 'pending'; ?>">
                                        <?php echo $f['estado']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <button class="btn btn-info btn-sm" onclick="verFactura(<?php echo $f['idVenta']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick="imprimirFactura(<?php echo $f['idVenta']; ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php if (!empty($f['email_cliente'])): ?>
                                            <button class="btn btn-primary btn-sm" onclick="enviarEmail(<?php echo $f['idVenta']; ?>)" title="Enviar por correo">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarFactura(<?php echo $f['idFactura']; ?>, <?php echo $f['idVenta']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function verFactura(id) {
    window.open(`venta_detalle.php?id=${id}`, '_blank', 'width=900,height=700');
}

function imprimirFactura(id) {
    window.open(`venta_detalle.php?id=${id}`, '_blank', 'width=900,height=700');
}

function enviarEmail(id) {
    if (confirm('¿Enviar esta factura por correo electrónico al cliente?')) {
        window.location.href = `facturas.php?enviar_email=1&id=${id}`;
    }
}

function eliminarFactura(idFactura, idVenta) {
    if (confirm('¿Estás seguro de eliminar esta factura?')) {
        fetch(`../api/facturas.php?action=eliminar&idFactura=${idFactura}&idVenta=${idVenta}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la factura');
                }
            });
    }
}
</script>

<style>
.btn-primary {
    background: #2563eb;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
}
.btn-primary:hover {
    background: #1d4ed8;
}
</style>

<?php require_once '../includes/footer.php'; ?>