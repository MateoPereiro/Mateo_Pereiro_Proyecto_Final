<?php
// =====================================================================
// ver_entrenadores.php — Entrenadores del gimnasio (solo lectura).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);

$entrenadores = $pdo->query('SELECT id, nombre, apellido, especialidad FROM entrenadores
                             WHERE archivado_en IS NULL ORDER BY apellido, nombre')->fetchAll();

encabezado('Entrenadores', $usuario);
?>
<section class="tarjeta">
    <?php if (!$entrenadores): ?>
        <p class="vacio">No hay entrenadores cargados.</p>
    <?php else: ?>
    <div class="grilla-tarjetas">
        <?php foreach ($entrenadores as $e): ?>
            <article class="tarjeta-info">
                <?php $img = imagen_de('entrenadores', (int) $e['id']); ?>
                <?php if ($img): ?>
                    <img class="foto-img" src="<?= escapar($img) ?>" alt="<?= escapar($e['nombre'] . ' ' . $e['apellido']) ?>">
                <?php else: ?>
                    <div class="foto">Foto del entrenador</div>
                <?php endif; ?>
                <h3><?= escapar($e['nombre'] . ' ' . $e['apellido']) ?></h3>
                <p class="vacio"><?= escapar($e['especialidad'] ?? 'Entrenador') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php pie();
