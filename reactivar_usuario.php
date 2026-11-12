<?php
// =====================================================================
// reactivar_usuario.php — Vuelve a activar una cuenta archivada.
// Solo POST + CSRF. No cambia contraseña, correo ni rol.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';

requerir_admin($pdo);

if (!es_post()) {
    redirigir('usuarios_archivados.php');
}
exigir_csrf();

$id = id_valido($_POST['id'] ?? null);
if ($id === null) {
    flash('error', 'Identificador de usuario no válido.');
    redirigir('usuarios_archivados.php');
}

$st = $pdo->prepare('UPDATE usuarios SET archivado_en = NULL
                     WHERE id = :id AND archivado_en IS NOT NULL');
$st->execute([':id' => $id]);

flash($st->rowCount() === 1 ? 'ok' : 'error',
      $st->rowCount() === 1 ? 'Cuenta reactivada correctamente.' : 'La cuenta no estaba archivada o no existe.');
redirigir('usuarios_archivados.php');
