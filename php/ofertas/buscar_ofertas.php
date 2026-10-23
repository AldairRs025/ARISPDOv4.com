<?php
// buscar_ofertas.php
session_start();
include("../conexion.php");
include("DetectorOfertas.php");

$detector = new DetectorOfertas($conn);
$eventosProximos = $detector->obtenerEventosProximos();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $busqueda = mysqli_real_escape_string($conn, $_POST['busqueda']);
    $codigoBarras = isset($_POST['codigo_barras']) ? mysqli_real_escape_string($conn, $_POST['codigo_barras']) : null;

    // Buscar en APIs
    $resultadosAPI = $detector->buscarEnAPIs($busqueda, $codigoBarras);

    // Procesar cada resultado
    $ofertasProcesadas = [];
    foreach ($resultadosAPI as $resultado) {
        $tipoOferta = $detector->detectarTipoOferta(
            $resultado['descripcion'],
            $resultado['precio_original'],
            $resultado['precio_oferta']
        );

        $ofertasProcesadas[] = array_merge($resultado, $tipoOferta);
    }

    // Comparar y encontrar la mejor oferta
    $comparacion = $detector->compararOfertas($ofertasProcesadas);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Buscador Inteligente de Ofertas - Los Nietos®</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .header-buscador {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .search-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .search-input-group {
            position: relative;
        }

        .search-input-group input {
            padding-left: 3rem;
            border-radius: 50px;
            border: 2px solid #e0e0e0;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .search-input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .barcode-scanner {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--primary-color);
            font-size: 1.3rem;
            transition: transform 0.2s;
        }

        .barcode-scanner:hover {
            transform: translateY(-50%) scale(1.1);
        }

        .eventos-card {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 5px solid var(--warning-color);
        }

        .resultados-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .resultados-table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .resultados-table th {
            padding: 1rem;
            font-weight: 600;
            border: none;
        }

        .resultados-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .resultados-table tbody tr {
            transition: all 0.3s;
        }

        .resultados-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }

        .mejor-oferta {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
            border-left: 5px solid var(--success-color);
        }

        .badge-oferta {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-2x1 {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-3x2 {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .badge-descuento {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-bonificacion {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-planea {
            background: #fce4ec;
            color: #c2185b;
        }

        .precio-original {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
        }

        .precio-oferta {
            color: var(--success-color);
            font-weight: bold;
            font-size: 1.2rem;
        }

        .ahorro-badge {
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .tienda-confianza {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .tienda-confianza.alta {
            color: var(--success-color);
        }

        .tienda-confianza.media {
            color: var(--warning-color);
        }

        .tienda-confianza.baja {
            color: var(--danger-color);
        }

        .comparador-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .comparador-card {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .loading-spinner.show {
            display: block;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header-buscador">
        <div class="container">
            <h1 class="text-center mb-3">
                <i class="bi bi-search-heart"></i> Buscador Inteligente de Ofertas
            </h1>
            <p class="text-center mb-0">Compara precios y encuentra las mejores promociones automáticamente</p>
        </div>
    </div>

    <div class="container">
        <!-- Eventos Próximos -->
        <?php if (!empty($eventosProximos)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="mb-3"><i class="bi bi-calendar-event"></i> Eventos de Ofertas Próximos</h3>
                    <div class="row">
                        <?php foreach ($eventosProximos as $evento): ?>
                            <div class="col-md-4 mb-3">
                                <div class="eventos-card">
                                    <h5 class="mb-2"><?= htmlspecialchars($evento['nombre_evento']) ?></h5>
                                    <p class="mb-1">
                                        <i class="bi bi-calendar-range"></i>
                                        <?= date('d/m/Y', strtotime($evento['fecha_inicio'])) ?> -
                                        <?= date('d/m/Y', strtotime($evento['fecha_fin'])) ?>
                                    </p>
                                    <small><?= htmlspecialchars($evento['descripcion']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulario de Búsqueda -->
        <div class="search-container">
            <form method="POST" id="formBusqueda">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <div class="search-input-group">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" name="busqueda" class="form-control"
                                placeholder="Busca un producto por nombre..." required
                                value="<?= isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : '' ?>">
                            <i class="bi bi-upc-scan barcode-scanner" title="Escanear código de barras"
                                onclick="activarEscanner()"></i>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="text" name="codigo_barras" class="form-control"
                            placeholder="O ingresa código de barras" id="inputCodigoBarras">
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-search"></i> Buscar Mejores Ofertas
                    </button>
                </div>
            </form>
        </div>

        <!-- Loading -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Buscando en múltiples tiendas...</p>
        </div>

        <?php if (isset($ofertasProcesadas) && !empty($ofertasProcesadas)): ?>
            <!-- Comparador de Ofertas -->
            <div class="comparador-section">
                <h3 class="mb-4"><i class="bi bi-trophy"></i> Mejor Oferta Encontrada</h3>
                <div class="comparador-card mb-4">
                    <h4><?= htmlspecialchars($comparacion['mejor_oferta']['nombre_tienda']) ?></h4>
                    <div class="display-4 text-success my-3">
                        $<?= number_format($comparacion['precio_unitario'], 2) ?>
                        <small class="text-muted">/unidad</small>
                    </div>
                    <div class="ahorro-badge mb-3">
                        Ahorras <?= $comparacion['ahorro_vs_precio_normal'] ?>% vs otras tiendas
                    </div>
                    <a href="<?= htmlspecialchars($comparacion['mejor_oferta']['link_producto']) ?>" target="_blank"
                        class="btn btn-success btn-lg">
                        <i class="bi bi-box-arrow-up-right"></i> Ver Oferta
                    </a>
                </div>
            </div>

            <!-- Tabla de Resultados -->
            <div class="resultados-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tienda</th>
                                <th>Producto</th>
                                <th>Tipo de Oferta</th>
                                <th>Precio Original</th>
                                <th>Precio Oferta</th>
                                <th>Ahorro</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ofertasProcesadas as $index => $oferta):
                                $esMejor = ($oferta === $comparacion['mejor_oferta']);
                                ?>
                                <tr class="<?= $esMejor ? 'mejor-oferta' : '' ?>">
                                    <td>
                                        <div class="tienda-confianza <?= strtolower($oferta['nivel_confianza']) ?>">
                                            <i class="bi bi-shop"></i>
                                            <?= htmlspecialchars($oferta['nombre_tienda']) ?>
                                            <?php if ($oferta['nivel_confianza'] == 'alta'): ?>
                                                <i class="bi bi-patch-check-fill" title="Tienda de confianza"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($oferta['nombre_producto']) ?></strong>
                                        <?php if ($esMejor): ?>
                                            <br><span class="badge bg-success">MEJOR PRECIO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-oferta badge-<?= str_replace('_', '-', $oferta['tipo']) ?>">
                                            <?php
                                            $tiposOferta = [
                                                '2x1' => '🎁 2x1',
                                                '3x2' => '🎁 3x2',
                                                'descuento_porcentaje' => '💰 ' . $oferta['porcentaje'] . '% OFF',
                                                'descuento_directo' => '💵 Descuento',
                                                'bonificacion' => '🎁 Bonificación',
                                                'planea_y_ahorra' => '📦 Planea y Ahorra'
                                            ];
                                            echo $tiposOferta[$oferta['tipo']] ?? $oferta['tipo'];
                                            ?>
                                        </span>
                                        <?php if ($oferta['descripcion']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($oferta['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="precio-original">
                                        $<?= number_format($oferta['precio_original'], 2) ?>
                                    </td>
                                    <td class="precio-oferta">
                                        $<?= number_format($oferta['precio_oferta'], 2) ?>
                                    </td>
                                    <td>
                                        <span class="ahorro-badge">
                                            <?= $oferta['porcentaje'] ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($oferta['link_producto']) ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (isset($_POST['busqueda'])): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No se encontraron ofertas para
                "<?= htmlspecialchars($_POST['busqueda']) ?>"
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        function activarEscanner() {
            // Crear modal para el escáner
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'scannerModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Escanear Código de Barras</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div id="reader" style="width: 100%;"></div>
                            <p class="mt-2 text-muted">Apunta la cámara al código de barras</p>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Iniciar escáner
            const html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText) => {
                    document.getElementById('inputCodigoBarras').value = decodedText;
                    bsModal.hide();
                    html5QrCode.stop();
                    document.getElementById('formBusqueda').submit();
                },
                (errorMessage) => {
                    // Ignorar errores de escaneo continuo
                }
            ).catch(err => {
                alert('Error al iniciar la cámara: ' + err);
            });

            modal.addEventListener('hidden.bs.modal', () => {
                html5QrCode.stop().catch(() => { });
                modal.remove();
            });
        }

        // Mostrar loading al buscar
        document.getElementById('formBusqueda').addEventListener('submit', function () {
            document.getElementById('loadingSpinner').classList.add('show');
        });
    </script>
</body>

</html>