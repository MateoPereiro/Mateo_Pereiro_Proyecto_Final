<?php
// =====================================================================
// socios.php — Socios del gimnasio: alta, edición, archivado, búsqueda
// por nombre/apellido/DNI y estado de la cuota (al día o no).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

$errores = [];
$valores = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'telefono' => '', 'entrenador_id' => null];
$idEditando = null;   // null = alta de un socio nuevo

// ---------- ACCIONES (POST) ----------
if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = id_valido($_POST['id'] ?? null);

    // Archivar / reactivar: no se borra nada, solo cambia la fecha de archivado
    if ($accion === 'archivar' || $accion === 'reactivar') {
        if ($id !== null) {
            $archivar = $accion === 'archivar';
            $st = $pdo->prepare('UPDATE socios SET archivado_en = :valor WHERE id = :id AND archivado_en '
                                . ($archivar ? 'IS NULL' : 'IS NOT NULL'));
            $st->execute([':valor' => $archivar ? date('Y-m-d H:i:s') : null, ':id' => $id]);
            flash('ok', $archivar ? 'Socio archivado.' : 'Socio reactivado.');
        }
        redirigir('socios.php');
    }

    // Guardar alta o edición
    if ($accion === 'guardar') {
        $valores = [
            'nombre' => texto_limpio($_POST['nombre'] ?? ''),
            'apellido' => texto_limpio($_POST['apellido'] ?? ''),
            'dni' => texto_limpio($_POST['dni'] ?? ''),
            'email' => mb_strtolower(texto_limpio($_POST['email'] ?? '')),
            'telefono' => texto_limpio($_POST['telefono'] ?? ''),
            'entrenador_id' => id_valido($_POST['entrenador_id'] ?? null),
        ];

        $errores = validar_persona($pdo, 'socios', $valores, $id);

        if (!$errores) {
            $datos = [
                ':nombre' => $valores['nombre'],
                ':apellido' => $valores['apellido'],
                ':dni' => $valores['dni'],
                ':email' => nulo_si_vacio($valores['email']),
                ':telefono' => nulo_si_vacio($valores['telefono']),
                ':entrenador_id' => $valores['entrenador_id'],
            ];

            if ($id !== null) {
                $datos[':id'] = $id;
                $st = $pdo->prepare('UPDATE socios SET nombre = :nombre, apellido = :apellido, dni = :dni,
                                     email = :email, telefono = :telefono, entrenador_id = :entrenador_id
                                     WHERE id = :id');
            } else {
                $st = $pdo->prepare('INSERT INTO socios (nombre, apellido, dni, email, telefono, entrenador_id)
                                     VALUES (:nombre, :apellido, :dni, :email, :telefono, :entrenador_id)');
            }
            $st->execute($datos);

            flash('ok', 'Socio guardado correctamente.');
            redirigir('socios.php');
        }
        $idEditando = $id;   // Si hubo errores, seguimos en el mismo modo (alta o edición)
    }
}

// ---------- Cargar un socio para editar (GET) ----------
if (!es_post() && isset($_GET['editar'])) {
    $idEditando = id_valido($_GET['editar']);
    $st = $pdo->prepare('SELECT nombre, apellido, dni, email, telefono, entrenador_id
                         FROM socios WHERE id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $idEditando]);
    $fila = $st->fetch();

    if (!$fila) {
        flash('error', 'El socio no existe o está archivado.');
        redirigir('socios.php');
    }
    $valores = $fila;
}

// ---------- LISTADO con búsqueda ----------
$verArchivados = ($_GET['ver'] ?? '') === 'archivados';
$buscar = texto_limpio($_GET['buscar'] ?? '');

$sql = 'SELECT s.id, s.nombre, s.apellido, s.dni, s.email, s.telefono,
               e.nombre AS entrenador_nombre, e.apellido AS entrenador_apellido
        FROM socios s
        LEFT JOIN entrenadores e ON e.id = s.entrenador_id
        WHERE s.archivado_en ' . ($verArchivados ? 'IS NOT NULL' : 'IS NULL');
$parametros = [];

if ($buscar !== '') {
    $sql .= ' AND (s.nombre LIKE :nombre OR s.apellido LIKE :apellido OR s.dni LIKE :dni)';
    $patron = patron_like($buscar);
    $parametros = [':nombre' => $patron, ':apellido' => $patron, ':dni' => $patron];
}
$sql .= ' ORDER BY s.apellido, s.nombre';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$socios = $st->fetchAll();

encabezado('Socios', $admin);
mostrar_errores($errores);

$modoEdicion = $idEditando !== null;
?>

<?php if (!$verArchivados): ?>
<section class="tarjeta">
    <h2><?= $modoEdicion ? 'Editar socio' : 'Registrar nuevo socio' ?></h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <?php if ($modoEdicion): ?>
            <input type="hidden" name="id" value="<?= (int) $idEditando ?>">
        <?php endif; ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre', $valores['nombre'], ['requerido' => true, 'max' => 60]);
            campo('apellido', 'Apellido', $valores['apellido'], ['requerido' => true, 'max' => 60]);
            campo('dni', 'DNI (sin puntos)', $valores['dni'], ['requerido' => true, 'max' => 8]);
            campo('email', 'Correo electrónico (opcional)', $valores['email'] ?? '', ['tipo' => 'email', 'max' => 120]);
            campo('telefono', 'Teléfono (opcional)', $valores['telefono'] ?? '', ['max' => 30]);
            select('entrenador_id', 'Entrenador asignado', opciones_personas($pdo, 'entrenadores'),
                   $valores['entrenador_id'] ?? null, ['primera' => 'Sin entrenador']);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Guardar</button>
            <?php if ($modoEdicion): ?>
                <a href="socios.php">Cancelar edición</a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Listado de socios</h2>
        <nav class="pestanas">
            <a href="socios.php" class="<?= !$verArchivados ? 'activa' : '' ?>">Activos</a>
            <a href="socios.php?ver=archivados" class="<?= $verArchivados ? 'activa' : '' ?>">Archivados</a>
        </nav>
    </div>

    <form method="get" class="buscador">
        <?php if ($verArchivados): ?><input type="hidden" name="ver" value="archivados"><?php endif; ?>
        <input type="search" name="buscar" value="<?= escapar($buscar) ?>" maxlength="100"
               placeholder="Nombre, apellido o DNI">
        <button type="submit">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a class="boton-secundario" href="socios.php<?= $verArchivados ? '?ver=archivados' : '' ?>">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (!$socios): ?>
        <p class="vacio">No hay socios para mostrar.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Apellido y nombre</th>
                    <th>DNI</th>
                    <th>Contacto</th>
                    <th>Entrenador</th>
                    <?php if (!$verArchivados): ?><th>Cuota</th><?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($socios as $s): ?>
                <?php $cuota = $verArchivados ? null : estado_cuota($pdo, (int) $s['id']); ?>
                <tr>
                    <td><?= escapar($s['apellido'] . ', ' . $s['nombre']) ?></td>
                    <td><?= escapar($s['dni']) ?></td>
                    <td><?= escapar($s['telefono']) ?><br><small><?= escapar($s['email']) ?></small></td>
                    <td><?= $s['entrenador_nombre'] !== null ? escapar($s['entrenador_apellido'] . ', ' . $s['entrenador_nombre']) : '—' ?></td>
                    <?php if ($cuota !== null): ?>
                        <td><span class="etiqueta <?= escapar($cuota['clase']) ?>"><?= escapar($cuota['texto']) ?></span></td>
                    <?php endif; ?>
                    <td class="acciones-tabla">
                        <?php if ($verArchivados): ?>
                            <?php boton_accion('socios.php', ['accion' => 'reactivar', 'id' => $s['id']], 'Reactivar'); ?>
                        <?php else: ?>
                            <a class="boton-pequeno" href="socios.php?editar=<?= (int) $s['id'] ?>">Editar</a>
                            <?php boton_accion('socios.php', ['accion' => 'archivar', 'id' => $s['id']], 'Archivar', 'boton-peligro', '¿Archivar este socio?'); ?>
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
