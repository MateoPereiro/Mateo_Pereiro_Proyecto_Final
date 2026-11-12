<?php
// =====================================================================
// quitar_mi_rutina.php — El socio elimina una rutina de SU panel.
// Solo puede borrar rutinas asignadas a su propio socio (nunca el catálogo).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);

if (!es_post()) {
    redirigir('mis_rutinas.php');
}
exigir_csrf();

$socio = socio_de_usuario($pdo, $usuario['dni']);
$rutinaId = id_valido($_POST['rutina_id'] ?? null);

if (!$socio || $rutinaId === null) {
    flash('error', 'No se pudo eliminar la rutina.');
    redirigir('mis_rutinas.php');
}

// La condición socio_id protege que solo se borre una rutina propia.
// Los ejercicios se borran solos (ON DELETE CASCADE).
$st = $pdo->prepare('DELETE FROM rutinas WHERE id = :id AND socio_id = :socio');
$st->execute([':id' => $rutinaId, ':socio' => $socio['id']]);

flash($st->rowCount() === 1 ? 'ok' : 'error',
      $st->rowCount() === 1 ? 'Rutina eliminada de tu panel.' : 'La rutina no existe en tu panel.');
redirigir('mis_rutinas.php');
