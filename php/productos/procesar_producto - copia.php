<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php"); // 🔥 NUEVO: Incluir función de registro

// 🔥 VALIDAR QUE EL USUARIO ESTÉ LOGUEADO
if (!isset($_SESSION["user_id"]) || $_SESSION["user_id"] <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => '❌ Sesión expirada. Por favor inicia sesión nuevamente.'
    ]);
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"] ?? 'Desconocido';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"] ?? 0;
    $username = $_SESSION["username"] ?? 'Desconocido';
    
    $codigo = $_POST['codigo_barra'];
    $nombre = $_POST['nombre_producto'];
    $marca = $_POST['marca'];
    $categoria = $_POST['categoria'];
    $cantidad = $_POST['cantidad'];
    // $precio_compra = $_POST['precio_compra']; // ❌ NO USAR
    $precio_venta = $_POST['precio_venta'];
    $estado = $_POST['estado'];
    $stock = $_POST['stock_minimo'];
    $modo = $_POST['modo'];
    $proveedor = isset($_POST['proveedor']) && !empty($_POST['proveedor']) ? $_POST['proveedor'] : NULL;
    
    // 🔥 MANEJO DE IMAGEN
    $imagen = NULL;
    $imagen_nueva = false;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $directorio_destino = "../../img/PRODUCTOS/";
        if (!file_exists($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }
        $nombre_archivo = basename($_FILES['imagen']['name']);
        $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
        $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
        $ruta_destino = $directorio_destino . $nombre_unico;
        $tipos_permitidos = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        if (in_array($extension, $tipos_permitidos)) {
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                $imagen = $nombre_unico;
                $imagen_nueva = true;
            }
        }
    }
    
    // 🔥 Si es modo editar y no hay nueva imagen, mantener la existente
    if ($modo === "editar" && empty($imagen)) {
        $sql_verificar = "SELECT imagen FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "'";
        $resultado = mysqli_query($conn, $sql_verificar);
        if ($fila = mysqli_fetch_assoc($resultado)) {
            $imagen = $fila['imagen'];
        }
    }
    
    // ❌ NO CALCULAR total_compra - Se gestiona desde lotes
    // $total_compra = $cantidad * $precio_compra;
    $total_venta = $cantidad * $precio_venta;
    
    // 🔥 VALIDAR QUE PRECIO VENTA SEA MAYOR
    if ($precio_venta <= 0) {
        echo "<script>
        alert('⚠ El precio de venta debe ser mayor a 0.');
        window.history.back();
        </script>";
        exit();
    }
    
    /* ==============================
    🔹 MODO EDITAR (UPDATE)
    ============================== */
    if ($modo === "editar") {
        $codigo_original = isset($_POST['codigo_original']) ? $_POST['codigo_original'] : $codigo;
        
        // 🔥 OBTENER DATOS ANTIGUOS ANTES DE ACTUALIZAR (Para audit log)
        $datos_antiguos_query = mysqli_query($conn, "SELECT * FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_original) . "'");
        $datos_antiguos = mysqli_fetch_assoc($datos_antiguos_query);
        
        // 🔥 VERIFICAR SI EL NUEVO CÓDIGO YA EXISTE EN OTRO PRODUCTO
        if ($codigo !== $codigo_original) {
            $verificar = mysqli_query($conn, "SELECT codigo_barra FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "'");
            if (mysqli_num_rows($verificar) > 0) {
                echo "<script>
                alert('❌ El código de barras ya existe');
                window.history.back();
                </script>";
                exit();
            }
        }
        
        // 🔥 OBTENER IMAGEN ANTES DE ACTUALIZAR
        $imagen_anterior = NULL;
        if ($imagen_nueva) {
            $sql_imagen_anterior = "SELECT imagen FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_original) . "'";
            $resultado_anterior = mysqli_query($conn, $sql_imagen_anterior);
            if ($fila_anterior = mysqli_fetch_assoc($resultado_anterior)) {
                $imagen_anterior = $fila_anterior['imagen'];
            }
        }
        
        // 🔥 ACTUALIZAR PRODUCTO - SIN modificar precio_compra ni total_compra
        $sql = "UPDATE productos SET
        codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "',
        nombre_producto = '" . mysqli_real_escape_string($conn, $nombre) . "',
        marca = '" . mysqli_real_escape_string($conn, $marca) . "',
        categoria = '" . mysqli_real_escape_string($conn, $categoria) . "',
        cantidad = '$cantidad',
        -- precio_compra se mantiene igual (se gestiona desde lotes)
        -- total_compra se mantiene igual (se gestiona desde lotes)
        precio_venta = '$precio_venta',
        total_venta = '$total_venta',
        estado = '$estado',
        stock_minimo = '$stock',
        proveedor = " . ($proveedor === NULL ? "NULL" : "'" . mysqli_real_escape_string($conn, $proveedor) . "'") . ",
        imagen = " . ($imagen === NULL ? "NULL" : "'" . mysqli_real_escape_string($conn, $imagen) . "'") . "
        WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_original) . "'";
        
        if (mysqli_query($conn, $sql)) {
            // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE ACTUALIZACIÓN
            registrar_actividad(
                $conn, $user_id, $username,
                "UPDATE",
                "Producto actualizado: $nombre (Código: $codigo_original)",
                "productos",
                $codigo_original,
                $datos_antiguos,
                $_POST
            );
            
            // 🔥 ELIMINAR IMAGEN ANTERIOR SI HAY NUEVA IMAGEN
            if ($imagen_nueva && !empty($imagen_anterior)) {
                $ruta_imagen_anterior = "../../img/PRODUCTOS/" . $imagen_anterior;
                if (file_exists($ruta_imagen_anterior)) {
                    unlink($ruta_imagen_anterior);
                }
            }
            
            // 🔥 SI SE CAMBIÓ EL CÓDIGO, ACTUALIZAR LOTES
            if ($codigo !== $codigo_original) {
                $sql_update_lotes = "UPDATE lotes SET codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "' WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_original) . "'";
                mysqli_query($conn, $sql_update_lotes);
            }
            
            header("Location: CRUD-Product.php?actualizado=1");
            exit();
        } else {
            echo "<script>
            alert('❌ Error al actualizar: " . mysqli_error($conn) . "');
            window.history.back();
            </script>";
            exit();
        }
    }
    
    /* ==============================
    🔹 MODO CREAR (INSERT)
    ============================== */
    else {
        // 🔥 Validar si ya existe el código
        $verificar = mysqli_query($conn, "SELECT codigo_barra FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "'");
        if (mysqli_num_rows($verificar) > 0) {
            echo "<script>
            alert('❌ El código de barras ya existe');
            window.location.href='CRUD-Product.php';
            </script>";
            exit();
        }
        
        // 🔥 AL CREAR, inicializar precio_compra y total_compra en 0
        $sql = "INSERT INTO productos
        (codigo_barra, nombre_producto, marca, categoria, cantidad, precio_compra, total_compra,
        precio_venta, total_venta, estado, stock_minimo, proveedor, imagen, fecha_ingreso)
        VALUES
        ('$codigo',
        '" . mysqli_real_escape_string($conn, $nombre) . "',
        '" . mysqli_real_escape_string($conn, $marca) . "',
        '" . mysqli_real_escape_string($conn, $categoria) . "',
        '$cantidad',
        0,  -- precio_compra inicial en 0
        0,  -- total_compra inicial en 0
        '$precio_venta',
        '$total_venta',
        '$estado',
        '$stock',
        " . ($proveedor === NULL ? "NULL" : "'" . mysqli_real_escape_string($conn, $proveedor) . "'") . ",
        " . ($imagen === NULL ? "NULL" : "'" . mysqli_real_escape_string($conn, $imagen) . "'") . ",
        NOW())";
        
        if (mysqli_query($conn, $sql)) {
            // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE CREACIÓN
            registrar_actividad(
                $conn, $user_id, $username,
                "CREATE",
                "Producto creado: $nombre (Código: $codigo)",
                "productos",
                $codigo,
                [],
                $_POST
            );
            
            header("Location: CRUD-Product.php?agregado=1");
            exit();
        } else {
            echo "<script>
            alert('❌ Error al crear: " . mysqli_error($conn) . "');
            window.history.back();
            </script>";
            exit();
        }
    }
}
?>