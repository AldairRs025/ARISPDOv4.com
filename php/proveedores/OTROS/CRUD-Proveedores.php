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
$mensaje = '';

// 🔥 CREAR/ACTUALIZAR PROVEEDOR
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $modo = $_POST['modo'] ?? 'crear';
    $nombre = $_POST['nombre'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $direccion = $_POST['direccion'] ?? '';

    if ($modo === 'crear') {
        $sql = "INSERT INTO proveedores (nombre, contacto, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", $nombre, $contacto, $telefono, $email, $direccion);
        if (mysqli_stmt_execute($stmt)) {
            registrar_actividad($conn, $user_id, $username, "CREATE", "Proveedor creado: $nombre", "proveedores", mysqli_insert_id($conn), [], $_POST);
            $mensaje = '✅ Proveedor creado correctamente';
        }
    } else {
        $id = $_POST['id'] ?? 0;
        $sql = "UPDATE proveedores SET nombre=?, contacto=?, telefono=?, email=?, direccion=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $contacto, $telefono, $email, $direccion, $id);
        if (mysqli_stmt_execute($stmt)) {
            registrar_actividad($conn, $user_id, $username, "UPDATE", "Proveedor actualizado: $nombre", "proveedores", $id, [], $_POST);
            $mensaje = '✅ Proveedor actualizado correctamente';
        }
    }
}

// 🔥 ELIMINAR PROVEEDOR
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM proveedores WHERE id = $id"));
    if (mysqli_query($conn, "DELETE FROM proveedores WHERE id = $id")) {
        registrar_actividad($conn, $user_id, $username, "DELETE", "Proveedor eliminado: " . $datos_antiguos['nombre'], "proveedores", $id, $datos_antiguos, []);
        $mensaje = '🗑️ Proveedor eliminado correctamente';
    }
}

// 🔥 OBTENER PROVEEDORES
$proveedores = mysqli_query($conn, "SELECT * FROM proveedores ORDER BY nombre ASC");
$proveedor_editar = null;
if (isset($_GET['editar'])) {
    $id = (int) $_GET['editar'];
    $proveedor_editar = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM proveedores WHERE id = $id"));
}
?>

<?php if ($proveedor_editar): ?>
<script>
    // Hacer scroll suave hacia el formulario al editar
    window.onload = function() {
        document.querySelector('.form-grid').scrollIntoView({ 
            behavior: 'smooth' 
        });
        // Cambiar texto del botón
        document.querySelector('.btn-guardar').innerText = '✏️ Actualizar Proveedor';
        document.querySelector('.btn-guardar').style.background = '#ffc107'; // Color amarillo para editar
    };
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚚 Proveedores - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Proveedores-STYLE/a.css">
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
        <h1>🚚 Administrar Proveedores</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <input type="hidden" name="modo" value="<?= $proveedor_editar ? 'editar' : 'crear' ?>">
            <?php if ($proveedor_editar): ?>
                <input type="hidden" name="id" value="<?= $proveedor_editar['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($proveedor_editar['nombre'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Contacto</label>
                <input type="text" name="contacto" value="<?= htmlspecialchars($proveedor_editar['contacto'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?= htmlspecialchars($proveedor_editar['telefono'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($proveedor_editar['email'] ?? '') ?>">
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label>Dirección</label>
                <input type="text" name="direccion"
                    value="<?= htmlspecialchars($proveedor_editar['direccion'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-guardar">
                <?= $proveedor_editar ? '✏️ Actualizar' : '💾 Crear' ?> Proveedor
            </button>

            <?php if ($proveedor_editar): ?>
                <a href="CRUD-Proveedores.php" style="grid-column: span 2; text-align: center; margin-top: 10px;">❌ Cancelar
                    Edición</a>
            <?php endif; ?>
        </form>

        <!-- AGREGAR ESTO ANTES DE LA TABLA -->
        <div class="buscador-container" style="margin: 20px 0;">
            <input type="text" id="buscadorProveedores"
                placeholder="🔍 Buscar proveedor por nombre, contacto o teléfono..."
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;">
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Dirección</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($prov = mysqli_fetch_assoc($proveedores)): ?>
                    <tr>
                        <td>
                            <?= $prov['id'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($prov['contacto']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($prov['telefono']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($prov['email']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($prov['direccion']) ?>
                        </td>
                        <td class="acciones">
                            <a href="?editar=<?= $prov['id'] ?>" class="btn-editar">✏️</a>
                            <a href="?eliminar=<?= $prov['id'] ?>" class="btn-eliminar"
                                onclick="return confirm('¿Eliminar proveedor?')">🗑️</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- BUSCADOR EN TIEMPO REAL PARA PROVEEDORES -->
    <script>
        document.getElementById('buscadorProveedores').addEventListener('keyup', function () {
            const filtro = this.value.toLowerCase();
            const filas = document.querySelectorAll(".tabla tbody tr"); // Asegúrate que tu tabla tenga clase "tabla" o ajusta el selector

            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                // Si coincide, mostrar, si no, ocultar
                fila.style.display = textoFila.includes(filtro) ? "" : "none";
            });
        });
    </script>
</body>

</html>