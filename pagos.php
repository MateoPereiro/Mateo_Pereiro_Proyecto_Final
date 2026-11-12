<?php
// =====================================================================
// pagos.php — Registro de pagos, historial, búsqueda por socio y
// filtros por fecha y estado.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

const METODOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta'];
const ESTADOS = ['pagado' => 'Pagado', 'pendiente' => 'Pendiente'];

$errores = [];
$valores = ['socio_id' => null, 'membresia_id' => null, 'monto' => '', 'fecha_pago' => date('Y-m-d'),
            'metodo' => 'efectivo', 'estado' => 'pagado', 'observaciones' => ''];

// ---------- ACCIONES (POST) ----------
if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? 'registrar';

    // Marcar un pago pendiente como pagado
    if ($accion === 'marcar_pagado') {
        $id = id_valido($_POST['id'] ?? null);
        if ($id !== null) {
            $st = $pdo->prepare("UPDATE pagos SET estado = 'pagado' WHERE id = :id AND estado = 'pendiente'");
            $st->execute([':id' => $id]);
            flash($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'Pago marcado como pagado.' : 'El pago no existe o ya estaba pagado.');
        }
        redirigir('pagos.php');
    }

    // Registrar un pago nuevo
    $valores = [
        'socio_id' => id_valido($_POST['socio_id'] ?? null),
        'membresia_id' => id_valido($_POST['membresia_id'] ?? null),
        'monto' => texto_limpio($_POST['monto'] ?? ''),
        'fecha_pago' => texto_limpio($_POST['fecha_pago'] ?? ''),
        'metodo' => texto_limpio($_POST['metodo'] ?? ''),
        'estado' => texto_limpio($_POST['estado'] ?? ''),
        'observaciones' => texto_limpio($_POST['observaciones'] ?? ''),
    ];

    if ($valores['socio_id'] === null) {
        $errores[] = 'Elegí un socio.';
    }
    if (!is_numeric($valores['monto']) || (float) $valores['monto'] <= 0) {
        $errores[] = 'El monto debe ser un número mayor a cero.';
    }
    if (!fecha_valida($valores['fecha_pago'])) {
        $errores[] = 'La fecha del pago no es válida.';
    }
    if (!isset(METODOS[$valores['metodo']])) {
        $errores[] = 'Elegí un método de pago válido.';
    }
    if (!isset(ESTADOS[$valores['estado']])) {
        $errores[] = 'Elegí un estado válido.';
    }

    // Si se indica una membresía, debe pertenecer al socio elegido
    if (!$errores && $valores['membresia_id'] !== null) {
        $st = $pdo->prepare('SELECT id FROM membresias WHERE id = :id AND socio_id = :socio');
        $st->execute([':id' => $valores['membresia_id'], ':socio' => $valores['socio_id']]);
        if (!$st->fetch()) {
            $errores[] = 'La membresía elegida no corresponde a ese socio.';
        }
    }

    if (!$errores) {
        $st = $pdo->prepare('INSERT INTO pagos (socio_id, membresia_id, monto, fecha_pago, metodo, estado, observaciones)
                             VALUES (:socio, :membresia, :monto, :fecha, :metodo, :estado, :observaciones)');
        $st->execute([
            ':socio' => $valores['socio_id'],
            ':membresia' => $valores['membresia_id'],
            ':monto' => number_format((float) $valores['monto'], 2, '.', ''),
            ':fecha' => $valores['fecha_pago'],
            ':metodo' => $valores['metodo'],
            ':estado' => $valores['estado'],
            ':observaciones' => nulo_si_vacio($valores['observaciones']),
        ]);
        flash('ok', 'Pago registrado correctamente.');
        redirigir('pagos.php');
    }
}

// ---------- HISTORIAL CON FILTROS (GET) ----------
$where = [];
$parametros = [];

$filtroSocio = id_valido($_GET['socio'] ?? null);
if ($filtroSocio !== null) {
    $where[] = 'p.socio_id = :socio';
    $parametros[':socio'] = $filtroSocio;
}

$filtroEstado = $_GET['estado'] ?? '';
if (isset(ESTADOS[$filtroEstado])) {
    $where[] = 'p.estado = :estado';
    $parametros[':estado'] = $filtroEstado;
}

$desde = $_GET['desde'] ?? '';
if (fecha_valida($desde)) {
    $where[] = 'p.fecha_pago >= :desde';
    $parametros[':desde'] = $desde;
}

$hasta = $_GET['hasta'] ?? '';
if (fecha_valida($hasta)) {
    $where[] = 'p.fecha_pago <= :hasta';
    $parametros[':hasta'] = $hasta;
}

$sql = 'SELECT p.id, p.monto, p.fecha_pago, p.metodo, p.estado, p.observaciones,
               s.nombre, s.apellido, pl.nombre AS plan_nombre, m.fecha_vencimiento
        FROM pagos p
        JOIN socios s ON s.id = p.socio_id
        LEFT JOIN membresias m ON m.id = p.membresia_id
        LEFT JOIN planes pl ON pl.id = m.plan_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.fecha_pago DESC, p.id DESC';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$pagos = $st->fetchAll();

$totalCobrado = 0;
foreach ($pagos as $p) {
    if ($p['estado'] === 'pagado') {
        $totalCobrado += (float) $p['monto'];
    }
}

// Opciones para los formularios
$socios = opciones_personas($pdo, 'socios');
$membresias = [];
$filas = $pdo->query('SELECT m.id, m.fecha_vencimiento, s.nombre, s.apellido, pl.nombre AS plan_nombre
                      FROM membresias m
                      JOIN socios s ON s.id = m.socio_id
                      JOIN planes pl ON pl.id = m.plan_id
                      ORDER BY s.apellido, s.nombre, m.fecha_vencimiento DESC')->fetchAll();
foreach ($filas as $f) {
    $membresias[$f['id']] = $f['apellido'] . ', ' . $f['nombre'] . ' · ' . $f['plan_nombre'] . ' (vence ' . formato_fecha($f['fecha_vencimiento']) . ')';
}

encabezado('Pagos', $admin);
mostrar_errores($errores);
?>
<section class="tarjeta">
    <h2>Registrar pago</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="grilla">
            <?php
            select('socio_id', 'Socio', $socios, $valores['socio_id'], ['requerido' => true]);
            select('membresia_id', 'Membresía (opcional)', $membresias, $valores['membresia_id'], ['primera' => 'Sin membresía asociada']);
            campo('monto', 'Monto ($)', $valores['monto'], ['tipo' => 'number', 'requerido' => true, 'min' => 0.01, 'paso' => '0.01']);
            campo('fecha_pago', 'Fecha del pago', $valores['fecha_pago'], ['tipo' => 'date', 'requerido' => true]);
            select('metodo', 'Método', METODOS, $valores['metodo'], ['sin_placeholder' => true]);
            select('estado', 'Estado', ESTADOS, $valores['estado'], ['sin_placeholder' => true]);
            campo('observaciones', 'Observaciones (opcional)', $valores['observaciones'], ['max' => 255]);
            ?>
        </div>
        <div class="acciones"><button type="submit">Registrar pago</button></div>
    </form>
</section>

<section class="tarjeta">
    <h2>Historial de pagos</h2>
    <form method="get" class="buscador">
        <?php select('socio', 'Socio', $socios, $filtroSocio, ['primera' => 'Todos los socios']); ?>
        <?php select('estado', 'Estado', ESTADOS, $filtroEstado, ['primera' => 'Todos los estados']); ?>
        <?php campo('desde', 'Desde', $desde, ['tipo' => 'date']); ?>
        <?php campo('hasta', 'Hasta', $hasta, ['tipo' => 'date']); ?>
        <div class="acciones">
            <button type="submit">Filtrar</button>
            <a class="boton-secundario" href="pagos.php">Limpiar</a>
        </div>
    </form>

    <?php if (!$pagos): ?>
        <p class="vacio">No hay pagos que coincidan con los filtros.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr><th>Fecha</th><th>Socio</th><th>Plan</th><th>Monto</th><th>Método</th><th>Estado</th><th>Observaciones</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= escapar(formato_fecha($p['fecha_pago'])) ?></td>
                    <td><?= escapar($p['apellido'] . ', ' . $p['nombre']) ?></td>
                    <td><?= escapar($p['plan_nombre'] ?? '—') ?></td>
                    <td><?= escapar(formato_dinero($p['monto'])) ?></td>
                    <td><?= escapar(METODOS[$p['metodo']] ?? $p['metodo']) ?></td>
                    <td>
                        <span class="etiqueta <?= $p['estado'] === 'pagado' ? 'etiqueta-verde' : 'etiqueta-amarillo' ?>">
                            <?= escapar(ESTADOS[$p['estado']] ?? $p['estado']) ?>
                        </span>
                    </td>
                    <td><?= escapar($p['observaciones']) ?></td>
                    <td>
                        <?php if ($p['estado'] === 'pendiente'): ?>
                            <?php boton_accion('pagos.php', ['accion' => 'marcar_pagado', 'id' => $p['id']], 'Marcar pagado'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="total">Total cobrado (según filtros): <?= escapar(formato_dinero($totalCobrado)) ?></p>
    <?php endif; ?>
</section>
<?php pie();
