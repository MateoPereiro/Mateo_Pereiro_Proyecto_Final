<?php
// =====================================================================
// archivar_usuario.php — Archiva una cuenta SIN borrar sus registros.
// Solo por POST + token CSRF (nunca por un enlace GET).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';

$admin = requerir_admin($pdo);

if (!es_post()) {
    redirigir('admin_usuarios.php');
}
exigir_csrf();

$id = id_valido($_POST['id'] ?? null);
if ($id === null) {
    flash('error', 'Identificador de usuario no válido.');
    redirigir('admin_usuarios.php');
}

if ($id === (int) $admin['id']) {
    flash('error', 'No podés archivar tu propia cuenta mientras la estás usando.');
    redirigir('admin_usuarios.php');
}

// Se marca la fecha de archivado; el registro sigue existiendo en la base
$st = $pdo->prepare('UPDATE usuarios SET archivado_en = :ahora
                     WHERE id = :id AND archivado_en IS NULL');
$st->execute([':ahora' => date('Y-m-d H:i:s'), ':id' => $id]);

if ($st->rowCount() === 1) {
    flash('ok', 'Usuario archivado. Podés consultarlo y reactivarlo desde "Ver archivados".');
} else {
    flash('error', 'El usuario ya estaba archivado o no existe.');
}
redirigir('admin_usuarios.php');
