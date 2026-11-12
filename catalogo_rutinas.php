<?php
// =====================================================================
// catalogo_rutinas.php — Rutinas generales. El usuario elige una y se copia a "Mis rutinas".
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);
$socio = socio_de_usuario($pdo, $usuario['dni']);

// Nombres de las rutinas que el socio ya tiene (para no ofrecerlas de nuevo)
$nombresMias = [];
if ($socio) {
    $st = $pdo->prepare('SELECT nombre FROM rutinas WHERE socio_id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $socio['id']]);
    $nombresMias = array_column($st->fetchAll(), 'nombre');
}

$catalogo = $pdo->query('SELECT r.id, r.nombre, r.objetivo, e.nombre AS entrenador_nombre, e.apellido AS entrenador_apellido
                         FROM rutinas r LEFT JOIN entrenadores e ON e.id = r.entrenador_id
                         WHERE r.socio_id IS NULL AND r.archivado_en IS NULL ORDER BY r.nombre')->fetchAll();

foreach ($catalogo as &$c) {
    $ej = $pdo->prepare('SELECT ejercicio, series, repeticiones FROM rutina_ejercicios WHERE rutina_id = :id ORDER BY id');
    $ej->execute([':id' => $c['id']]);
    $c['ejercicios'] = $ej->fetchAll();
}
unset($c);

encabezado('Catálogo de rutinas', $usuario);
?>
<?php if (!$socio): ?>
    <section class="tarjeta"><p class="vacio">Para elegir rutinas necesitás tener un plan. Comprá uno en la sección Planes.</p></section>
<?php endif; ?>

<?php if (!$catalogo): ?>
    <section class="tarjeta"><p class="vacio">Todavía no hay rutinas en el catálogo.</p></section>
<?php endif; ?>

<?php foreach ($catalogo as $c): ?>
<?php $yaElegida = in_array($c['nombre'], $nombresMias, true); ?>
<section class="tarjeta">
    <h2><?= escapar($c['nombre']) ?></h2>
    <p>
        <?= escapar($c['objetivo'] ?? '') ?>
        <?php if ($c['entrenador_nombre'] !== null): ?>
            <small>· Entrenador: <?= escapar($c['entrenador_nombre'] . ' ' . $c['entrenador_apellido']) ?></small>
        <?php endif; ?>
    </p>
    <?php if ($c['ejercicios']): ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead><tr><th>Ejercicio</th><th>Series</th><th>Repeticiones</th></tr></thead>
            <tbody>
            <?php foreach ($c['ejercicios'] as $ej): ?>
                <tr>
                    <td><?= escapar($ej['ejercicio']) ?></td>
                    <td><?= (int) $ej['series'] ?></td>
                    <td><?= (int) $ej['repeticiones'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php if ($socio): ?>
        <?php if ($yaElegida): ?>
            <span class="etiqueta etiqueta-verde">Ya está en Mis rutinas</span>
        <?php else: ?>
            <form method="post" action="elegir_rutina.php" style="margin-top:.6rem">
                <?= campo_csrf() ?>
                <input type="hidden" name="rutina_id" value="<?= (int) $c['id'] ?>">
                <button type="submit">Elegir esta rutina</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php pie();
