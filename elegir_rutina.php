<?php
// =====================================================================
// elegir_rutina.php — El usuario elige una rutina del catálogo: se copia
// a sus rutinas, con todos sus ejercicios. Solo POST + token CSRF.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';

$usuario = requerir_login($pdo);

if (!es_post()) {
    redirigir('catalogo_rutinas.php');
}
exigir_csrf();

// La rutina solo se puede asignar a un socio: se busca la ficha por DNI
$st = $pdo->prepare('SELECT id FROM socios WHERE dni = :dni AND archivado_en IS NULL');
$st->execute([':dni' => $usuario['dni']]);
$socio = $st->fetch();

if (!$socio) {
    flash('error', 'Para elegir rutinas necesitás un plan: tu ficha de socio se crea al comprarlo.');
    redirigir('ver_planes.php');
}

// Solo rutinas del catálogo (sin socio) y activas
$rutinaId = id_valido($_POST['rutina_id'] ?? null);
$st = $pdo->prepare('SELECT id, nombre, objetivo, entrenador_id FROM rutinas
                     WHERE id = :id AND socio_id IS NULL AND archivado_en IS NULL');
$st->execute([':id' => $rutinaId]);
$catalogo = $st->fetch();

if (!$catalogo) {
    flash('error', 'La rutina no está disponible.');
    redirigir('catalogo_rutinas.php');
}

// Evita copiar dos veces la misma rutina
$st = $pdo->prepare('SELECT id FROM rutinas WHERE socio_id = :socio AND nombre = :nombre AND archivado_en IS NULL');
$st->execute([':socio' => $socio['id'], ':nombre' => $catalogo['nombre']]);
if ($st->fetch()) {
    flash('error', 'Esa rutina ya está en Mis rutinas.');
    redirigir('mis_rutinas.php');
}

$pdo->beginTransaction();
try {
    // 1) Copia de la cabecera de la rutina, asignada al socio
    $st = $pdo->prepare('INSERT INTO rutinas (socio_id, entrenador_id, nombre, objetivo)
                         VALUES (:socio, :entrenador, :nombre, :objetivo)');
    $st->execute([
        ':socio' => $socio['id'],
        ':entrenador' => $catalogo['entrenador_id'],
        ':nombre' => $catalogo['nombre'],
        ':objetivo' => $catalogo['objetivo'],
    ]);
    $nuevaId = (int) $pdo->lastInsertId();

    // 2) Copia de todos los ejercicios de la rutina original
    $st = $pdo->prepare('INSERT INTO rutina_ejercicios (rutina_id, ejercicio, series, repeticiones)
                         SELECT :nueva, ejercicio, series, repeticiones FROM rutina_ejercicios
                         WHERE rutina_id = :origen ORDER BY id');
    $st->execute([':nueva' => $nuevaId, ':origen' => $catalogo['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

flash('ok', 'Rutina agregada a Mis rutinas.');
redirigir('mis_rutinas.php');
