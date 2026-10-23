<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 NUEVO: Obtener todos los folios creados
$folios_query = mysqli_query($conn, "SELECT c.folio, c.fecha_compra, c.fecha_registro, c.total_compra, p.nombre as proveedor_nombre
                                                    FROM compras_cabecera c
                                                    LEFT JOIN proveedores p ON c.proveedor_id = p.id
                                                    ORDER BY c.fecha_registro DESC");
$folios = [];
while ($row = mysqli_fetch_assoc($folios_query)) {
    $folios[] = $row;
}

$folio_busqueda = $_GET['folio'] ?? '';
$compra = null;
$detalle = [];

if ($folio_busqueda) {
    // Buscar cabecera
    $sql = "SELECT c.*, p.nombre as proveedor_nombre, u.username 
            FROM compras_cabecera c
            LEFT JOIN proveedores p ON c.proveedor_id = p.id
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.folio = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $folio_busqueda);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $compra = mysqli_fetch_assoc($result);

    // Buscar detalle
    if ($compra) {
        $sql_detalle = "SELECT d.*, pr.nombre_producto, pr.precio_venta
                        FROM compras d
                        LEFT JOIN productos pr ON d.codigo_barra = pr.codigo_barra
                        WHERE d.compra_id = ?";
        $stmt_detalle = mysqli_prepare($conn, $sql_detalle);
        mysqli_stmt_bind_param($stmt_detalle, "i", $compra['id']);
        mysqli_stmt_execute($stmt_detalle);
        $detalle = mysqli_stmt_get_result($stmt_detalle);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>🔍 Buscar Compra por Folio</title>
    <link rel="stylesheet" href="../../css/Compras-STYLE/BuscarCompraFolio5.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>

        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i>
                <span class="tooltip">USUARIOS</span>
            </a>
            <a href="../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i>
                <span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i>
                <span class="tooltip">VENTAS</span>
            </a>
            <a href="../corte/corte_caja.php" class="icon-btn">
                <i class="bi bi-cash-coin"></i>
                <span class="tooltip">CAJA</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>🔍 Buscar Compra por Folio</h1>

        <form method="GET" class="filtros">
            <div class="filtro-group">
                <label>Folio de Compra</label>
                <input type="text" name="folio" placeholder="Ej: CMP-20260310-0001"
                    value="<?= htmlspecialchars($folio_busqueda) ?>" required>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn-filtrar">🔍 Buscar</button>
                <a href="buscar_compra_folio.php" class="btn-limpiar">🧹 Limpiar</a>
                <button type="button" class="btn-listar" onclick="mostrarListaFolios()">
                    📋 Ver Todos los Folios
                </button>
            </div>
        </form>

        <!-- 🔥 MODAL PARA MOSTRAR TODOS LOS FOLIOS -->
        <div id="modalFolios" class="modal-folios" style="display: none;">
            <div class="modal-content-folios">
                <div class="modal-header">
                    <h2>📋 Lista de Compras Registradas</h2>
                    <button type="button" class="btn-cerrar" onclick="cerrarModalFolios()">✕</button>
                </div>
                <div class="modal-body">
                    <?php if (count($folios) > 0): ?>
                        <table class="tabla-folios">
                            <thead>
                                <tr>
                                    <th>Número de Folio</th>
                                    <th>Fecha de Compra</th>
                                    <th>Proveedor</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($folios as $f): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($f['folio']) ?></strong></td>
                                        <td><?= date('d/m/Y h:i A', strtotime($f['fecha_compra'])) ?></td>
                                        <td><?= htmlspecialchars($f['proveedor_nombre'] ?? 'N/A') ?></td>
                                        <td>$<?= number_format($f['total_compra'], 2) ?></td>
                                        <td>
                                            <a href="buscar_compra_folio.php?folio=<?= urlencode($f['folio']) ?>"
                                                class="btn-ver-ticket">
                                                🔍 Ver Ticket
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="sin-datos">
                            <p>📭 No hay compras registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($compra): ?>
            <div class="ticket-compra"
                style="background:#fff; padding:30px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); margin-top:20px;">
                <h2 style="text-align:center; color:black; border-bottom:3px solid #0d6efd; padding-bottom:15px;">
                    📋 Ticket de Compra #
                    <?= htmlspecialchars($compra['folio']) ?>
                </h2>

                <div class="info-ticket"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin:20px 0; padding:15px; background:#f8f9fa; border-radius:8px;">
                    <div><strong>🏪 Proveedor:</strong>
                        <?= htmlspecialchars($compra['proveedor_nombre']) ?>
                    </div>
                    <div><strong>📅 Fecha y Hora:</strong>
                        <?= date('d/m/Y h:i A', strtotime($compra['fecha_compra'])) ?>
                    </div>
                    <div><strong>👤 Usuario:</strong>
                        <?= htmlspecialchars($compra['username']) ?>
                    </div>
                    <div><strong>🎫 Folio:</strong>
                        <?= htmlspecialchars($compra['folio']) ?>
                    </div>
                </div>

                <table class="tabla" style="margin-top:20px;">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>P. Compra</th>
                            <th>Subtotal</th>
                            <th>Ganancias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_items = 0;
                        $total_ganancias = 0;
                        while ($row = mysqli_fetch_assoc($detalle)):
                            $total_items += $row['total'];

                            // 🔥 CALCULAR GANANCIAS
                            $precio_venta = (float) $row['precio_venta'];
                            $precio_compra = (float) $row['precio_compra'];
                            $cantidad = (float) $row['cantidad'];

                            $ganancia_unitaria = $precio_venta - $precio_compra;
                            $ganancia_total = $ganancia_unitaria * $cantidad;
                            $porcentaje_ganancia = $precio_compra > 0 ? (($ganancia_unitaria / $precio_compra) * 100) : 0;

                            // 🔥 COLOR SEGÚN GANANCIA
                            $color_ganancia = $ganancia_total >= 0 ? '#28a745' : '#dc3545';
                            $total_ganancias += $ganancia_total;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['codigo_barra']) ?></td>
                                <td><?= htmlspecialchars($row['nombre_producto'] ?? $row['codigo_barra']) ?></td>
                                <td><?= $row['cantidad'] ?></td>
                                <td>$<?= number_format($row['precio_compra'], 2) ?></td>
                                <td>$<?= number_format($row['total'], 2) ?></td>
                                <!-- 🔥 NUEVA COLUMNA GANANCIAS -->
                                <td style="text-align:center;">
                                    <strong style="color:<?= $color_ganancia ?>;">
                                        $<?= number_format($ganancia_total, 2) ?>
                                    </strong>
                                    <br>
                                    <small style="color:#6c757d; font-size:11px;">
                                        (<?= number_format($porcentaje_ganancia, 1) ?>%)
                                    </small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#e7f3ff;">
                            <td colspan="5" style="text-align:right;">💰 Total Compra:</td>
                            <td style="text-align:center;">$<?= number_format($compra['total_compra'], 2) ?></td>
                        </tr>
                        <tr style="font-weight:bold; background:#d4edda;">
                            <td colspan="5" style="text-align:right;">💰 Ganancia Total:</td>
                            <td
                                style="text-align:center; font-size:18px; color:<?= $total_ganancias >= 0 ? '#28a745' : '#dc3545' ?>;">
                                $<?= number_format($total_ganancias, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- BOTONES DE ACCIÓN -->
                <div
                    style="text-align:center; margin-top:30px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <a href="registrar_compra.php" class="btn-nueva">🛒 Nueva Compra</a>
                    <a href="historial_compras.php" class="btn-volver">← Historial</a>

                    <!-- 🔥 BOTÓN EDITAR TICKET - ABRE MODAL -->
                    <button type="button" onclick="abrirModalEditarTicket()" class="btn-accion btn-editar">
                        <i class="bi bi-pencil-square"></i> Editar Ticket
                    </button>
                    <button type="button"
                        onclick="eliminarTicket('<?= htmlspecialchars($compra['folio']) ?>', <?= $compra['id'] ?>)"
                        class="btn-accion btn-eliminar">
                        <i class="bi bi-trash"></i> Eliminar Ticket
                    </button>
                </div>
            </div>
        <?php elseif ($folio_busqueda): ?>
            <div class="mensaje error" style="text-align:center; padding:20px;">
                ❌ No se encontró ninguna compra con el folio: <strong>
                    <?= htmlspecialchars($folio_busqueda) ?>
                </strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <script>
        // ============================================
        // MOSTRAR/OCULTAR MODAL DE FOLIOS
        // ============================================
        function mostrarListaFolios() {
            document.getElementById('modalFolios').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalFolios() {
            document.getElementById('modalFolios').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalFolios').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModalFolios();
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarModalFolios();
            }
        });
    </script>

    <script>
        // ============================================
        // 🔥 ELIMINAR TICKET COMPLETO
        // ============================================
        function eliminarTicket(folio, compra_id) {
            if (confirm('⚠️ ¿ESTÁS SEGURO DE ELIMINAR ESTE TICKET?\n\nFolio: ' + folio + '\n\nEsta acción eliminará:\n• Todos los productos del ticket\n• Los lotes creados\n• Se ajustará el inventario\n• Esta acción NO se puede deshacer\n\n¿Continuar?')) {

                fetch('eliminar_ticket_completo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        folio: folio,
                        compra_id: compra_id
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ ' + data.message);
                            window.location.href = 'buscar_compra_folio.php';
                        } else {
                            alert('❌ Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Error de conexión. Revisa la consola.');
                    });
            }
        }

        // ============================================
        // 🔥 EDITAR TICKET - REDIRECCIONAR
        // ============================================
        function editarTicket(folio) {
            window.location.href = 'editar_ticket_compra.php?folio=' + encodeURIComponent(folio);
        }
    </script>

    <!-- 🔥 MODAL PARA EDITAR TICKET -->
    <div id="modalEditarTicket" class="modal-editar-ticket" style="display:none;">
        <div class="modal-content-editar">
            <div class="modal-header-editar">
                <h2>✏️ Editar Ticket
                    <?= htmlspecialchars($compra['folio']) ?>
                </h2>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModalEditarTicket()">✕</button>
            </div>

            <div class="modal-body-editar">
                <!-- Información del Ticket -->
                <div class="info-ticket-editar">
                    <div><strong>🏪 Proveedor:</strong>
                        <?= htmlspecialchars($compra['proveedor_nombre']) ?>
                    </div>
                    <div><strong>📅 Fecha:</strong>
                        <?= date('d/m/Y g:i A', strtotime($compra['fecha_compra'])) ?>
                    </div>
                    <div><strong>🎫 Folio:</strong>
                        <?= htmlspecialchars($compra['folio']) ?>
                    </div>
                </div>

                <!-- Tabla de Productos Editables -->
                <table class="tabla-editar-ticket">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>P. Compra</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductosEditar">
                        <?php
                        mysqli_data_seek($detalle, 0); // Reiniciar resultado
                        while ($row = mysqli_fetch_assoc($detalle)):
                            ?>
                            <tr data-codigo="<?= $row['codigo_barra'] ?>" data-id="<?= $row['id'] ?>">
                                <td>
                                    <?= htmlspecialchars($row['codigo_barra']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nombre_producto'] ?? $row['codigo_barra']) ?>
                                </td>
                                <td>
                                    <input type="number" class="input-cantidad-editar" value="<?= $row['cantidad'] ?>"
                                        min="0.01" step="0.01" data-precio="<?= $row['precio_compra'] ?>"
                                        style="width:80px; padding:5px; border:1px solid #ddd; border-radius:4px;">
                                </td>
                                <td>
                                    <input type="number" class="input-precio-editar" value="<?= $row['precio_compra'] ?>"
                                        min="0.01" step="0.01"
                                        style="width:100px; padding:5px; border:1px solid #ddd; border-radius:4px;">
                                </td>
                                <td class="subtotal-editar">$
                                    <?= number_format($row['total'], 2) ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-eliminar-producto"
                                        onclick="eliminarProductoDelTicket(this, '<?= $row['id'] ?>')"
                                        title="Eliminar producto">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#e7f3ff;">
                            <td colspan="4" style="text-align:right;">Total:</td>
                            <td id="totalEditarTicket">$
                                <?= number_format($compra['total_compra'], 2) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Botones de Acción -->
                <div class="modal-footer-editar">
                    <button type="button" onclick="guardarCambiosTicket()" class="btn-guardar-cambios">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                    <button type="button" onclick="cerrarModalEditarTicket()" class="btn-cancelar-cambios">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        // ============================================
        // 🔥 ABRIR/CERRAR MODAL EDITAR TICKET
        // ============================================
        function abrirModalEditarTicket() {
            document.getElementById('modalEditarTicket').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            calcularTotalEditarTicket();
        }

        function cerrarModalEditarTicket() {
            document.getElementById('modalEditarTicket').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalEditarTicket').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModalEditarTicket();
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarModalEditarTicket();
            }
        });

        // ============================================
        // 🔥 ACTUALIZAR SUBTOTALES Y TOTAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Escuchar cambios en cantidad
            document.querySelectorAll('.input-cantidad-editar').forEach(input => {
                input.addEventListener('change', function () {
                    actualizarFilaEditar(this);
                    calcularTotalEditarTicket();
                });
            });

            // Escuchar cambios en precio
            document.querySelectorAll('.input-precio-editar').forEach(input => {
                input.addEventListener('change', function () {
                    actualizarFilaEditar(this);
                    calcularTotalEditarTicket();
                });
            });
        });

        function actualizarFilaEditar(input) {
            const row = input.closest('tr');
            const cantidadInput = row.querySelector('.input-cantidad-editar');
            const precioInput = row.querySelector('.input-precio-editar');

            const cantidad = parseFloat(cantidadInput.value) || 0;
            const precio = parseFloat(precioInput.value) || 0;
            const subtotal = cantidad * precio;

            row.querySelector('.subtotal-editar').textContent = '$' + subtotal.toFixed(2);
        }

        function calcularTotalEditarTicket() {
            let total = 0;
            document.querySelectorAll('.subtotal-editar').forEach(cell => {
                const text = cell.textContent.replace('$', '').replace(',', '');
                total += parseFloat(text) || 0;
            });
            document.getElementById('totalEditarTicket').textContent = '$' + total.toFixed(2);
        }

        // ============================================
        // 🔥 ELIMINAR PRODUCTO DEL TICKET
        // ============================================
        function eliminarProductoDelTicket(btn, idDetalle) {
            if (confirm('¿Eliminar este producto del ticket?')) {
                const row = btn.closest('tr');
                row.remove();
                calcularTotalEditarTicket();
            }
        }

        // ============================================
        // 🔥 GUARDAR CAMBIOS DEL TICKET
        // ============================================
        function guardarCambiosTicket() {
            const productos = [];
            const folio = '<?= htmlspecialchars($compra['folio']) ?>';
            const compraId = <?= $compra['id'] ?>;

            console.log('🔍 Iniciando guardado...');
            console.log('Folio:', folio);
            console.log('Compra ID:', compraId);

            document.querySelectorAll('#tbodyProductosEditar tr').forEach(row => {
                const codigo = row.dataset.codigo;
                const idDetalle = row.dataset.id;
                const cantidad = parseFloat(row.querySelector('.input-cantidad-editar').value) || 0;
                const precio = parseFloat(row.querySelector('.input-precio-editar').value) || 0;

                if (cantidad > 0) {
                    productos.push({
                        id_detalle: idDetalle,
                        codigo: codigo,
                        cantidad: cantidad,
                        precio: precio,
                        subtotal: cantidad * precio
                    });
                }
            });

            console.log('📦 Productos a actualizar:', productos.length);

            if (productos.length === 0) {
                alert('❌ Debe haber al menos un producto en el ticket');
                return;
            }

            if (confirm('¿Confirmar cambios en el ticket?\nSe actualizarán:\n• Cantidades\n• Precios\n• Totales\n• Inventario')) {
                // 🔥 MOSTRAR INDICADOR DE CARGA
                const btnGuardar = document.querySelector('.btn-guardar-cambios');
                const textoOriginal = btnGuardar.innerHTML;
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

                // Enviar datos al servidor
                fetch('actualizar_ticket_compra.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        folio: folio,
                        compra_id: compraId,
                        productos: productos
                    })
                })
                    .then(response => {
                        console.log('📡 Respuesta del servidor:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('📊 Datos recibidos:', data);

                        if (data.success) {
                            alert('✅ ' + data.message);
                            cerrarModalEditarTicket();
                            // Recargar página para ver cambios
                            window.location.reload();
                        } else {
                            alert('❌ Error: ' + data.message);
                            btnGuardar.disabled = false;
                            btnGuardar.innerHTML = textoOriginal;
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error:', error);
                        alert('❌ Error de conexión. Revisa la consola (F12).');
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = textoOriginal;
                    });
            }
        }
    </script>
</body>

</html>