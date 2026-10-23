<?php
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

include("../conexion.php");
include("../login/registrar_actividad.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $corte_id = (int) ($_POST['corte_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $usuario_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    if ($corte_id <= 0) {
        throw new Exception('ID de corte inválido');
    }

    // 🔥 VERIFICAR CONTRASEÑA DE ADMIN
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM usuarios WHERE id = $usuario_id"));
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Contraseña incorrecta');
    }

    // 🔥 OBTENER DATOS DEL CORTE PARA AUDITORÍA
    $corte = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cortes_caja WHERE id = $corte_id"));
    if (!$corte) {
        throw new Exception('Corte no encontrado');
    }

    // 🔥 ELIMINAR CORTE
    $stmt = mysqli_prepare($conn, "DELETE FROM cortes_caja WHERE id = ? AND usuario_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $corte_id, $usuario_id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error al eliminar: ' . mysqli_error($conn));
    }

    // 🔥 REGISTRAR ACTIVIDAD DELETE
    if (function_exists('registrar_actividad') && $corte) {
        registrar_actividad(
            $conn,
            $usuario_id,
            $username,
            "DELETE",
            "Corte de caja eliminado: #$corte_id (Total: $" . number_format($corte['total_efectivo_contado'], 2) . ")",
            "cortes_caja",
            $corte_id,
            ['total_ventas' => $corte['total_ventas_bd'], 'total_efectivo' => $corte['total_efectivo_contado'], 'estado' => $corte['estado']],
            []
        );
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Corte eliminado correctamente']);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>