<?php
include("../conexion.php");
$codigo = $_GET['codigo'] ?? '';
$productos = mysqli_query($conn, "SELECT * FROM productos WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "'");
$row = mysqli_fetch_assoc($productos);
if (!$row) { 
    echo ''; 
    exit(); 
}
$stockBajo = $row['cantidad'] <= $row['stock_minimo'];
?>
<tr data-codigo="<?= $row['codigo_barra'] ?>">
<td><?= $row['codigo_barra'] ?></td>
<td><?= htmlspecialchars($row['nombre_producto']) ?></td>
<td class="<?= $stockBajo ? 'stock-bajo' : 'stock-ok' ?>">
<?= $row['cantidad'] ?>
</td>
<td><?= htmlspecialchars($row['marca']) ?></td>
<td><?= htmlspecialchars($row['categoria']) ?></td>
<td>$<?= number_format($row['precio_compra'], 2) ?></td>
<td>$<?= number_format($row['total_compra'], 2) ?></td>
<td>$<?= number_format($row['precio_venta'], 2) ?></td>
<td>$<?= number_format($row['total_venta'], 2) ?></td>
<td><span class="estado estado-<?= $row['estado'] ?>"><?= $row['estado'] ?></span></td>
<td>
<?php if ($row['imagen']): ?>
<img src="../../img/PRODUCTOS/<?= $row['imagen'] ?>" alt="Imagen" style="max-width:50px">
<?php else: ?> - <?php endif; ?>
</td>
<td class="acciones">
<i class="bi bi-pencil-square btn-editar" data-id="<?= $row['codigo_barra'] ?>" ...></i>
<i class="bi bi-trash btn-eliminar" onclick="eliminarProducto('<?= $row['codigo_barra'] ?>')"></i>
<button type="button" onclick="mostrarLotes('<?= $row['codigo_barra'] ?>', '<?= htmlspecialchars($row['nombre_producto']) ?>')" style="background:none; border:none; color:#17a2b8; margin-left:5px;">
<i class="bi bi-eye"></i>
</button>
</td>
</tr>
<?php
// 🔥 AGREGAR ESTO AL FINAL: Retornar JSON si se solicita
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'precio_venta' => $row['precio_venta'],
        'total_venta' => $row['total_venta'],
        'cantidad' => $row['cantidad'],
        'total_compra' => $row['total_compra']
    ]);
    exit();
}
?>