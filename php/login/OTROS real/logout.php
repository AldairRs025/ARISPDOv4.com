<?php
session_start();

// 🔥 REGISTRAR ACTIVIDAD DE LOGOUT ANTES DE DESTRUIR SESIÓN
if (isset($_SESSION["user_id"]) && isset($_SESSION["username"])) {
    include("../conexion.php");
    include("registrar_actividad.php");
    
    registrar_actividad(
        $conn, 
        $_SESSION["user_id"], 
        $_SESSION["username"], 
        "LOGOUT", 
        "Cierre de sesión", 
        "usuarios", 
        $_SESSION["user_id"]
    );
}

session_unset();
session_destroy();
header("Location: ../../index.html");
exit;
?>