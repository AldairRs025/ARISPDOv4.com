<?php
/**
 * Registrar actividad del usuario
 * @param mysqli $conn Conexión a la base de datos
 * @param int $user_id ID del usuario
 * @param string $username Nombre de usuario
 * @param string $accion Tipo de acción (LOGIN, CREATE, UPDATE, DELETE, etc.)
 * @param string $descripcion Descripción detallada
 * @param string $tabla_afectada Tabla de BD afectada (productos, lotes, usuarios, etc.)
 * @param mixed $registro_id ID del registro afectado
 * @param array $datos_antiguos Datos antes del cambio (para UPDATE/DELETE)
 * @param array $datos_nuevos Datos después del cambio (para CREATE/UPDATE)
 */
function registrar_actividad($conn, $user_id, $username, $accion, $descripcion = '', 
                            $tabla_afectada = '', $registro_id = '', 
                            $datos_antiguos = [], $datos_nuevos = []) {
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 255);
    
    // Escapar datos para seguridad
    $accion = mysqli_real_escape_string($conn, $accion);
    $descripcion = mysqli_real_escape_string($conn, $descripcion);
    $tabla_afectada = mysqli_real_escape_string($conn, $tabla_afectada);
    $registro_id = mysqli_real_escape_string($conn, $registro_id);
    
    // Convertir arrays a JSON
    $datos_antiguos_json = !empty($datos_antiguos) ? mysqli_real_escape_string($conn, json_encode($datos_antiguos, JSON_UNESCAPED_UNICODE)) : NULL;
    $datos_nuevos_json = !empty($datos_nuevos) ? mysqli_real_escape_string($conn, json_encode($datos_nuevos, JSON_UNESCAPED_UNICODE)) : NULL;
    
    $sql = "INSERT INTO actividades 
            (user_id, username, accion, descripcion, tabla_afectada, registro_id, 
             datos_antiguos, datos_nuevos, ip_address, user_agent, fecha) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        // 🔥 CORREGIDO: 'isssssssss' = 1 integer + 9 strings = 10 parámetros
        mysqli_stmt_bind_param($stmt, "isssssssss", 
            $user_id, $username, $accion, $descripcion, $tabla_afectada, 
            $registro_id, $datos_antiguos_json, $datos_nuevos_json, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    }
    return false;
}
?>