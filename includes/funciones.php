<?php
// =====================================================================
// funciones.php — Funciones compartidas por todas las páginas:
// sesiones, protección CSRF, control de acceso, validaciones y formatos.
// =====================================================================

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ---------------------------------------------------------------------
// SESIONES
// ---------------------------------------------------------------------

// Inicia la sesión con opciones seguras (la cookie no es accesible desde JavaScript).
function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

// Borra los datos de la sesión y la destruye.
function destruir_sesion(): void
{
    $_SESSION = [];
    session_destroy();
}

iniciar_sesion_segura();

// ---------------------------------------------------------------------
// SEGURIDAD: escapado, CSRF y control de acceso
// ---------------------------------------------------------------------

// Escapa un valor para mostrarlo en HTML (evita inyección de código / XSS).
function escapar($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Escapa los caracteres especiales de LIKE (%, _ y \) para que la búsqueda sea literal.
function patron_like(string $texto): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';
}

function es_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirigir(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}

// Mensajes que se muestran una sola vez (tras una redirección).
function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtener_flash(): ?array
{
    $mensaje = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $mensaje;
}

// Token CSRF: un valor secreto por sesión que todo formulario debe devolver.
// Evita que otro sitio web envíe acciones en nombre del usuario logueado.
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . escapar(token_csrf()) . '">';
}

// Se llama al principio de cada POST. Si el token no coincide, se corta la ejecución.
function exigir_csrf(): void
{
    $recibido = $_POST['csrf'] ?? '';
    if (!is_string($recibido) || !hash_equals(token_csrf(), $recibido)) {
        http_response_code(403);
        die('Solicitud no válida (token de seguridad incorrecto). Volvé atrás, recargá la página e intentá de nuevo.');
    }
}

// Exige sesión iniciada y verifica en la base que la cuenta siga activa.
// Devuelve los datos del usuario actual.
function requerir_login(PDO $pdo): array
{
    if (empty($_SESSION['usuario_id'])) {
        flash('error', 'Tenés que iniciar sesión para ver esa página.');
        redirigir('login.php');
    }

    $st = $pdo->prepare('SELECT id, nombre, apellido, dni, email, rol, creado_en, archivado_en
                         FROM usuarios WHERE id = :id');
    $st->execute([':id' => $_SESSION['usuario_id']]);
    $usuario = $st->fetch();

    // La cuenta pudo ser archivada o borrada mientras la sesión seguía abierta
    if (!$usuario || $usuario['archivado_en'] !== null) {
        destruir_sesion();
        iniciar_sesion_segura();
        flash('error', 'Tu cuenta no está activa. Contactá al administrador.');
        redirigir('login.php');
    }

    $_SESSION['usuario_rol'] = $usuario['rol'];   // Refrescamos el rol desde la base de datos
    return $usuario;
}

// Solo para administradores. Un usuario común es redirigido a su panel.
function requerir_admin(PDO $pdo): array
{
    $usuario = requerir_login($pdo);
    if ($usuario['rol'] !== 'admin') {
        flash('error', 'No tenés permisos para acceder a esa sección.');
        redirigir('panel.php');
    }
    return $usuario;
}

// ---------------------------------------------------------------------
// VALIDACIONES Y FORMATOS
// ---------------------------------------------------------------------

function texto_limpio($valor): string
{
    return trim((string) ($valor ?? ''));
}

// Convierte '' en null (para columnas opcionales).
function nulo_si_vacio(string $texto): ?string
{
    return $texto === '' ? null : $texto;
}

// Devuelve el identificador como entero positivo, o null si no es válido.
function id_valido($valor): ?int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function dni_valido(string $dni): bool
{
    return preg_match('/^\d{7,8}$/', $dni) === 1;
}

function email_valido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function fecha_valida(string $fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}

function formato_fecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
}

function formato_fecha_hora(?string $fecha): string
{
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '—';
}

function formato_dinero($monto): string
{
    return '$ ' . number_format((float) $monto, 2, ',', '.');
}

// Días desde hoy hasta la fecha: positivo = falta, negativo = ya pasó.
function dias_hasta(string $fecha): int
{
    $hoy = new DateTimeImmutable('today');
    return (int) $hoy->diff(new DateTimeImmutable($fecha))->format('%r%a');
}

// Valida datos de personas (usuarios, socios o entrenadores).
// Devuelve una lista de errores; si está vacía, los datos son válidos.
function validar_persona(PDO $pdo, string $tabla, array $d, ?int $ignorarId = null): array
{
    // Solo se aceptan nombres de tabla fijos (nunca texto que venga del usuario)
    if (!in_array($tabla, ['usuarios', 'socios', 'entrenadores'], true)) {
        throw new InvalidArgumentException('Tabla no permitida.');
    }

    $errores = [];
    $esUsuario = $tabla === 'usuarios';

    if ($d['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if ($d['apellido'] === '') {
        $errores[] = 'El apellido es obligatorio.';
    }
    if (!dni_valido($d['dni'])) {
        $errores[] = 'El DNI es obligatorio y debe tener 7 u 8 números, sin puntos.';
    }
    if ($esUsuario && $d['email'] === '') {
        $errores[] = 'El correo electrónico es obligatorio.';
    }
    if ($d['email'] !== '' && !email_valido($d['email'])) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    if (!$errores) {
        // Duplicados: se buscan también entre registros archivados, porque siguen en la base
        $condiciones = ['dni = :dni'];
        $parametros = [':dni' => $d['dni'], ':id' => $ignorarId ?? 0];

        // En usuarios el correo es único (login); en socios y entrenadores no es obligatorio
        if ($esUsuario && $d['email'] !== '') {
            $condiciones[] = 'email = :email';
            $parametros[':email'] = $d['email'];
        }

        $st = $pdo->prepare("SELECT dni, email FROM $tabla
                             WHERE (" . implode(' OR ', $condiciones) . ') AND id <> :id');
        $st->execute($parametros);

        foreach ($st->fetchAll() as $fila) {
            if ($fila['dni'] === $d['dni']) {
                $errores[] = 'Ya existe un registro con ese DNI.';
            }
            if ($esUsuario && strcasecmp((string) $fila['email'], $d['email']) === 0) {
                $errores[] = 'Ya existe una cuenta con ese correo electrónico.';
            }
        }
    }

    return array_values(array_unique($errores));
}

// Valida la contraseña y su confirmación.
function validar_clave(string $clave, string $confirmar): array
{
    $errores = [];
    if (strlen($clave) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($clave !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    return $errores;
}

// Lista [id => "Apellido, Nombre"] de socios o entrenadores activos, para los <select>.
function opciones_personas(PDO $pdo, string $tabla): array
{
    if (!in_array($tabla, ['socios', 'entrenadores'], true)) {
        return [];
    }
    $filas = $pdo->query("SELECT id, nombre, apellido FROM $tabla
                          WHERE archivado_en IS NULL ORDER BY apellido, nombre")->fetchAll();
    $opciones = [];
    foreach ($filas as $f) {
        $opciones[$f['id']] = $f['apellido'] . ', ' . $f['nombre'];
    }
    return $opciones;
}

// ---------------------------------------------------------------------
// REGLAS DEL GIMNASIO
// ---------------------------------------------------------------------

// Cuota al día = la membresía más reciente está vigente Y tiene pagos registrados
// como "pagado" que cubren su precio.
function estado_cuota(PDO $pdo, int $socioId): array
{
    $st = $pdo->prepare('SELECT m.fecha_vencimiento, m.precio,
                                (SELECT COALESCE(SUM(p.monto), 0) FROM pagos p
                                  WHERE p.membresia_id = m.id AND p.estado = :pagado) AS total_pagado
                         FROM membresias m
                         WHERE m.socio_id = :socio AND m.cancelada_en IS NULL
                         ORDER BY m.fecha_vencimiento DESC
                         LIMIT 1');
    $st->execute([':pagado' => 'pagado', ':socio' => $socioId]);
    $membresia = $st->fetch();

    if (!$membresia) {
        return ['al_dia' => false, 'texto' => 'Sin membresía', 'clase' => 'etiqueta-gris'];
    }

    $vigente = $membresia['fecha_vencimiento'] >= date('Y-m-d');
    $pagada = (float) $membresia['total_pagado'] >= (float) $membresia['precio'];

    if (!$vigente) {
        return ['al_dia' => false, 'texto' => 'Vencida el ' . formato_fecha($membresia['fecha_vencimiento']), 'clase' => 'etiqueta-rojo'];
    }
    if (!$pagada) {
        return ['al_dia' => false, 'texto' => 'Pago pendiente', 'clase' => 'etiqueta-amarillo'];
    }
    return ['al_dia' => true, 'texto' => 'Al día · vence ' . formato_fecha($membresia['fecha_vencimiento']), 'clase' => 'etiqueta-verde'];
}

// Membresía activa del socio: no cancelada y con vencimiento hoy o después. Devuelve null si no tiene.
function membresia_activa(PDO $pdo, int $socioId): ?array
{
    $st = $pdo->prepare('SELECT m.id, m.plan_id, m.fecha_vencimiento, p.nombre AS plan_nombre
                         FROM membresias m JOIN planes p ON p.id = m.plan_id
                         WHERE m.socio_id = :socio AND m.cancelada_en IS NULL AND m.fecha_vencimiento >= :hoy
                         ORDER BY m.fecha_vencimiento DESC LIMIT 1');
    $st->execute([':socio' => $socioId, ':hoy' => date('Y-m-d')]);
    return $st->fetch() ?: null;
}

// Asigna un plan a un socio. Un socio solo puede tener UN plan activo:
// si tenía otro vigente, se cancela. Devuelve el id de la nueva membresía.
// Debe usarse dentro de una transacción (la controla quien llama).
function asignar_membresia(PDO $pdo, int $socioId, array $plan, string $fechaInicio): int
{
    $st = $pdo->prepare('UPDATE membresias SET cancelada_en = :ahora
                         WHERE socio_id = :socio AND cancelada_en IS NULL AND fecha_vencimiento >= :hoy');
    $st->execute([':ahora' => date('Y-m-d H:i:s'), ':socio' => $socioId, ':hoy' => date('Y-m-d')]);

    // El vencimiento lo calcula MySQL con DATE_ADD. La duración es un entero tomado de la base.
    $duracion = (int) $plan['duracion_meses'];
    $st = $pdo->prepare("INSERT INTO membresias (socio_id, plan_id, precio, fecha_inicio, fecha_vencimiento)
                         VALUES (:socio, :plan, :precio, :inicio, DATE_ADD(:inicio2, INTERVAL $duracion MONTH))");
    $st->execute([
        ':socio' => $socioId,
        ':plan' => $plan['id'],
        ':precio' => $plan['precio'],
        ':inicio' => $fechaInicio,
        ':inicio2' => $fechaInicio,
    ]);
    return (int) $pdo->lastInsertId();
}

// Ficha de socio activa de la cuenta (se vincula por DNI). Devuelve null si no tiene.
function socio_de_usuario(PDO $pdo, string $dni): ?array
{
    $st = $pdo->prepare('SELECT id, nombre, apellido FROM socios WHERE dni = :dni AND archivado_en IS NULL');
    $st->execute([':dni' => $dni]);
    return $st->fetch() ?: null;
}

// Imagen de un plan o entrenador: busca img/<carpeta>/<id>.(jpg|jpeg|png|webp).
// Devuelve la ruta relativa o null si no hay imagen (en ese caso se muestra un recuadro).
function imagen_de(string $carpeta, int $id): ?string
{
    if (!in_array($carpeta, ['planes', 'entrenadores'], true)) {
        return null;
    }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $ruta = "img/$carpeta/$id.$ext";
        if (is_file(__DIR__ . '/../' . $ruta)) {
            return $ruta;
        }
    }
    return null;
}
