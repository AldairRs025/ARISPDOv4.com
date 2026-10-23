<?php
session_start();
$host = "localhost";
$dbname = "losnietos";
$user = "root";
$pass = "";

// 🔥 INCLUIR FUNCIÓN DE REGISTRO
include("registrar_actividad.php");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $username = trim($_POST["username"]);
        $password = trim($_POST["password"]);

        $stmt = $pdo->prepare("SELECT id, username, password, rol, estado FROM usuarios WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            // 🔥 VALIDAR ESTADO DEL USUARIO
            if ($user["estado"] !== "activo") {
                echo "<p style='color:red; text-align:center;'>❌ Usuario inactivo. Contacta al administrador.</p>";
                exit;
            }

            // Guardar datos en sesión
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["rol"] = $user["rol"];

            // 🔥 ACTUALIZAR ÚLTIMO ACCESO
            include("../conexion.php");
            mysqli_query($conn, "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = " . $user["id"]);

            // 🔥 REGISTRAR ACTIVIDAD DE LOGIN
            registrar_actividad(
                $conn,
                $user["id"],
                $user["username"],
                "LOGIN",
                "Inicio de sesión exitoso",
                "usuarios",
                $user["id"]
            );

            // Redirigir según rol
            if ($user["rol"] === "admin") {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: dashboard_empleado.php");
            }
            exit;
        } else {
            // 🔥 REGISTRAR INTENTO FALLIDO
            include("../conexion.php");
            registrar_actividad(
                $conn,
                0,
                $username,
                "LOGIN_FALLIDO",
                "Intento de inicio de sesión fallido",
                "usuarios",
                ''
            );
            echo "<p style='color:red; text-align:center;'>Usuario o contraseña incorrectos.</p>";
        }
    }
} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
?>