<?php
// =====================================================================
// pagar.php — Compra o cambio de plan desde el panel (sin pantalla intermedia).
// Regla: el socio tiene UN solo plan activo. Si compra otro, el vigente se cancela.
// Al confirmar: alta de socio (si no existe), membresía y pago "pagado".
// Solo acepta POST con token CSRF.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';

$usuario = requerir_login($pdo);

if (!es_post()) {
    redirigir('ver_planes.php');
}
exigir_csrf();

$planId = id_valido($_POST['plan_id'] ?? null);
$st = $pdo->prepare('SELECT id, nombre, duracion_meses, precio FROM planes WHERE id = :id AND archivado_en IS NULL');
$st->execute([':id' => $planId]);
$plan = $st->fetch();

if (!$plan) {
    flash('error', 'El plan no existe o ya no está disponible.');
    redirigir('ver_planes.php');
}

$pdo->beginTransaction();   // Todo o nada
try {
    // 1) Socio: se busca por DNI (también archivados) o se da de alta con los datos de la cuenta
    $st = $pdo->prepare('SELECT id, archivado_en FROM socios WHERE dni = :dni');
    $st->execute([':dni' => $usuario['dni']]);
    $socio = $st->fetch();

    if ($socio && $socio['archivado_en'] !== null) {
        $pdo->rollBack();
        flash('error', 'Tu ficha de socio está archivada. Consultá en recepción.');
        redirigir('panel.php');
    }

    if ($socio) {
        $socioId = (int) $socio['id'];
    } else {
        $st = $pdo->prepare('INSERT INTO socios (nombre, apellido, dni, email) VALUES (:nombre, :apellido, :dni, :email)');
        $st->execute([
            ':nombre' => $usuario['nombre'],
            ':apellido' => $usuario['apellido'],
            ':dni' => $usuario['dni'],
            ':email' => $usuario['email'],
        ]);
        $socioId = (int) $pdo->lastInsertId();
    }

    // 2) Un solo plan activo: si ya tiene ESTE plan vigente, no se repite
    $hoy = date('Y-m-d');
    $activa = membresia_activa($pdo, $socioId);
    if ($activa && (int) $activa['plan_id'] === (int) $plan['id']) {
        $pdo->rollBack();
        flash('error', 'Ya tenés activo el plan ' . $plan['nombre'] . ' hasta el ' . formato_fecha($activa['fecha_vencimiento']) . '. Para cambiar, elegí otro plan.');
        redirigir('ver_planes.php');
    }

    // 3) Nueva membresía (cancela la anterior si había otra vigente)
    $membresiaId = asignar_membresia($pdo, $socioId, $plan, $hoy);

    // 4) Pago registrado como pagado
    $st = $pdo->prepare("INSERT INTO pagos (socio_id, membresia_id, monto, fecha_pago, metodo, estado, observaciones)
                         VALUES (:socio, :membresia, :monto, :fecha, 'tarjeta', 'pagado', 'Compra desde el panel')");
    $st->execute([
        ':socio' => $socioId,
        ':membresia' => $membresiaId,
        ':monto' => $plan['precio'],
        ':fecha' => $hoy,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$mensaje = $activa
    ? 'Cambiaste al plan ' . $plan['nombre'] . '. El plan anterior quedó cancelado.'
    : 'Compra registrada. Tu cuota quedó al día con el plan ' . $plan['nombre'] . '.';
flash('ok', $mensaje);
redirigir('mi_membresia.php');
