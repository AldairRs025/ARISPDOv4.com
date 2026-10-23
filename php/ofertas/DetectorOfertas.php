<?php
// DetectorOfertas.php
class DetectorOfertas
{
    private $conn;

    public function __construct($conexion)
    {
        $this->conn = $conexion;
    }

    /**
     * Detecta el tipo de oferta en un texto
     */
    public function detectarTipoOferta($texto, $precioOriginal, $precioOferta)
    {
        $texto = strtolower($texto);
        $oferta = [
            'tipo' => 'descuento_directo',
            'porcentaje' => 0,
            'descripcion' => ''
        ];

        // Detectar 2x1
        if (preg_match('/2x1|2 por 1|dos por uno/', $texto)) {
            $oferta['tipo'] = '2x1';
            $oferta['porcentaje'] = 50;
            $oferta['descripcion'] = '2x1 - Lleva 2, paga 1';
        }
        // Detectar 3x2
        elseif (preg_match('/3x2|3 por 2|tres por dos/', $texto)) {
            $oferta['tipo'] = '3x2';
            $oferta['porcentaje'] = round((1 / 3) * 100, 2);
            $oferta['descripcion'] = '3x2 - Lleva 3, paga 2';
        }
        // Detectar bonificación
        elseif (preg_match('/bonificacion|bonific|regalo|gratis/', $texto)) {
            $oferta['tipo'] = 'bonificacion';
            $oferta['descripcion'] = 'Bonificación detectada';
        }
        // Detectar "Planea y Ahorra" (Amazon)
        elseif (preg_match('/planea y ahorra|suscribete y ahorra/', $texto)) {
            $oferta['tipo'] = 'planea_y_ahorra';
            $oferta['descripcion'] = 'Planea y Ahorra - Amazon';
        }
        // Detectar descuento por porcentaje
        elseif (preg_match('/(\d+)%\s*(off|descuento|dto)/', $texto, $matches)) {
            $oferta['tipo'] = 'descuento_porcentaje';
            $oferta['porcentaje'] = floatval($matches[1]);
            $oferta['descripcion'] = $matches[1] . '% de descuento';
        }
        // Calcular porcentaje si tenemos precios
        elseif ($precioOriginal > 0 && $precioOferta > 0) {
            $oferta['porcentaje'] = round((($precioOriginal - $precioOferta) / $precioOriginal) * 100, 2);
            $oferta['descripcion'] = $oferta['porcentaje'] . '% de descuento';
        }

        return $oferta;
    }

    /**
     * Compara ofertas y determina cuál es mejor
     */
    public function compararOfertas($ofertas)
    {
        $mejorOferta = null;
        $mejorPrecioUnitario = PHP_FLOAT_MAX;

        foreach ($ofertas as $oferta) {
            if ($oferta['precio_oferta'] < $mejorPrecioUnitario) {
                $mejorPrecioUnitario = $oferta['precio_oferta'];
                $mejorOferta = $oferta;
            }
        }

        return [
            'mejor_oferta' => $mejorOferta,
            'precio_unitario' => $mejorPrecioUnitario
        ];
    }

    /**
     * Busca productos en APIs externas (simulado)
     */
    public function buscarEnAPIs($producto, $codigoBarras = null)
    {
        $resultados = [];

        // Simulación de búsqueda
        $tiendas = $this->obtenerTiendasActivas();

        foreach ($tiendas as $tienda) {
            $resultadoSimulado = $this->simularBusquedaAPI($producto, $tienda);
            if ($resultadoSimulado) {
                $resultados[] = $resultadoSimulado;
            }
        }

        return $resultados;
    }

    private function obtenerTiendasActivas()
    {
        $sql = "SELECT * FROM tiendas_priorizadas WHERE activa = TRUE ORDER BY 
                CASE nivel_confianza 
                    WHEN 'alta' THEN 1 
                    WHEN 'media' THEN 2 
                    WHEN 'baja' THEN 3 
                END";
        $result = mysqli_query($this->conn, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    private function simularBusquedaAPI($producto, $tienda)
    {
        $nombreTienda = is_array($tienda) ? $tienda['nombre'] : $tienda;

        // Generar precios base aleatorios pero consistentes para el mismo producto
        $precioBase = rand(50, 500);
        $descuento = rand(5, 35);
        $precioOferta = round($precioBase * (1 - $descuento / 100), 2);

        // Tipos de ofertas por tienda
        $tiposOferta = [
            'Walmart' => ['tipo' => 'descuento_porcentaje', 'desc' => $descuento . '% de descuento'],
            'Amazon México' => ['tipo' => 'planea_y_ahorra', 'desc' => 'Suscríbete y Ahorra'],
            'Soriana' => ['tipo' => '2x1', 'desc' => '2x1 en productos seleccionados'],
            "sams" => ['tipo' => '3x2', 'desc' => '3x2 en mayoreo'],
            'Bodega Aurrera' => ['tipo' => 'descuento_directo', 'desc' => 'Precio especial'],
            'Chedraui' => ['tipo' => 'bonificacion', 'desc' => 'Lleva 2, paga 1'],
            'Costco' => ['tipo' => 'descuento_porcentaje', 'desc' => 'Descuento por volumen'],
            'La Comer' => ['tipo' => 'descuento_porcentaje', 'desc' => 'Oferta semanal']
        ];

        $ofertaInfo = $tiposOferta[$nombreTienda] ?? ['tipo' => 'descuento_porcentaje', 'desc' => $descuento . '% OFF'];

        return [
            'nombre_tienda' => $nombreTienda,
            'nombre_producto' => $producto,
            'precio_original' => $precioBase,
            'precio_oferta' => $precioOferta,
            'descripcion' => $ofertaInfo['desc'],
            'link_producto' => 'https://www.' . strtolower(str_replace(' ', '', $nombreTienda)) . '.com.mx/search?q=' . urlencode($producto),
            'nivel_confianza' => $tienda['nivel_confianza'] ?? 'media'
        ];
    }

    /**
     * Obtiene eventos de ofertas próximos
     */
    public function obtenerEventosProximos($dias = 30)
    {
        $sql = "SELECT * FROM calendario_ofertas 
                WHERE fecha_inicio BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $dias DAY)
                AND activo = TRUE
                ORDER BY fecha_inicio ASC";

        $result = mysqli_query($this->conn, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}
?>