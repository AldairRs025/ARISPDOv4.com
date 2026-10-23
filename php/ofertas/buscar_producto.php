<?php
// buscar_producto.php
header('Content-Type: application/json');
include("../conexion.php");
include("DetectorOfertas.php");

$detector = new DetectorOfertas($conn);

if (!isset($_GET['producto'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Producto requerido']);
    exit;
}

$producto = $_GET['producto'];
$codigoBarras = $_GET['codigo_barras'] ?? null;

// Buscar en múltiples fuentes
$resultados = $detector->buscarEnAPIs($producto, $codigoBarras);

// Procesar ofertas
$ofertasProcesadas = [];
foreach ($resultados as $resultado) {
    $tipoOferta = $detector->detectarTipoOferta(
        $resultado['descripcion'],
        $resultado['precio_original'],
        $resultado['precio_oferta']
    );

    $ofertasProcesadas[] = array_merge($resultado, $tipoOferta);
}

// Comparar
$comparacion = $detector->compararOfertas($ofertasProcesadas);

echo json_encode([
    'success' => true,
    'total_resultados' => count($ofertasProcesadas),
    'mejor_oferta' => $comparacion['mejor_oferta'],
    'precio_unitario_mejor' => $comparacion['precio_unitario'],
    'ahorro' => $comparacion['ahorro_vs_precio_normal'],
    'ofertas' => $ofertasProcesadas
]);
?>