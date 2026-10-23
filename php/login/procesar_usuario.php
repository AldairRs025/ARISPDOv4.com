<?php
session_start();
include("../conexion.php");
include("registrar_actividad.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}

$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // 🔥 CREAR USUARIO
    if ($accion === 'crear') {
        $username_new = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? 'empleado';

        if (empty($username_new) || empty($password)) {
            header("Location: CRUD-Usuarios.php?error=campos_vacios");
            exit;
        }

        // Verificar si existe
        $check = mysqli_query($conn, "SELECT id FROM usuarios WHERE username = '" . mysqli_real_escape_string($conn, $username_new) . "'");
        if (mysqli_num_rows($check) > 0) {
            header("Location: CRUD-Usuarios.php?error=usuario_existe");
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (username, password, rol) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $username_new, $password_hash, $rol);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);

            registrar_actividad(
                $conn,
                $user_id,
                $username,
                "CREATE",
                "Usuario creado: $username_new (Rol: $rol)",
                "usuarios",
                $new_id,
                [],
                ['username' => $username_new, 'rol' => $rol]
            );

            header("Location: CRUD-Usuarios.php?creado=1");
        } else {
            header("Location: CRUD-Usuarios.php?error=crear");
        }
        exit;
    }

    // 🔥 ACTUALIZAR USUARIO
    if ($accion === 'actualizar') {
        $id = (int) ($_POST['id'] ?? 0);
        $username_new = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? 'empleado';

        $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));

        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET username = ?, password = ?, rol = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $username_new, $password_hash, $rol, $id);
        } else {
            $sql = "UPDATE usuarios SET username = ?, rol = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $username_new, $rol, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            registrar_actividad(
                $conn,
                $user_id,
                $username,
                "UPDATE",
                "Usuario actualizado: $username_new (Rol: $rol)",
                "usuarios",
                $id,
                $datos_antiguos,
                ['username' => $username_new, 'rol' => $rol]
            );

            header("Location: CRUD-Usuarios.php?actualizado=1");
        } else {
            header("Location: CRUD-Usuarios.php?error=actualizar");
        }
        exit;
    }

    // 🔥 ELIMINAR USUARIO
    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $user_id) {
            header("Location: CRUD-Usuarios.php?error=no_puedes_eliminarte");
            exit;
        }

        $datos_antiguos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM usuarios WHERE id = $id"));

        if (mysqli_query($conn, "DELETE FROM usuarios WHERE id = $id")) {
            registrar_actividad(
                $conn,
                $user_id,
                $username,
                "DELETE",
                "Usuario eliminado: " . $datos_antiguos['username'],
                "usuarios",
                $id,
                $datos_antiguos,
                []
            );

            header("Location: CRUD-Usuarios.php?eliminado=1");
        } else {
            header("Location: CRUD-Usuarios.php?error=eliminar");
        }
        exit;
    }
}

header("Location: CRUD-Usuarios.php");
exit;
?>