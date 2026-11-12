<?php
// =====================================================================
// ver_planes.php — Planes disponibles. Comprar o cambiar de plan (un solo plan activo).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);
$socio = socio_de_usuario($pdo, $usuario['dni']);
$planActivo = $socio ? membresia_activa($pdo, (int) $socio['id']) : null;
$planActivoId = $planActivo ? (int) $planActivo['plan_id'] : null;

$planes = $pdo->query('SELECT id, nombre, duracion_meses, precio, descripcion FROM planes
                       WHERE archivado_en IS NULL ORDER BY duracion_meses, precio')->fetchAll();

encabezado('Planes', $usuario);
?>
<section class="tarjeta">
    <p class="vacio">Tenés un solo plan activo. Si comprás otro, el que tenés se cancela y queda el nuevo.</p>
    <?php if (!$planes): ?>
        <p class="vacio">No hay planes cargados.</p>
    <?php else: ?>
    <div class="grilla-tarjetas">
        <?php foreach ($planes as $p): ?>
            <article class="tarjeta-info">
                <?php $img = imagen_de('planes', (int) $p['id']); ?>
                <?php if ($img): ?>
                    <img class="foto-img" src="<?= escapar($img) ?>" alt="<?= escapar($p['nombre']) ?>">
                <?php else: ?>
                    <div class="foto">Imagen del plan</div>
                <?php endif; ?>
                <h3><?= escapar($p['nombre']) ?></h3>
                <p class="precio"><?= escapar(formato_dinero($p['precio'])) ?></p>
                <p><?= (int) $p['duracion_meses'] === 1 ? '1 mes' : (int) $p['duracion_meses'] . ' meses' ?></p>
                <p class="vacio"><?= escapar($p['descripcion'] ?? '') ?></p>
                <?php if ($planActivoId === (int) $p['id']): ?>
                    <p style="margin-top:.6rem"><span class="etiqueta etiqueta-verde">Tu plan actual</span></p>
                <?php else: ?>
                <form method="post" action="pagar.php" style="margin-top:.6rem"
                      <?= $planActivoId ? 'onsubmit="return confirm(\'Al cambiar de plan, el plan actual se cancela. ¿Continuar?\');"' : '' ?>>
                    <?= campo_csrf() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit"><?= $planActivoId ? 'Cambiar a este plan' : 'Comprar plan' ?></button>
                </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php pie();
