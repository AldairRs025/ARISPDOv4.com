<?php
include("../conexion.php");
$id_lote = (int) ($_GET['id'] ?? 0);
if ($id_lote <= 0) {
    echo '<div style="color:red; text-align:center; padding:20px;">❌ Lote no válido</div>';
    exit();
}

// 🔥 OBTENER DATOS DEL LOTE
$stmt = mysqli_prepare($conn, "SELECT * FROM lotes WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id_lote);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$lote = mysqli_fetch_assoc($result);

if (!$lote) {
    echo '<div style="color:red; text-align:center; padding:20px;">❌ Lote no encontrado</div>';
    exit();
}

// 🔥 OBTENER NOMBRE DEL PRODUCTO
$stmt2 = mysqli_prepare($conn, "SELECT nombre_producto FROM productos WHERE codigo_barra = ?");
mysqli_stmt_bind_param($stmt2, "s", $lote['codigo_barra']);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
$producto = mysqli_fetch_assoc($result2);
$nombre_producto = $producto ? htmlspecialchars($producto['nombre_producto']) : 'Producto';
?>
<div style="padding: 20px; background:#f8f9fa; border-radius:8px;">
    <h4 style="margin-top:0; color:#0d6efd; text-align:center;">
        <i class="bi bi-pencil-square"></i> Editar Lote de: <?= $nombre_producto ?>
    </h4>
    <form id="formEditarLote">
        <input type="hidden" name="id_lote" value="<?= $lote['id'] ?>">
        <input type="hidden" name="codigo_barra" value="<?= htmlspecialchars($lote['codigo_barra']) ?>">
        <input type="hidden" name="cantidad_anterior" value="<?= $lote['cantidad'] ?>">
        
        <div style="margin:15px 0;">
            <label style="display:block; margin-bottom:8px; font-weight:bold; color:#333; font-style: italic;">
                🏪 Proveedor:
            </label>
            <input type="text" name="proveedor"
                   style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:14px;"
                   value="<?= htmlspecialchars($lote['proveedor'] ?? '') ?>" 
                   placeholder="Nombre del proveedor">
        </div>
        
        <div style="margin:15px 0;">
            <label style="display:block; margin-bottom:8px; font-weight:bold; color:#333; font-style: italic;">
                📦 Cantidad:
            </label>
            <input type="text" name="cantidad" min="0.01" step="0.01" required
                   style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:14px;"
                   value="<?= $lote['cantidad'] ?>"
                   oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
        </div>
        
        <div style="margin:15px 0;">
            <label style="display:block; margin-bottom:8px; font-weight:bold; color:#333; font-style: italic;">
                💰 Precio de Compra:
            </label>
            <input type="text" name="precio_compra" min="0" step="0.01" required
                   style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:14px;"
                   value="<?= $lote['precio_compra'] ?>"
                   oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
        </div>
        
        <div style="margin-top:20px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
            <button type="submit"
                    style="background:#0d6efd; color:white; border:none; padding:10px 20px;
                    border-radius:6px; cursor:pointer; font-size:14px; font-weight:bold;">
                <i class="bi bi-check-lg"></i> Guardar Cambios
            </button>
            <button type="button" onclick="cerrarFormularioLote()"
                    style="background:#6c757d; color:white; border:none; padding:10px 20px;
                    border-radius:6px; cursor:pointer; font-size:14px; font-weight:bold;">
                <i class="bi bi-x-lg"></i> Cancelar
            </button>
        </div>
    </form>
</div>