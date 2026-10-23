<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 INCLUIR PHPSpreadsheet
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// 🔥 OBTENER OPCIONES
$omitir_repetidos = isset($_POST['omitir_repetidos']);
$actualizar_existencias = isset($_POST['actualizar_existencias']);
$validar_datos = isset($_POST['validar_datos']);

// 🔥 VALIDAR ARCHIVO
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    header("Location: carga_masiva.php?error=archivo");
    exit;
}

$archivo = $_FILES['archivo_excel']['tmp_name'];
$extension = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));

// 🔥 VALIDAR EXTENSIÓN
if (!in_array($extension, ['xlsx', 'xls'])) {
    header("Location: carga_masiva.php?error=extension&mensaje=Extensión no válida. Usa .xlsx o .xls");
    exit;
}

// 🔥 CARGAR EXCEL CON PHPSpreadsheet
try {
    $spreadsheet = IOFactory::load($archivo);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
} catch (Exception $e) {
    header("Location: carga_masiva.php?error=lectura&mensaje=" . urlencode($e->getMessage()));
    exit;
}

// 🔥 ELIMINAR PRIMERA FILA (ENCABEZADOS)
array_shift($rows);

// 🔥 CONTADORES
$procesados = 0;
$insertados = 0;
$actualizados = 0;
$omitidos = 0;
$errores = [];

// 🔥 PROCESAR CADA FILA
foreach ($rows as $fila => $datos) {
    $numero_fila = $fila + 2; // Excel fila 1 = encabezados

    // Saltar filas vacías
    if (empty($datos[0]) || empty($datos[1])) {
        continue;
    }

    // 🔥 OBTENER DATOS
    $codigo_barra = trim($datos[0] ?? '');
    $nombre_producto = trim($datos[1] ?? '');
    $cantidad = floatval($datos[2] ?? 0);
    $marca = trim($datos[3] ?? '');
    $categoria = trim($datos[4] ?? '');
    $precio_compra = floatval($datos[5] ?? 0);
    $precio_venta = floatval($datos[6] ?? 0);
    $stock_minimo = intval($datos[7] ?? 0);
    $proveedor = trim($datos[8] ?? '');

    // 🔥 VALIDACIONES
    if ($validar_datos) {
        if (empty($codigo_barra)) {
            $errores[] = "Fila $numero_fila: Código de barras vacío";
            $omitidos++;
            continue;
        }

        if (empty($nombre_producto)) {
            $errores[] = "Fila $numero_fila: Nombre del producto vacío";
            $omitidos++;
            continue;
        }

        if ($cantidad < 0) {
            $errores[] = "Fila $numero_fila: Cantidad inválida";
            $omitidos++;
            continue;
        }

        if ($precio_venta < 0) {
            $errores[] = "Fila $numero_fila: Precio de venta inválido";
            $omitidos++;
            continue;
        }
    }

    $procesados++;

    // 🔥 CALCULAR GANANCIAS PARA EL LOTE
    $ganancia_unitaria = $precio_venta - $precio_compra;
    $ganancia_lote = $ganancia_unitaria * $cantidad;
    $porcentaje_ganancia = ($precio_compra > 0) ? (($ganancia_unitaria / $precio_compra) * 100) : 0;

    // 🔥 VERIFICAR SI YA EXISTE
    $check = mysqli_query($conn, "SELECT codigo_barra, precio_venta, cantidad FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_barra) . "'");

    if (mysqli_num_rows($check) > 0) {
        // 🔥 PRODUCTO YA EXISTE - ACTUALIZAR
        $producto_existente = mysqli_fetch_assoc($check);

        if ($actualizar_existencias) {
            // 🔥 USAR EL PRECIO DE VENTA EXISTENTE SI NO VIENE EN EL EXCEL
            $precio_venta_final = ($precio_venta > 0) ? $precio_venta : $producto_existente['precio_venta'];

            // 🔥 NUEVA CANTIDAD TOTAL
            $nueva_cantidad = $producto_existente['cantidad'] + $cantidad;

            // 🔥 ACTUALIZAR PRODUCTOS (precio_compra = 0, total_venta = cantidad * precio_venta)
            $sql = "UPDATE productos SET 
                    cantidad = $nueva_cantidad,
                    nombre_producto = '" . mysqli_real_escape_string($conn, $nombre_producto) . "',
                    marca = '" . mysqli_real_escape_string($conn, $marca) . "',
                    categoria = '" . mysqli_real_escape_string($conn, $categoria) . "',
                    precio_compra = 0,
                    precio_venta = $precio_venta_final,
                    stock_minimo = $stock_minimo,
                    proveedor = '" . mysqli_real_escape_string($conn, $proveedor) . "',
                    total_compra = total_compra + (" . $cantidad . " * " . $precio_compra . "),
                    total_venta = $nueva_cantidad * $precio_venta_final
                    WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo_barra) . "'";

            if (mysqli_query($conn, $sql)) {
                $actualizados++;

                // 🔥 INSERTAR EN LOTES (SIN fecha_vencimiento)
                $sql_lote = "INSERT INTO lotes (
                                codigo_barra,
                                cantidad,
                                precio_compra,
                                ganancia_lote,
                                porcentaje_ganancia,
                                proveedor,
                                compra_id,
                                fecha_ingreso,
                                es_de_compra
                            ) VALUES (
                                '" . mysqli_real_escape_string($conn, $codigo_barra) . "',
                                $cantidad,
                                $precio_compra,
                                $ganancia_lote,
                                $porcentaje_ganancia,
                                '" . mysqli_real_escape_string($conn, $proveedor) . "',
                                NULL,
                                NOW(),
                                0
                            )";
                mysqli_query($conn, $sql_lote);

            } else {
                $errores[] = "Fila $numero_fila: Error al actualizar: " . mysqli_error($conn);
            }
        } elseif ($omitir_repetidos) {
            $omitidos++;
        } else {
            $errores[] = "Fila $numero_fila: Código de barras duplicado ($codigo_barra)";
        }

    } else {
        // 🔥 INSERTAR NUEVO PRODUCTO
        $total_compra = $cantidad * $precio_compra;
        $total_venta = $cantidad * $precio_venta;

        // 🔥 CALCULAR GANANCIAS ACTUALIZADAS
        $ganancia_unitaria = $precio_venta - $precio_compra;
        $ganancia_lote = $ganancia_unitaria * $cantidad;
        $porcentaje_ganancia = ($precio_compra > 0) ? (($ganancia_unitaria / $precio_compra) * 100) : 0;

        $sql = "INSERT INTO productos (
                    codigo_barra, 
                    nombre_producto, 
                    cantidad, 
                    marca, 
                    categoria,
                    precio_compra, 
                    precio_venta, 
                    stock_minimo, 
                    proveedor,
                    total_compra, 
                    total_venta, 
                    estado, 
                    fecha_ingreso
                ) VALUES (
                    '" . mysqli_real_escape_string($conn, $codigo_barra) . "',
                    '" . mysqli_real_escape_string($conn, $nombre_producto) . "',
                    $cantidad,
                    '" . mysqli_real_escape_string($conn, $marca) . "',
                    '" . mysqli_real_escape_string($conn, $categoria) . "',
                    0,
                    $precio_venta,
                    $stock_minimo,
                    '" . mysqli_real_escape_string($conn, $proveedor) . "',
                    $total_compra,
                    $total_venta,
                    'activo',
                    NOW()
                )";

        if (mysqli_query($conn, $sql)) {
            $insertados++;

            // 🔥 INSERTAR EN LOTES (SIN fecha_vencimiento)
            $sql_lote = "INSERT INTO lotes (
                            codigo_barra,
                            cantidad,
                            precio_compra,
                            ganancia_lote,
                            porcentaje_ganancia,
                            proveedor,
                            compra_id,
                            fecha_ingreso,
                            es_de_compra
                        ) VALUES (
                            '" . mysqli_real_escape_string($conn, $codigo_barra) . "',
                            $cantidad,
                            $precio_compra,
                            $ganancia_lote,
                            $porcentaje_ganancia,
                            '" . mysqli_real_escape_string($conn, $proveedor) . "',
                            NULL,
                            NOW(),
                            0
                        )";
            mysqli_query($conn, $sql_lote);

        } else {
            $errores[] = "Fila $numero_fila: Error al insertar: " . mysqli_error($conn);
        }
    }
}

// 🔥 REDIRECCIONAR CON RESULTADOS
$mensaje = urlencode("✅ Procesados: $procesados | Insertados: $insertados | Actualizados: $actualizados | Omitidos: $omitidos");
if (!empty($errores)) {
    $mensaje .= "&errores=" . urlencode(implode("<br>", $errores));
}

header("Location: carga_masiva.php?resultado=$mensaje");
exit;
?>