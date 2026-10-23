<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
include("registrar_actividad.php");

$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';

/* ============================================
🔥 VALIDAR CONTRASEÑA
============================================ */
function validarContrasena($password)
{
    $errores = [];
    if (strlen($password) < 6)
        $errores[] = "Mínimo 6 caracteres";
    if (!preg_match('/[A-Z]/', $password))
        $errores[] = "Al menos 1 mayúscula";
    if (!preg_match('/[0-9]/', $password))
        $errores[] = "Al menos 1 número";
    return $errores;
}

/* ============================================
🔥 CREAR/ACTUALIZAR USUARIO
============================================ */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $modo = $_POST['modo'] ?? 'crear';
    $username_new = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $rol = $_POST['rol'] ?? 'empleado';
    $estado = $_POST['estado'] ?? 'activo';

    if (empty($username_new)) {
        header("Location: CRUD-Usuarios.php?error=1");
        exit;
    } elseif (strlen($username_new) < 3) {
        header("Location: CRUD-Usuarios.php?error=2");
        exit;
    } else {
        if ($modo === 'crear') {
            $check = mysqli_query($conn, "SELECT id FROM usuarios WHERE username = '" . mysqli_real_escape_string($conn, $username_new) . "'");
            if (mysqli_num_rows($check) > 0) {
                header("Location: CRUD-Usuarios.php?error=3");
                exit;
            } elseif (empty($password)) {
                header("Location: CRUD-Usuarios.php?error=4");
                exit;
            } elseif ($password !== $password_confirm) {
                header("Location: CRUD-Usuarios.php?error=5");
                exit;
            } else {
                $errores_password = validarContrasena($password);
                if (!empty($errores_password)) {
                    header("Location: CRUD-Usuarios.php?error=6");
                    exit;
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO usuarios (username, password, rol, estado) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssss", $username_new, $password_hash, $rol, $estado);
                    if (mysqli_stmt_execute($stmt)) {
                        $new_id = mysqli_insert_id($conn);
                        registrar_actividad($conn, $user_id, $username, "CREATE", "Usuario creado: $username_new", "usuarios", $new_id, [], $_POST);
                        header("Location: CRUD-Usuarios.php?creado=1");
                        exit;
                    }
                }
            }
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));
            $check = mysqli_query($conn, "SELECT id FROM usuarios WHERE username = '" . mysqli_real_escape_string($conn, $username_new) . "' AND id != $id");
            if (mysqli_num_rows($check) > 0) {
                header("Location: CRUD-Usuarios.php?error=3");
                exit;
            } else {
                if (!empty($password)) {
                    $errores_password = validarContrasena($password);
                    if (!empty($errores_password) || $password !== $password_confirm) {
                        header("Location: CRUD-Usuarios.php?error=6");
                        exit;
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE usuarios SET username = ?, password = ?, rol = ?, estado = ? WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ssssi", $username_new, $password_hash, $rol, $estado, $id);
                    }
                } else {
                    $sql = "UPDATE usuarios SET username = ?, rol = ?, estado = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "sssi", $username_new, $rol, $estado, $id);
                }
                if (isset($stmt) && mysqli_stmt_execute($stmt)) {
                    registrar_actividad($conn, $user_id, $username, "UPDATE", "Usuario actualizado: $username_new", "usuarios", $id, $datos_antiguos, $_POST);
                    header("Location: CRUD-Usuarios.php?actualizado=1");
                    exit;
                }
            }
        }
    }
}

/* ============================================
🔥 ELIMINAR / TOGGLE ESTADO
============================================ */
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    $admin_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'admin' AND estado = 'activo'"))['total'];
    $user_to_delete = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));

    if ($user_to_delete['rol'] === 'admin' && $admin_count <= 1) {
        header("Location: CRUD-Usuarios.php?error=7");
        exit;
    } elseif ($id === $user_id) {
        header("Location: CRUD-Usuarios.php?error=8");
        exit;
    } else {
        $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));
        if (mysqli_query($conn, "DELETE FROM usuarios WHERE id = $id")) {
            registrar_actividad($conn, $user_id, $username, "DELETE", "Usuario eliminado: " . $datos_antiguos['username'], "usuarios", $id, $datos_antiguos, []);
            header("Location: CRUD-Usuarios.php?eliminado=1");
            exit;
        }
    }
}

if (isset($_GET['toggle_estado'])) {
    $id = (int) $_GET['toggle_estado'];
    if ($id !== $user_id) {
        $usuario = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));
        $nuevo_estado = $usuario['estado'] === 'activo' ? 'inactivo' : 'activo';
        if (mysqli_query($conn, "UPDATE usuarios SET estado = '$nuevo_estado' WHERE id = $id")) {
            header("Location: CRUD-Usuarios.php?actualizado=1");
            exit;
        }
    }
}

/* ============================================
🔥 OBTENER USUARIOS
============================================ */
$usuarios = mysqli_query($conn, "SELECT * FROM usuarios ORDER BY creado_en DESC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>• Usuarios </title>
    <!-- ✅ TU CSS CORRECTO -->
    <link rel="stylesheet" href="../../css/Usuarios-STYLE/t.css">
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
    <form class="form-productos" method="POST" autocomplete="off" id="formUsuario" required>
        <div class="form-grid">
            <input type="hidden" name="modo" id="modo" value="crear">
            <input type="hidden" name="id" id="id_usuario" value="">

            <!-- 🔥 CAMPOS FALSOS PARA ENGañAR AL NAVEGADOR -->
            <input type="text" style="display:none">
            <input type="password" style="display:none">
            <!-- ------------------------------------------- -->

            <div class="form-group col-3">
                <label>Nombre de Usuario *</label>
                <input type="text" name="username" id="username" required minlength="3" placeholder="Ej. JuanPerez">
            </div>

            <div class="form-group col-2">
                <label>Rol *</label>
                <select name="rol" id="rol" required>
                    <option value="admin">👑 Administrador</option>
                    <option value="empleado">👷 Empleado</option>
                </select>
            </div>

            <div class="form-group col-1">
                <label>Estado *</label>
                <select name="estado" id="estado" required>
                    <option value="activo">✅ Activo</option>
                    <option value="inactivo">⛔ Inactivo</option>
                </select>
            </div>

            <div class="form-group col-2">
                <label>Contraseña <span id="labelPass" style="font-size:12px; color:#666;">*</span></label>

                <input type="password" name="password" id="password" placeholder="Mínimo 6 caracteres"
                    oninput="validarFortalezaPassword()" autocomplete="off">
                <div id="passwordStrength" style="font-size:11px; margin-top:4px;"></div>
                <!-- AGREGA ESTO DEBAJO DEL INPUT DE CONTRASEÑA -->
                <div style="font-size: 11px; color: #666; margin-top: 5px;">
                    🔒 Requisitos: Mínimo 6 caracteres, letras y números.
                </div>
            </div>

            <div class="form-group col-1">
                <label>Confirmar <span id="labelPassConf" style="font-size:12px; color:#666;">*</span></label>
                <input type="password" name="password_confirm" id="password_confirm" placeholder="Repetir"
                    oninput="validarCoincidenciaPassword()" autocomplete="off">
                <div id="passwordMatch" style="font-size:11px; margin-top:4px;"></div>
            </div>

            <!-- BOTONES ALINEADOS A LA DERECHA -->
            <div style="grid-column: span 3; display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button type="submit" class="btn-guardar" id="btnGuardar" style="margin:0; width: 150px;">💾
                    Crear</button>

                <a href="CRUD-Usuarios.php" id="btnCancelar" style="display:none; background:#dc3545; color:white; padding:0 20px; 
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
        <h3 style="margin-top:30px; position: fixed;">👥 Usuarios Registrados</h3>

        <!-- BUSCADOR -->
        <div class="buscador-productos" style="margin-top:70px;">
            <input type="text" id="buscador" placeholder="🔍 Buscar usuario..." autocomplete="off">
        </div>

        <!-- TABLA CON SCROLL -->
        <div class="tabla-container" style="margin-top:112px;">
            <div class="tabla-productos">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#333; color:#fff;">
                            <th style="padding:12px 8px;">ID</th>
                            <th style="padding:12px 8px;">Usuario</th>
                            <th style="padding:12px 8px;">Rol</th>
                            <th style="padding:12px 8px;">Estado</th>
                            <th style="padding:12px 8px;">Último Acceso</th>
                            <th style="padding:12px 8px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = mysqli_fetch_assoc($usuarios)): ?>
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #ddd;"><?= $user['id'] ?></td>
                                <td style="padding:8px; border-bottom:1px solid #ddd; font-weight:bold;">
                                    <?= htmlspecialchars($user['username']) ?>
                                </td>
                                <td style="padding:8px; border-bottom:1px solid #ddd;">
                                    <?= $user['rol'] === 'admin' ? '👑 Admin' : '👷 Empleado' ?>
                                </td>
                                <td style="padding:8px; border-bottom:1px solid #ddd;">
                                    <span class="estado estado-<?= $user['estado']; ?>">
                                        <?= $user['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td style="padding:8px; border-bottom:1px solid #ddd; font-size:12px;">
                                    <?= $user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'Nunca' ?>
                                </td>
                                <td class="acciones" style="padding:8px; border-bottom:1px solid #ddd; text-align:center;">
                                    <!-- EDITAR -->
                                    <i class="bi bi-pencil-square btn-editar" data-id="<?= $user['id'] ?>"
                                        data-username="<?= htmlspecialchars($user['username']) ?>"
                                        data-rol="<?= $user['rol'] ?>" data-estado="<?= $user['estado'] ?>"
                                        title="Editar Usuario"
                                        style="font-size:18px; margin:0 5px; cursor:pointer; color:#0d6efd; transition:0.2s;">
                                    </i>
                                    <!-- TOGGLE ESTADO -->
                                    <i class="bi bi-shield-lock btn-toggle" onclick="toggleEstado('<?= $user['id'] ?>')"
                                        title="<?= $user['estado'] === 'activo' ? 'Inactivar' : 'Activar' ?>"
                                        style="font-size:18px; margin:0 5px; cursor:pointer; color:#17a2b8; transition:0.2s;">
                                    </i>
                                    <!-- ELIMINAR -->
                                    <?php if ($user['id'] !== $user_id): ?>
                                        <i class="bi bi-trash btn-eliminar"
                                            onclick="eliminarUsuario('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>')"
                                            title="Eliminar Usuario"
                                            style="font-size:18px; margin:0 5px; cursor:pointer; color:#dc3545; transition:0.2s;">
                                        </i>
                                    <?php else: ?>
                                        <i class="bi bi-slash-circle" title="No puedes eliminarte"
                                            style="font-size:18px; margin:0 5px; color:#ccc;"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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
                document.getElementById("id_usuario").value = this.dataset.id;
                document.getElementById("username").value = this.dataset.username;
                document.getElementById("rol").value = this.dataset.rol;
                document.getElementById("estado").value = this.dataset.estado;

                // Limpiar campos de contraseña al editar
                document.getElementById("password").value = "";
                document.getElementById("password_confirm").value = "";
                document.getElementById("labelPass").innerText = "(dejar vacío para no cambiar)";
                document.getElementById("labelPassConf").innerText = "(opcional)";
                document.getElementById("password").removeAttribute("required");
                document.getElementById("password_confirm").removeAttribute("required");

                // Cambiar botón a Actualizar
                document.getElementById("modo").value = "editar";
                document.getElementById("btnGuardar").innerText = "✏️ Actualizar";
                document.getElementById("btnGuardar").style.background = "#ffc107";
                document.getElementById("btnGuardar").style.color = "#000";
                document.getElementById("btnCancelar").style.display = "block";

                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        });

        // 2. CANCELAR EDICIÓN
        document.getElementById("btnCancelar").addEventListener("click", function (e) {
            e.preventDefault();
            document.querySelector("form").reset();
            document.getElementById("modo").value = "crear";
            document.getElementById("btnGuardar").innerText = "💾 Crear";
            document.getElementById("btnGuardar").style.background = "#28a745";
            document.getElementById("btnGuardar").style.color = "#fff";

            // Restaurar requerimientos de contraseña
            document.getElementById("labelPass").innerText = "*";
            document.getElementById("labelPassConf").innerText = "*";
            document.getElementById("password").setAttribute("required", "true");
            document.getElementById("password_confirm").setAttribute("required", "true");

            this.style.display = "none";
        });

        // 3. ELIMINAR USUARIO
        function eliminarUsuario(id, username) {
            if (confirm("⚠️ ¿Seguro que deseas eliminar al usuario '" + username + "'?")) {
                window.location.href = "?eliminar=" + id;
            }
        }

        // 4. CAMBIAR ESTADO (ACTIVO/INACTIVO)
        function toggleEstado(id) {
            if (confirm("¿Cambiar estado del usuario?")) {
                window.location.href = "?toggle_estado=" + id;
            }
        }

        // 5. BUSCADOR EN TIEMPO REAL
        document.getElementById('buscador').addEventListener('keyup', function () {
            const filtro = this.value.toLowerCase();
            const filas = document.querySelectorAll("table tbody tr");
            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(filtro) ? "" : "none";
            });
        });

        // 6. VALIDACIONES DE CONTRASEÑA (Visuales)
        function validarFortalezaPassword() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            if (password.length === 0) { strengthDiv.textContent = ''; return; }
            let strength = 0;
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;

            if (strength === 1) { strengthDiv.textContent = '🔴 Débil'; strengthDiv.style.color = '#dc3545'; }
            else if (strength === 2) { strengthDiv.textContent = '🟡 Media'; strengthDiv.style.color = '#ffc107'; }
            else { strengthDiv.textContent = '🟢 Fuerte'; strengthDiv.style.color = '#28a745'; }
        }

        function validarCoincidenciaPassword() {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('password_confirm').value;
            const matchDiv = document.getElementById('passwordMatch');
            if (p2.length === 0) { matchDiv.textContent = ''; return; }
            if (p1 === p2) { matchDiv.textContent = '✅ Coinciden'; matchDiv.style.color = '#28a745'; }
            else { matchDiv.textContent = '❌ No coinciden'; matchDiv.style.color = '#dc3545'; }
        }

        // 7. NOTIFICACIONES TOAST
        window.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            let mensaje = '';
            let tipo = '';

            if (urlParams.get('creado') === '1') { mensaje = '✅ Usuario creado correctamente'; tipo = 'success'; }
            else if (urlParams.get('actualizado') === '1') { mensaje = '✅ Usuario actualizado correctamente'; tipo = 'success'; }
            else if (urlParams.get('eliminado') === '1') { mensaje = '🗑️ Usuario eliminado correctamente'; tipo = 'danger'; }
            else if (urlParams.get('error')) { mensaje = '❌ Ocurrió un error'; tipo = 'danger'; }

            if (mensaje) {
                window.history.replaceState({}, document.title, window.location.pathname);
                mostrarNotificacion(mensaje, tipo);
            }
        });

        function mostrarNotificacion(mensaje, tipo) {
            const notificacion = document.createElement('div');
            const color = tipo === 'danger' ? '#dc3545' : '#28a745';
            notificacion.style.cssText = `
        position: fixed; top: 80px; right: 20px; background: ${color}; color: white;
        padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000; animation: slideIn 0.3s ease; font-weight: bold;
    `;
            notificacion.textContent = mensaje;
            document.body.appendChild(notificacion);
            setTimeout(() => {
                notificacion.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }, 3000);
        }

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