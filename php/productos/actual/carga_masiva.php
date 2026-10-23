<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Carga Masiva - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Productos-STYLE/carga-masiva.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos®</div>
        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="carga_masiva.php" class="icon-btn">
                <i class="bi bi-upload"></i>
                <span class="tooltip">CARGA MASIVA</span>
            </a>
            <a href="../login/logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="carga-container">
        <!-- HEADER -->
        <div class="carga-header">
            <h1>📦 Carga Masiva de Productos</h1>
            <p>Sube un archivo Excel para agregar múltiples productos al inventario</p>
        </div>

        <!-- PASOS -->
        <div class="pasos-carga">
            <div class="paso">
                <div class="paso-numero">1</div>
                <div class="paso-info">
                    <h4>📥 Descargar Plantilla</h4>
                    <p>Descarga el formato Excel con las columnas requeridas</p>
                </div>
                <a href="descargar_plantilla.php" class="btn-paso">
                    <i class="bi bi-download"></i> Descargar
                </a>
            </div>

            <div class="paso">
                <div class="paso-numero">2</div>
                <div class="paso-info">
                    <h4>✏️ Llenar Datos</h4>
                    <p>Completa la información de tus productos en el Excel</p>
                </div>
            </div>

            <div class="paso">
                <div class="paso-numero">3</div>
                <div class="paso-info">
                    <h4>📤 Subir Archivo</h4>
                    <p>Selecciona tu archivo Excel y presiona cargar</p>
                </div>
            </div>
        </div>

        <!-- FORMULARIO DE CARGA -->
        <div class="formulario-carga">
            <form action="procesar_carga.php" method="POST" enctype="multipart/form-data" id="formCarga">
                <div class="form-group">
                    <label for="archivo_excel">📎 Seleccionar Archivo Excel:</label>
                    <input type="file" name="archivo_excel" id="archivo_excel" accept=".xlsx,.xls" required>
                    <small>Formatos permitidos: .xlsx, .xls (Máx. 10MB)</small>
                </div>

                <div class="opciones-carga">
                    <h4>⚙️ Opciones de Carga:</h4>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="omitir_repetidos" checked>
                            <span>Omitir productos con código de barras repetido</span>
                        </label>
                        <label>
                            <input type="checkbox" name="actualizar_existencias" checked>
                            <span>Actualizar existencias si el producto ya existe</span>
                        </label>
                        <label>
                            <input type="checkbox" name="validar_datos" checked>
                            <span>Validar datos antes de insertar</span>
                        </label>
                    </div>
                </div>

                <div class="botones-carga">
                    <button type="submit" class="btn-cargar">
                        <i class="bi bi-cloud-upload"></i> Cargar Productos
                    </button>
                    <a href="CRUD-Product.php" class="btn-cancelar">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </form>
        </div>

        <!-- INFORMACIÓN ADICIONAL -->
        <div class="info-adicional">
            <h3>📋 Columnas Requeridas en el Excel:</h3>
            <table class="tabla-columnas">
                <thead>
                    <tr>
                        <th>Columna</th>
                        <th>Campo</th>
                        <th>Tipo</th>
                        <th>Requerido</th>
                        <th>Ejemplo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>A</td>
                        <td>codigo_barra</td>
                        <td>Texto (50)</td>
                        <td>✅ Sí</td>
                        <td>750100123456</td>
                    </tr>
                    <tr>
                        <td>B</td>
                        <td>nombre_producto</td>
                        <td>Texto (100)</td>
                        <td>✅ Sí</td>
                        <td>Leche Entera 1L</td>
                    </tr>
                    <tr>
                        <td>C</td>
                        <td>cantidad</td>
                        <td>Número (10,2)</td>
                        <td>✅ Sí</td>
                        <td>100</td>
                    </tr>
                    <tr>
                        <td>D</td>
                        <td>marca</td>
                        <td>Texto (100)</td>
                        <td>⚠️ Opcional</td>
                        <td>Alpura</td>
                    </tr>
                    <tr>
                        <td>E</td>
                        <td>categoria</td>
                        <td>Texto (100)</td>
                        <td>⚠️ Opcional</td>
                        <td>Lácteos</td>
                    </tr>
                    <tr>
                        <td>F</td>
                        <td>precio_compra</td>
                        <td>Número (10,2)</td>
                        <td>✅ Sí</td>
                        <td>18.50</td>
                    </tr>
                    <tr>
                        <td>G</td>
                        <td>precio_venta</td>
                        <td>Número (10,2)</td>
                        <td>✅ Sí</td>
                        <td>25.00</td>
                    </tr>
                    <tr>
                        <td>H</td>
                        <td>stock_minimo</td>
                        <td>Número (11)</td>
                        <td>⚠️ Opcional</td>
                        <td>20</td>
                    </tr>
                    <tr>
                        <td>I</td>
                        <td>proveedor</td>
                        <td>Texto (100)</td>
                        <td>⚠️ Opcional</td>
                        <td>Distribuidora ABC</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ALERTAS -->
        <div class="alertas-info">
            <div class="alerta alerta-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <div>
                    <strong>⚠️ Importante:</strong>
                    <ul>
                        <li>El código de barras debe ser único</li>
                        <li>No uses símbolos de moneda ($)</li>
                        <li>Usa punto decimal (.) no coma (,)</li>
                        <li>La primera fila debe contener los encabezados</li>
                        <li>Limita a 500-1000 productos por archivo</li>
                    </ul>
                </div>
            </div>

            <div class="alerta alerta-info">
                <i class="bi bi-info-circle"></i>
                <div>
                    <strong>ℹ️ Nota:</strong>
                    <ul>
                        <li>El estado se establece como "activo" por defecto</li>
                        <li>La fecha de ingreso se genera automáticamente</li>
                        <li>Se validará que los códigos de barras no existan</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- SCRIPTS -->
    <script>
        // Validar tipo de archivo antes de enviar
        document.getElementById('formCarga').addEventListener('submit', function (e) {
            const archivo = document.getElementById('archivo_excel').files[0];
            if (archivo) {
                const extensiones = ['xlsx', 'xls']; // ✅ AHORA SÍ XLSX
                const nombreArchivo = archivo.name.toLowerCase();
                const extension = nombreArchivo.split('.').pop();

                if (!extensiones.includes(extension)) {
                    e.preventDefault();
                    alert('❌ Error: El archivo debe ser .xlsx o .xls');
                    return false;
                }

                if (archivo.size > 10 * 1024 * 1024) {
                    e.preventDefault();
                    alert('❌ Error: El archivo no debe pesar más de 10MB');
                    return false;
                }

                // Mostrar confirmación
                if (!confirm('⚠️ ¿Estás seguro de cargar ' + archivo.name + '?\n\nSe procesarán todos los productos del archivo.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>

</html>