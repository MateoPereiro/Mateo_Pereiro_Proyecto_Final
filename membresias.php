<?php
// =====================================================================
// membresias.php — Asigna un plan a un socio y muestra cuándo vence
// cada membresía y si está pagada.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

$errores = [];
$valores = ['socio_id' => null, 'plan_id' => null, 'fecha_inicio' => date('Y-m-d')];

// ---------- ASIGNAR PLAN A UN SOCIO ----------
if (es_post()) {
    exigir_csrf();

    $valores = [
        'socio_id' => id_valido($_POST['socio_id'] ?? null),
        'plan_id' => id_valido($_POST['plan_id'] ?? null),
        'fecha_inicio' => texto_limpio($_POST['fecha_inicio'] ?? ''),
    ];

    if ($valores['socio_id'] === null) {
        $errores[] = 'Elegí un socio.';
    }
    if ($valores['plan_id'] === null) {
        $errores[] = 'Elegí un plan.';
    }
    if (!fecha_valida($valores['fecha_inicio'])) {
        $errores[] = 'La fecha de inicio no es válida.';
    }

    if (!$errores) {
        // Socio y plan deben estar activos
        $st = $pdo->prepare('SELECT id FROM socios WHERE id = :id AND archivado_en IS NULL');
        $st->execute([':id' => $valores['socio_id']]);
        if (!$st->fetch()) {
            $errores[] = 'El socio elegido no existe o está archivado.';
        }

        $st = $pdo->prepare('SELECT id, precio, duracion_meses FROM planes WHERE id = :id AND archivado_en IS NULL');
        $st->execute([':id' => $valores['plan_id']]);
        $plan = $st->fetch();
        if (!$plan) {
            $errores[] = 'El plan elegido no existe o está archivado.';
        }
    }

    if (!$errores) {
        // Un socio solo puede tener un plan activo: no se repite el mismo plan vigente
        $activa = membresia_activa($pdo, (int) $valores['socio_id']);
        if ($activa && (int) $activa['plan_id'] === (int) $valores['plan_id']) {
            $errores[] = 'El socio ya tiene este plan activo hasta el ' . formato_fecha($activa['fecha_vencimiento']) . '.';
        }
    }

    if (!$errores) {
        // El plan vigente anterior (si lo había) se cancela automáticamente
        $pdo->beginTransaction();
        asignar_membresia($pdo, (int) $valores['socio_id'], $plan, $valores['fecha_inicio']);
        $pdo->commit();

        flash('ok', 'Plan asignado. Registrá el pago para que la cuota quede al día.');
        redirigir('membresias.php');
    }
}

// ---------- LISTADO DE MEMBRESÍAS ----------
$filtroSocio = id_valido($_GET['socio'] ?? null);
$sql = 'SELECT m.id, m.precio, m.fecha_inicio, m.fecha_vencimiento, m.cancelada_en,
               s.nombre, s.apellido, s.dni,
               p.nombre AS plan_nombre,
               (SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg
                 WHERE pg.membresia_id = m.id AND pg.estado = :pagado) AS total_pagado
        FROM membresias m
        JOIN socios s ON s.id = m.socio_id
        JOIN planes p ON p.id = m.plan_id';
$parametros = [':pagado' => 'pagado'];

if ($filtroSocio !== null) {
    $sql .= ' WHERE m.socio_id = :socio';
    $parametros[':socio'] = $filtroSocio;
}
$sql .= ' ORDER BY m.fecha_vencimiento ASC';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$membresias = $st->fetchAll();

// Socios y planes activos para el formulario
$socios = opciones_personas($pdo, 'socios');
$planes = [];
foreach ($pdo->query('SELECT id, nombre, precio FROM planes WHERE archivado_en IS NULL ORDER BY duracion_meses')->fetchAll() as $p) {
    $planes[$p['id']] = $p['nombre'] . ' · ' . formato_dinero($p['precio']);
}

encabezado('Membresías', $admin);
mostrar_errores($errores);
?>
<section class="tarjeta">
    <h2>Asignar plan a un socio</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="grilla">
            <?php
            select('socio_id', 'Socio', $socios, $valores['socio_id'], ['requerido' => true]);
            select('plan_id', 'Plan', $planes, $valores['plan_id'], ['requerido' => true]);
            campo('fecha_inicio', 'Fecha de inicio', $valores['fecha_inicio'], ['tipo' => 'date', 'requerido' => true]);
            ?>
        </div>
        <div class="acciones"><button type="submit">Asignar plan</button></div>
    </form>
</section>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Membresías asignadas<?= $filtroSocio ? ' (filtradas por socio)' : '' ?></h2>
        <?php if ($filtroSocio): ?><a class="boton-secundario" href="membresias.php">Ver todas</a><?php endif; ?>
    </div>

    <?php if (!$membresias): ?>
        <p class="vacio">Todavía no hay membresías asignadas.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr><th>Socio</th><th>Plan</th><th>Inicio</th><th>Vencimiento</th><th>Estado</th><th>Pago</th></tr>
            </thead>
            <tbody>
            <?php foreach ($membresias as $m): ?>
                <?php
                $dias = dias_hasta($m['fecha_vencimiento']);
                if ($m['cancelada_en'] !== null) {
                    $estado = ['texto' => 'Cancelada (cambió de plan)', 'clase' => 'etiqueta-gris'];
                } elseif ($dias > 0) {
                    $estado = ['texto' => "Vigente · vence en $dias días", 'clase' => $dias <= 7 ? 'etiqueta-amarillo' : 'etiqueta-verde'];
                } elseif ($dias === 0) {
                    $estado = ['texto' => 'Vence hoy', 'clase' => 'etiqueta-amarillo'];
                } else {
                    $estado = ['texto' => 'Vencida hace ' . abs($dias) . ' días', 'clase' => 'etiqueta-rojo'];
                }
                $pagada = (float) $m['total_pagado'] >= (float) $m['precio'];
                ?>
                <tr>
                    <td><?= escapar($m['apellido'] . ', ' . $m['nombre']) ?><br><small>DNI <?= escapar($m['dni']) ?></small></td>
                    <td><?= escapar($m['plan_nombre']) ?></td>
                    <td><?= escapar(formato_fecha($m['fecha_inicio'])) ?></td>
                    <td><?= escapar(formato_fecha($m['fecha_vencimiento'])) ?></td>
                    <td><span class="etiqueta <?= $estado['clase'] ?>"><?= escapar($estado['texto']) ?></span></td>
                    <td>
                        <?php if ($pagada): ?>
                            <span class="etiqueta etiqueta-verde">Pagada</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta-amarillo">Pagado <?= escapar(formato_dinero($m['total_pagado'])) ?> de <?= escapar(formato_dinero($m['precio'])) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php pie();
