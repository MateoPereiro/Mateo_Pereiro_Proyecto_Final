<?php
// =====================================================================
// mis_rutinas.php — Rutinas del socio con sus ejercicios. Se pueden eliminar.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);
$socio = socio_de_usuario($pdo, $usuario['dni']);

$rutinas = [];
if ($socio) {
    $st = $pdo->prepare('SELECT r.id, r.nombre, r.objetivo, e.nombre AS entrenador_nombre, e.apellido AS entrenador_apellido
                         FROM rutinas r LEFT JOIN entrenadores e ON e.id = r.entrenador_id
                         WHERE r.socio_id = :id AND r.archivado_en IS NULL ORDER BY r.creado_en DESC');
    $st->execute([':id' => $socio['id']]);
    $rutinas = $st->fetchAll();

    foreach ($rutinas as &$r) {
        $ej = $pdo->prepare('SELECT ejercicio, series, repeticiones FROM rutina_ejercicios WHERE rutina_id = :id ORDER BY id');
        $ej->execute([':id' => $r['id']]);
        $r['ejercicios'] = $ej->fetchAll();
    }
    unset($r);
}

encabezado('Mis rutinas', $usuario);
?>
<?php if (!$socio): ?>
    <section class="tarjeta">
        <p class="vacio">Todavía no tenés rutinas. Comprá un plan y después elegí rutinas en el Catálogo.</p>
        <a class="boton" href="catalogo_rutinas.php">Ver catálogo</a>
    </section>
<?php elseif (!$rutinas): ?>
    <section class="tarjeta">
        <p class="vacio">Todavía no elegiste ninguna rutina.</p>
        <a class="boton" href="catalogo_rutinas.php">Ir al catálogo</a>
    </section>
<?php endif; ?>

<?php foreach ($rutinas as $r): ?>
<section class="tarjeta">
    <div class="cabecera-seccion">
        <div>
            <h2><?= escapar($r['nombre']) ?></h2>
            <p>
                <?= escapar($r['objetivo'] ?? '') ?>
                <?php if ($r['entrenador_nombre'] !== null): ?>
                    <small>· Entrenador: <?= escapar($r['entrenador_nombre'] . ' ' . $r['entrenador_apellido']) ?></small>
                <?php endif; ?>
            </p>
        </div>
        <?php boton_accion('quitar_mi_rutina.php', ['rutina_id' => $r['id']], 'Eliminar de mis rutinas', 'boton-peligro', '¿Eliminar esta rutina de tu panel?'); ?>
    </div>
    <?php if (!$r['ejercicios']): ?>
        <p class="vacio">Sin ejercicios cargados.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead><tr><th>Ejercicio</th><th>Series</th><th>Repeticiones</th></tr></thead>
            <tbody>
            <?php foreach ($r['ejercicios'] as $ej): ?>
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
</section>
<?php endforeach; ?>
<?php pie();
