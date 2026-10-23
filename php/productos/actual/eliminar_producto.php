<?php
session_start(); // 🔥 NUEVO: Iniciar sesión para obtener user_id
include("../conexion.php");
include("../login/registrar_actividad.php"); // 🔥 NUEVO: Incluir función de registro

if (isset($_GET['id'])) {
    $user_id = $_SESSION["user_id"] ?? 0; // 🔥 NUEVO: Obtener user_id
    $username = $_SESSION["username"] ?? 'Desconocido'; // 🔥 NUEVO: Obtener username
    
    $codigo = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 🔥 OBTENER DATOS DEL PRODUCTO ANTES DE ELIMINAR (Para audit log)
    $datos_antiguos_query = mysqli_query($conn, "SELECT * FROM productos WHERE codigo_barra = '$codigo'");
    $datos_antiguos = mysqli_fetch_assoc($datos_antiguos_query);
    
    if ($datos_antiguos) {
        // 🔥 ELIMINAR LOTES PRIMERO (clave foránea)
        mysqli_query($conn, "DELETE FROM lotes WHERE codigo_barra = '$codigo'");
        
        // 🔥 ELIMINAR IMAGEN SI EXISTE
        if (!empty($datos_antiguos['imagen'])) {
            $ruta_imagen = "../../img/PRODUCTOS/" . $datos_antiguos['imagen'];
            if (file_exists($ruta_imagen)) {
                unlink($ruta_imagen);
            }
        }
        
        // 🔥 ELIMINAR PRODUCTO
        $sql = "DELETE FROM productos WHERE codigo_barra = '$codigo'";
        if (mysqli_query($conn, $sql)) {
            // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE ELIMINACIÓN
            registrar_actividad(
                $conn, $user_id, $username,
                "DELETE",
                "Producto eliminado: " . $datos_antiguos['nombre_producto'] . " (Código: $codigo)",
                "productos",
                $codigo,
                $datos_antiguos,
                []
            );
            
            header("Location: CRUD-Product.php?eliminado=1");
            exit();
        } else {
            echo "<script>
            alert('❌ Error al eliminar: " . mysqli_error($conn) . "');
            window.history.back();
            </script>";
        }
    } else {
        echo "<script>
        alert('❌ Producto no encontrado');
        window.location.href='CRUD-Product.php';
        </script>";
    }
} else {
    header("Location: CRUD-Product.php");
    exit();
}
?>