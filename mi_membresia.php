<?php
// =====================================================================
// mi_membresia.php — Estado de la cuota, plan actual e historial de pagos.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);
$socio = socio_de_usuario($pdo, $usuario['dni']);

$cuota = null;
$membresia = null;
$pagos = [];

if ($socio) {
    $socioId = (int) $socio['id'];
    $cuota = estado_cuota($pdo, $socioId);

    $st = $pdo->prepare('SELECT m.fecha_vencimiento, p.nombre AS plan_nombre
                         FROM membresias m JOIN planes p ON p.id = m.plan_id
                         WHERE m.socio_id = :id AND m.cancelada_en IS NULL
                         ORDER BY m.fecha_vencimiento DESC LIMIT 1');
    $st->execute([':id' => $socioId]);
    $membresia = $st->fetch();

    $st = $pdo->prepare('SELECT fecha_pago, monto, metodo, estado, observaciones FROM pagos
                         WHERE socio_id = :id ORDER BY fecha_pago DESC, id DESC');
    $st->execute([':id' => $socioId]);
    $pagos = $st->fetchAll();
}

encabezado('Mi membresía', $usuario);
?>
<?php if (!$socio): ?>
    <section class="tarjeta">
        <p class="vacio">Todavía no tenés una membresía. Elegí un plan en la sección Planes para empezar.</p>
        <a class="boton" href="ver_planes.php">Ver planes</a>
    </section>
<?php else: ?>
    <section class="tarjeta">
        <h2>Estado actual</h2>
        <dl class="datos">
            <dt>Estado de la cuota</dt>
            <dd><span class="etiqueta <?= escapar($cuota['clase']) ?>"><?= escapar($cuota['texto']) ?></span></dd>
            <dt>Plan actual</dt>
            <dd><?= $membresia ? escapar($membresia['plan_nombre']) . ' · vence el ' . escapar(formato_fecha($membresia['fecha_vencimiento'])) : 'Sin plan activo' ?></dd>
        </dl>
    </section>

    <section class="tarjeta">
        <h2>Mis pagos</h2>
        <?php if (!$pagos): ?>
            <p class="vacio">Todavía no registraste pagos.</p>
        <?php else: ?>
        <div class="tabla-responsive">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Estado</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($pagos as $pg): ?>
                    <tr>
                        <td><?= escapar(formato_fecha($pg['fecha_pago'])) ?></td>
                        <td><?= escapar(formato_dinero($pg['monto'])) ?></td>
                        <td><?= escapar(ucfirst($pg['metodo'])) ?></td>
                        <td><span class="etiqueta <?= $pg['estado'] === 'pagado' ? 'etiqueta-verde' : 'etiqueta-amarillo' ?>"><?= escapar(ucfirst($pg['estado'])) ?></span></td>
                        <td><?= escapar($pg['observaciones'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php pie();
