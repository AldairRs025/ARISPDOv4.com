<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// Filtros
$filtro_proveedor = $_GET['proveedor'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';

$where = [];
if ($filtro_proveedor)
    $where[] = "h.proveedor_id = '" . mysqli_real_escape_string($conn, $filtro_proveedor) . "'";
if ($filtro_fecha_inicio)
    $where[] = "h.fecha_compra >= '" . mysqli_real_escape_string($conn, $filtro_fecha_inicio) . "'";
if ($filtro_fecha_fin)
    $where[] = "h.fecha_compra <= '" . mysqli_real_escape_string($conn, $filtro_fecha_fin) . "'";

// 🔥 CONSULTAR SOLO TABLA DE HISTORIAL
$sql = "SELECT h.*, p.nombre as proveedor_nombre 
        FROM historial_compras_cabecera h
        LEFT JOIN proveedores p ON h.proveedor_id = p.id
        " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . "
        ORDER BY h.fecha_compra DESC";
$compras = mysqli_query($conn, $sql);

// Total
$totalSQL = "SELECT SUM(h.total_compra) as total_compras 
             FROM historial_compras_cabecera h
             " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "");
$total = mysqli_fetch_assoc(mysqli_query($conn, $totalSQL))['total_compras'] ?? 0;

$proveedores = mysqli_query($conn, "SELECT * FROM proveedores ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>📋 Historial de Compras - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Compras-STYLE/Historial4.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-family: 'Kalam', cursive;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
        }

        .tab-btn.active {
            background: #0d6efd;
            color: white;
        }
    </style>
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>
        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i><span class="tooltip">INICIO</span>
            </a>
            <a href="../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i><span class="tooltip">USUARIOS</span>
            </a>
            <a href="../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i><span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i><span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i><span class="tooltip">VENTAS</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i><span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>📋 Historial de Compras</h1>

        <!-- 🔥 BOTÓN DEPURAR HISTORIAL -->
        <button type="button" onclick="abrirModalDepurar()" class="btn-depurar">
            <i class="bi bi-trash3"></i> Depurar Historial
        </button>

        <a href="registrar_compra.php" class="btn-nueva">➕ Nueva Compra</a>

        <!-- FILTROS -->
        <form method="GET" class="filtros">
            <div class="filtro-group">
                <label>Proveedor</label>
                <select name="proveedor">
                    <option value="">Todos</option>
                    <?php while ($prov = mysqli_fetch_assoc($proveedores)): ?>
                        <option value="<?= $prov['id'] ?>" <?= $filtro_proveedor == $prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?= $filtro_fecha_inicio ?>">
            </div>
            <div class="filtro-group">
                <label>Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?= $filtro_fecha_fin ?>">
            </div>
            <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
            <a href="historial_compras.php" class="btn-limpiar">🧹 Limpiar</a>
        </form>

        <div class="total-compras">
            💰 Total en Historial: $<?= number_format($total, 2) ?>
        </div>

        <!-- TABLA DE HISTORIAL -->
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>Proveedor</th>
                    <th>Total</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($compra = mysqli_fetch_assoc($compras)): ?>
                    <tr>
                        <td><?= date('d/m/Y h:i A', strtotime($compra['fecha_registro'])) ?></td>
                        <td><strong><?= htmlspecialchars($compra['folio']) ?></strong></td>
                        <td><?= htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A') ?></td>
                        <td>$<?= number_format($compra['total_compra'], 2) ?></td>
                        <td>ID: <?= $compra['usuario_id'] ?></td>
                        <td>
                            <a href="buscar_compra_folio.php?folio=<?= urlencode($compra['folio']) ?>" class="btn-ver">
                                🔍 Ver
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- MODAL DEPURAR -->
    <div id="modalDepurar" class="modal-depurar" style="display: none;">
        <div class="modal-content-depurar">
            <div class="modal-header-depurar">
                <h2>🗑️ Depurar Historial</h2>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModalDepurar()">✕</button>
            </div>
            <div class="modal-body-depurar">
                <div class="alerta-warning" style="background: #d4edda; border-left-color: #28a745;">
                    ✅ <strong>Seguro:</strong> Esta acción SOLO eliminará el historial visual.
                    <ul>
                        <li>✅ Los tickets reales PERMANECEN en la base de datos</li>
                        <li>✅ El inventario NO se afecta (productos y lotes)</li>
                        <li>✅ Se generará un PDF de respaldo antes de eliminar</li>
                    </ul>
                </div>
                <div class="form-group-depurar">
                    <label>📅 Fecha Inicial</label>
                    <input type="date" id="fechaInicioDepurar">
                </div>
                <div class="form-group-depurar">
                    <label>📅 Fecha Final</label>
                    <input type="date" id="fechaFinDepurar">
                </div>
                <div class="modal-footer-depurar">
                    <button type="button" onclick="cerrarModalDepurar()" class="btn-cancelar-depurar">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                    <button type="button" onclick="confirmarDepuracion()" class="btn-confirmar-depurar">
                        <i class="bi bi-trash3"></i> Depurar y Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function abrirModalDepurar() {
            document.getElementById('modalDepurar').style.display = 'flex';
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('fechaFinDepurar').value = hoy;
            document.getElementById('fechaInicioDepurar').value = hoy;
        }

        function cerrarModalDepurar() {
            document.getElementById('modalDepurar').style.display = 'none';
        }

        function confirmarDepuracion() {
            const fInicio = document.getElementById('fechaInicioDepurar').value;
            const fFin = document.getElementById('fechaFinDepurar').value;

            if (!fInicio || !fFin) {
                alert('Selecciona ambas fechas');
                return;
            }

            if (confirm('⚠️ ¿Seguro? Se descargará el PDF y se eliminará SOLO el historial visual.\n\n✅ Los tickets reales NO se eliminan.\n✅ El inventario NO se afecta.')) {
                // 🔥 PASO 1: Descargar PDF primero
                window.open(`generar_pdf_compras.php?fecha_inicio=${fInicio}&fecha_fin=${fFin}`, '_blank');

                // 🔥 PASO 2: Esperar 2 segundos y luego depurar historial
                setTimeout(() => {
                    fetch('depurar_historial.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            fecha_inicio: fInicio,
                            fecha_fin: fFin
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('✅ ' + data.message);
                                cerrarModalDepurar();
                                window.location.reload();
                            } else {
                                alert('❌ Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('❌ Error de conexión. Revisa la consola.');
                        });
                }, 2000);

                cerrarModalDepurar();
            }
        }
    </script>
</body>

</html>