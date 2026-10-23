<?php
include("../conexion.php");
$codigo = $_GET['codigo'] ?? '';

// 🔥 CONSULTA CON es_de_compra
$lotes = mysqli_query($conn, "SELECT * FROM lotes WHERE codigo_barra = '" . mysqli_real_escape_string($conn, $codigo) . "' ORDER BY fecha_ingreso DESC");

if (mysqli_num_rows($lotes) === 0) {
    echo '<tr><td colspan="6" style="text-align:center; padding:30px; color:#666;">
<i class="bi bi-inbox" style="font-size:40px; display:block; margin-bottom:10px;"></i>
No hay lotes registrados
</td></tr>';
} else {
    while ($lote = mysqli_fetch_assoc($lotes)) {
        // 🔥 USAR GANANCIA GUARDADA EN BD
        $ganancia = (float) $lote['ganancia_lote'];
        $porcentaje = (float) $lote['porcentaje_ganancia'];
        $color_ganancia = $ganancia >= 0 ? '#28a745' : '#dc3545';

        // 🔥 VERIFICAR SI ES DE TICKET
        $es_de_compra = (int) ($lote['es_de_compra'] ?? 0);
        $es_editable = ($es_de_compra == 0);

        echo "<tr style='transition:0.2s;'>
<td style='padding:10px 8px; text-align:center; font-weight:bold;'>{$lote['cantidad']}</td>
<td style='padding:10px 8px; text-align:center; font-weight:bold;'>$" . number_format($lote['precio_compra'], 2) . "</td>
<td style='padding:10px 8px; text-align:center;'>" . htmlspecialchars($lote['proveedor'] ?? 'N/A') . "</td>
<!-- 🔥 COLUMNA GANANCIAS (DESDE BD) -->
<td style='padding:10px 8px; text-align:center; font-weight:bold; color:{$color_ganancia};'>
$" . number_format($ganancia, 2) . "
<br>
<small style='font-size:11px; color:#666;'>(" . number_format($porcentaje, 1) . "%)</small>
</td>
<td style='padding:10px 8px; text-align:center;'>" . date('d/m/Y h:i A', strtotime($lote['fecha_ingreso'])) . "</td>
<td style='padding:10px 8px; text-align:center; white-space:nowrap;'>";

        // 🔥 BOTÓN EDITAR - Solo si es editable
        if ($es_editable) {
            echo "<i class='bi bi-pencil-square btn-editar-lote'
data-id='{$lote['id']}'
data-cantidad='{$lote['cantidad']}'
data-precio_compra='{$lote['precio_compra']}'
data-proveedor='" . htmlspecialchars($lote['proveedor']) . "'
style='font-size:16px; margin:0 5px; cursor:pointer; color:#0d6efd;
transition:0.2s; padding:5px;'
title='Editar Lote'></i>";
        } else {
            // 🔥 ICONO DE BLOQUEADO
            echo "<i class='bi bi-lock' 
style='font-size:14px; margin:0 5px; color:#ccc; cursor:not-allowed;'
title='Lote de ticket - No editable'></i>";
        }

        // 🔥 BOTÓN ELIMINAR - Solo si es editable
        if ($es_editable) {
            echo "<i class='bi bi-trash btn-eliminar-lote'
data-id='{$lote['id']}'
style='font-size:16px; margin:0 5px; cursor:pointer; color:#dc3545;
transition:0.2s; padding:5px;'
title='Eliminar Lote'></i>";
        } else {
            // 🔥 ICONO DE BLOQUEADO
            echo "<i class='bi bi-lock-fill' 
style='font-size:14px; margin:0 5px; color:#dc3545; opacity:0.3; cursor:not-allowed;'
title='Lote de ticket - No eliminable'></i>";
        }

        echo "</td>
</tr>";
    }
}
?>