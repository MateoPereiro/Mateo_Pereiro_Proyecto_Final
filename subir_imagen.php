<?php
// =====================================================================
// subir_imagen.php — Sube la foto de un plan o de un entrenador.
// La imagen se guarda como img/<sección>/<id>.<extensión>, que es lo que
// lee imagen_de() para mostrarla en ver_planes.php y ver_entrenadores.php.
// Solo administradores, solo por POST y con token CSRF.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';

$admin = requerir_admin($pdo);

if (!es_post()) {
    redirigir('panel.php');
}
exigir_csrf();

// Lista fija de secciones: nunca se usa un nombre de tabla o carpeta que venga del usuario sin validar
// 'tabla' null = no es un registro de la base: el id es el número del espacio (1 a 'maximo')
$secciones = [
    'planes' => ['tabla' => 'planes', 'volver' => 'planes.php'],
    'entrenadores' => ['tabla' => 'entrenadores', 'volver' => 'entrenadores.php'],
    'instalacion' => ['tabla' => null, 'volver' => 'instalacion.php', 'maximo' => 5],
];

$carpeta = $_POST['carpeta'] ?? '';
if (!isset($secciones[$carpeta])) {
    flash('error', 'Sección no válida.');
    redirigir('panel.php');
}
$tabla = $secciones[$carpeta]['tabla'];
$volver = $secciones[$carpeta]['volver'];

$id = id_valido($_POST['id'] ?? null);
if ($id === null) {
    flash('error', 'Identificador no válido.');
    redirigir($volver);
}

if ($tabla === null) {
    // Fotos de las instalaciones: solo los espacios 1 a 5 que muestra instalacion.php
    if ($id > $secciones[$carpeta]['maximo']) {
        flash('error', 'Espacio no válido.');
        redirigir($volver);
    }
} else {
    $st = $pdo->prepare("SELECT id FROM $tabla WHERE id = :id");   // $tabla sale de la lista fija de arriba
    $st->execute([':id' => $id]);
    if (!$st->fetch()) {
        flash('error', 'No se encontró el registro para subirle la foto.');
        redirigir($volver);
    }
}

$archivo = $_FILES['imagen'] ?? null;
if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
    // Un archivo mayor al límite de PHP (2 MB en XAMPP por defecto) también cae acá
    flash('error', 'No se pudo recibir la imagen. Elegí un archivo de hasta 2 MB e intentá de nuevo.');
    redirigir($volver);
}

if ($archivo['size'] > 2 * 1024 * 1024) {
    flash('error', 'La imagen supera los 2 MB.');
    redirigir($volver);
}

// Se verifica el contenido real del archivo, no solo su extensión
$extensiones = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
if (!isset($extensiones[$tipo]) || @getimagesize($archivo['tmp_name']) === false) {
    flash('error', 'Solo se aceptan imágenes JPG, PNG o WEBP.');
    redirigir($volver);
}
$extension = $extensiones[$tipo];

$directorio = __DIR__ . '/img/' . $carpeta;
if (!is_dir($directorio) && !mkdir($directorio, 0775, true)) {
    flash('error', 'No se pudo crear la carpeta de imágenes.');
    redirigir($volver);
}

// Se borra la foto anterior (con cualquier extensión) para que no queden dos
foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
    $anterior = "$directorio/$id.$ext";
    if (is_file($anterior)) {
        unlink($anterior);
    }
}

if (move_uploaded_file($archivo['tmp_name'], "$directorio/$id.$extension")) {
    flash('ok', 'Foto guardada correctamente.');
} else {
    flash('error', 'No se pudo guardar la foto. Revisá los permisos de la carpeta img.');
}
redirigir($volver);
