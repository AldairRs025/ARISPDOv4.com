<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
include("../login/registrar_actividad.php");

$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';

// 🔥 CREAR/ACTUALIZAR PROVEEDOR
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $modo = $_POST['modo'] ?? 'crear';
    $nombre = trim($_POST['nombre'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validación básica
    if (empty($nombre)) {
        header("Location: CRUD-Proveedores.php?error=1");
        exit;
    }

    if ($modo === 'crear') {
        $sql = "INSERT INTO proveedores (nombre, contacto, telefono) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $nombre, $contacto, $telefono);

        if (mysqli_stmt_execute($stmt)) {
            registrar_actividad($conn, $user_id, $username, "CREATE", "Proveedor creado: $nombre", "proveedores", mysqli_insert_id($conn), [], $_POST);
            header("Location: CRUD-Proveedores.php?creado=1");
            exit;
        } else {
            header("Location: CRUD-Proveedores.php?error=1");
            exit;
        }
    } else {
        $id = (int) $_POST['id'];
        $sql = "UPDATE proveedores SET nombre=?, contacto=?, telefono=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $nombre, $contacto, $telefono, $id);

        if (mysqli_stmt_execute($stmt)) {
            registrar_actividad($conn, $user_id, $username, "UPDATE", "Proveedor actualizado: $nombre", "proveedores", $id, [], $_POST);
            header("Location: CRUD-Proveedores.php?actualizado=1");
            exit;
        } else {
            header("Location: CRUD-Proveedores.php?error=1");
            exit;
        }
    }
}

// 🔥 ELIMINAR PROVEEDOR
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    // Obtener nombre antes de borrar para el log
    $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre FROM proveedores WHERE id = $id"));

    if (mysqli_query($conn, "DELETE FROM proveedores WHERE id = $id")) {
        registrar_actividad($conn, $user_id, $username, "DELETE", "Proveedor eliminado: " . ($datos_antiguos['nombre'] ?? 'Desconocido'), "proveedores", $id, $datos_antiguos, []);
        header("Location: CRUD-Proveedores.php?eliminado=1");
        exit;
    } else {
        header("Location: CRUD-Proveedores.php?error=1");
        exit;
    }
}

// 🔥 OBTENER PROVEEDORES (CON BÚSQUEDA)
$whereClauses = [];
if (!empty($_GET['busqueda'])) {
    $busqueda = mysqli_real_escape_string($conn, $_GET['busqueda']);
    $whereClauses[] = "(nombre LIKE '%$busqueda%' OR contacto LIKE '%$busqueda%' OR telefono LIKE '%$busqueda%')";
}

$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

$proveedores = mysqli_query($conn, "SELECT * FROM proveedores $where ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>• Proveedores</title>
    <link rel="stylesheet" href="../../css/Proveedores-STYLE/d.css">
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

    <!-- FORMULARIO (Lado Derecho Fijo) -->
    <form class="form-productos" method="POST" autocomplete="off">
        <div class="form-grid">
            <input type="hidden" name="modo" id="modo" value="crear">
            <input type="hidden" name="id" id="id_proveedor" value="">

            <div class="form-group col-3">
                <label>Nombre del Proveedor *</label>
                <input type="text" name="nombre" id="nombre" required placeholder="Ej. Coca-Cola FEMSA">
            </div>

            <div class="form-group col-2">
                <label>Persona de Contacto</label>
                <input type="text" name="contacto" id="contacto" placeholder="Ej. Juan Pérez">
            </div>

            <div class="form-group col-1">
                <label>Teléfono</label>
                <input type="text" name="telefono" id="telefono" placeholder="Ej. 5551234567" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

            <!-- BOTONES ALINEADOS A LA DERECHA -->
            <div style="grid-column: span 3; display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button type="submit" class="btn-guardar" id="btnGuardar" style="margin:0; width: 150px;">💾
                    Crear</button>

                <a href="CRUD-Proveedores.php" id="btnCancelar" style="display:none; background:#dc3545; color:white; padding:0 20px; 
                      border-radius:6px; text-decoration:none; font-weight:bold; 
                      height: 40px; font-size: 16px; line-height: 40px; 
                      text-align: center; transition: 0.2s; width: 150px;">
                    ❌ Cancelar
                </a>
            </div>
        </div>
    </form>

    <!-- CONTENEDOR IZQUIERDO (Tabla + Buscador) -->
    <div class="contenedor-tabla">
        <h3 style="margin-top:90px; position: fixed;">🚚 Proveedores Registrados</h3>

        <!-- BUSCADOR -->
        <div class="buscador-productos" style="margin-top:130px;">
            <form method="GET" action="CRUD-Proveedores.php">
                <input type="text" name="busqueda" id="buscador" placeholder="🔍 Buscar proveedor..."
                    value="<?= $_GET['busqueda'] ?? '' ?>" autocomplete="off">
                <button type="submit" style="display:none;"></button>
            </form>
        </div>

        <!-- TABLA CON SCROLL -->
        <div class="tabla-container" style="margin-top:10px;">
            <div class="tabla-productos">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#333; color:#fff;">
                            <th style="padding:12px 8px;">ID</th>
                            <th style="padding:12px 8px;">Nombre</th>
                            <th style="padding:12px 8px;">Contacto</th>
                            <th style="padding:12px 8px;">Teléfono</th>
                            <th style="padding:12px 8px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($proveedores) > 0): ?>
                            <?php while ($prov = mysqli_fetch_assoc($proveedores)): ?>
                                <tr>
                                    <td style="padding:8px; border-bottom:1px solid #ddd;"><?= $prov['id'] ?></td>
                                    <td style="padding:8px; border-bottom:1px solid #ddd; font-weight:bold;">
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </td>
                                    <td style="padding:8px; border-bottom:1px solid #ddd;">
                                        <?= htmlspecialchars($prov['contacto']) ?>
                                    </td>
                                    <td style="padding:8px; border-bottom:1px solid #ddd;">
                                        <?= htmlspecialchars($prov['telefono']) ?>
                                    </td>
                                    <td class="acciones" style="padding:8px; border-bottom:1px solid #ddd; text-align:center;">
                                        <i class="bi bi-pencil-square btn-editar" data-id="<?= $prov['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($prov['nombre']) ?>"
                                            data-contacto="<?= htmlspecialchars($prov['contacto']) ?>"
                                            data-telefono="<?= htmlspecialchars($prov['telefono']) ?>" title="Editar Proveedor"
                                            style="font-size:18px; margin:0 5px; cursor:pointer; color:#0d6efd; transition:0.2s;">
                                        </i>
                                        <i class="bi bi-trash btn-eliminar"
                                            onclick="eliminarProveedor('<?= $prov['id'] ?>', '<?= htmlspecialchars($prov['nombre']) ?>')"
                                            title="Eliminar Proveedor"
                                            style="font-size:18px; margin:0 5px; cursor:pointer; color:#dc3545; transition:0.2s;">
                                        </i>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:20px;">No hay proveedores registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- SCRIPTS DE FUNCIONALIDAD -->
    <script>
        // 1. CARGAR DATOS AL FORMULARIO (EDITAR)
        document.querySelectorAll(".btn-editar").forEach(btn => {
            btn.addEventListener("click", function () {
                document.getElementById("id_proveedor").value = this.dataset.id;
                document.getElementById("nombre").value = this.dataset.nombre;
                document.getElementById("contacto").value = this.dataset.contacto;
                document.getElementById("telefono").value = this.dataset.telefono;

                document.getElementById("modo").value = "editar";
                document.getElementById("btnGuardar").innerText = "✏️ Actualizar";
                document.getElementById("btnGuardar").style.background = "#ffc107"; // Amarillo
                document.getElementById("btnGuardar").style.color = "#000";
                document.getElementById("btnCancelar").style.display = "block"; // Mostrar botón cancelar

                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        });

        // 2. CANCELAR EDICIÓN
        document.getElementById("btnCancelar").addEventListener("click", function (e) {
            e.preventDefault();
            document.querySelector("form").reset();
            document.getElementById("modo").value = "crear";
            document.getElementById("btnGuardar").innerText = "💾 Crear";
            document.getElementById("btnGuardar").style.background = "#28a745"; // Verde
            document.getElementById("btnGuardar").style.color = "#fff";
            this.style.display = "none"; // Ocultar botón cancelar
        });

        // 3. ELIMINAR CON CONFIRMACIÓN
        function eliminarProveedor(id, nombre) {
            if (confirm("⚠️ ¿Seguro que deseas eliminar al proveedor '" + nombre + "'?")) {
                window.location.href = "?eliminar=" + id;
            }
        }

        // 4. NOTIFICACIONES TOAST (IGUAL QUE PRODUCTOS)
        window.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            let mensaje = '';
            let tipo = '';

            if (urlParams.get('creado') === '1') {
                mensaje = ' Proveedor creado correctamente';
                tipo = 'success';
            } else if (urlParams.get('actualizado') === '1') {
                mensaje = ' Proveedor actualizado correctamente';
                tipo = 'success';
            } else if (urlParams.get('eliminado') === '1') {
                mensaje = ' Proveedor eliminado correctamente';
                tipo = 'danger';
            } else if (urlParams.get('error') === '1') {
                mensaje = ' Ocurrió un error en la operación';
                tipo = 'danger';
            }

            if (mensaje) {
                // Limpiar URL
                window.history.replaceState({}, document.title, window.location.pathname);
                mostrarNotificacion(mensaje, tipo);
            }
        });

        function mostrarNotificacion(mensaje, tipo) {
            const notificacion = document.createElement('div');
            const color = tipo === 'danger' ? '#dc3545' : '#28a745';

            // Estilos idénticos a los de productos
            notificacion.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${color};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-weight: bold;
        font-family: 'Kalam', cursive;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    `;

            // Icono según el tipo
            const icono = tipo === 'danger' ? '🗑️' : '✅';
            notificacion.innerHTML = `<span>${icono}</span> <span>${mensaje}</span>`;

            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }, 3000);
        }

        // Agregar animaciones CSS dinámicamente
        const style = document.createElement('style');
        style.textContent = `
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
`;
        document.head.appendChild(style);
    </script>

    <script>
        // Evitar autocompletado agresivo del navegador
        document.addEventListener("DOMContentLoaded", function () {
            const inputs = document.querySelectorAll("#formUsuario input");

            inputs.forEach(input => {
                // Hacerlos readonly al cargar
                input.setAttribute("readonly", "readonly");

                // Quitar el readonly cuando el usuario hace clic
                input.addEventListener("click", function () {
                    this.removeAttribute("readonly");
                });

                // También quitarlo si usan la tecla Tab para navegar
                input.addEventListener("keydown", function () {
                    this.removeAttribute("readonly");
                });
            });
        });
    </script>

</body>

</html>