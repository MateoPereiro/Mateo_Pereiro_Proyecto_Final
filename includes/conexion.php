<?php
// =====================================================================
// conexion.php — Conexión a MySQL con PDO.
// Crea el objeto $pdo que usan todas las páginas para consultar la base.
// =====================================================================

const DB_HOST = 'localhost';
const DB_NAME = 'gimnasio';
const DB_USUARIO = 'root';   // Usuario por defecto de XAMPP
const DB_CLAVE = '';         // En XAMPP el usuario root no tiene contraseña por defecto

function conectar_bd(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // Los errores se lanzan como excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Filas como arrays asociativos
        PDO::ATTR_EMULATE_PREPARES => false,               // Consultas preparadas reales (protegen contra inyección SQL)
    ];

    try {
        return new PDO($dsn, DB_USUARIO, DB_CLAVE, $opciones);
    } catch (PDOException $e) {
        error_log('Error de conexión: ' . $e->getMessage());   // El detalle queda en el log, no en pantalla
        http_response_code(500);
        die('No se pudo conectar con la base de datos. Verificá que MySQL esté iniciado en XAMPP.');
    }
}

// Cualquier error no controlado se registra en el log y se muestra un mensaje genérico
// (así no se revelan detalles internos ni trazas del motor al usuario).
set_exception_handler(function (Throwable $e) {
    error_log('Error no controlado: ' . $e->getMessage());
    http_response_code(500);
    echo 'Ocurrió un error inesperado. Intentá nuevamente o consultá al administrador.';
});

$pdo = conectar_bd();
