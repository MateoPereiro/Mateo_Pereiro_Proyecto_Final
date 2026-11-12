<?php
// =====================================================================
// entrenadores.php — Entrenadores: alta, edición de especialidades,
// archivado y asignación / quita de alumnos (socios).
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

$errores = [];
$valores = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'telefono' => '', 'especialidad' => ''];
$idEditando = null;

// ---------- ACCIONES (POST) ----------
if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = id_valido($_POST['id'] ?? null);

    if ($accion === 'archivar' || $accion === 'reactivar') {
        if ($id !== null) {
            $archivar = $accion === 'archivar';
            $st = $pdo->prepare('UPDATE entrenadores SET archivado_en = :valor WHERE id = :id AND archivado_en '
                                . ($archivar ? 'IS NULL' : 'IS NOT NULL'));
            $st->execute([':valor' => $archivar ? date('Y-m-d H:i:s') : null, ':id' => $id]);

            if ($archivar) {
                // Un entrenador que ya no trabaja deja sus alumnos sin asignar
                $st = $pdo->prepare('UPDATE socios SET entrenador_id = NULL WHERE entrenador_id = :id');
                $st->execute([':id' => $id]);
                flash('ok', 'Entrenador archivado. Sus alumnos quedaron sin entrenador asignado.');
            } else {
                flash('ok', 'Entrenador reactivado.');
            }
        }
        redirigir('entrenadores.php');
    }

    // Asignar un socio como alumno de este entrenador
    if ($accion === 'asignar_alumno') {
        $socioId = id_valido($_POST['socio_id'] ?? null);
        if ($id !== null && $socioId !== null) {
            $st = $pdo->prepare('UPDATE socios SET entrenador_id = :entrenador
                                 WHERE id = :socio AND archivado_en IS NULL');
            $st->execute([':entrenador' => $id, ':socio' => $socioId]);
            flash($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'Alumno asignado.' : 'No se pudo asignar el socio.');
        } else {
            flash('error', 'Elegí un socio para asignar.');
        }
        redirigir('entrenadores.php?alumnos=' . (int) $id);
    }

    // Quitar un alumno de este entrenador
    if ($accion === 'quitar_alumno') {
        $socioId = id_valido($_POST['socio_id'] ?? null);
        if ($id !== null && $socioId !== null) {
            $st = $pdo->prepare('UPDATE socios SET entrenador_id = NULL
                                 WHERE id = :socio AND entrenador_id = :entrenador');
            $st->execute([':socio' => $socioId, ':entrenador' => $id]);
            flash('ok', 'Alumno quitado del entrenador.');
        }
        redirigir('entrenadores.php?alumnos=' . (int) $id);
    }

    // Alta o edición de entrenador
    if ($accion === 'guardar') {
        $valores = [
            'nombre' => texto_limpio($_POST['nombre'] ?? ''),
            'apellido' => texto_limpio($_POST['apellido'] ?? ''),
            'dni' => texto_limpio($_POST['dni'] ?? ''),
            'email' => mb_strtolower(texto_limpio($_POST['email'] ?? '')),
            'telefono' => texto_limpio($_POST['telefono'] ?? ''),
            'especialidad' => texto_limpio($_POST['especialidad'] ?? ''),
        ];

        $errores = validar_persona($pdo, 'entrenadores', $valores, $id);

        if (!$errores) {
            $datos = [
                ':nombre' => $valores['nombre'],
                ':apellido' => $valores['apellido'],
                ':dni' => $valores['dni'],
                ':email' => nulo_si_vacio($valores['email']),
                ':telefono' => nulo_si_vacio($valores['telefono']),
                ':especialidad' => nulo_si_vacio($valores['especialidad']),
            ];

            if ($id !== null) {
                $datos[':id'] = $id;
                $st = $pdo->prepare('UPDATE entrenadores SET nombre = :nombre, apellido = :apellido, dni = :dni,
                                     email = :email, telefono = :telefono, especialidad = :especialidad
                                     WHERE id = :id');
            } else {
                $st = $pdo->prepare('INSERT INTO entrenadores (nombre, apellido, dni, email, telefono, especialidad)
                                     VALUES (:nombre, :apellido, :dni, :email, :telefono, :especialidad)');
            }
            $st->execute($datos);

            flash('ok', 'Entrenador guardado correctamente.');
            redirigir('entrenadores.php');
        }
        $idEditando = $id;
    }
}

// ---------- Cargar datos según la vista (GET) ----------
if (!es_post() && isset($_GET['editar'])) {
    $idEditando = id_valido($_GET['editar']);
    $st = $pdo->prepare('SELECT nombre, apellido, dni, email, telefono, especialidad
                         FROM entrenadores WHERE id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $idEditando]);
    $fila = $st->fetch();
    if (!$fila) {
        flash('error', 'El entrenador no existe o está archivado.');
        redirigir('entrenadores.php');
    }
    $valores = $fila;
}

// Vista de alumnos de un entrenador
$idAlumnos = id_valido($_GET['alumnos'] ?? null);
$entrenadorAlumnos = null;
$alumnos = [];
$socioDisponibles = [];

if (!es_post() && $idAlumnos !== null) {
    $st = $pdo->prepare('SELECT id, nombre, apellido, especialidad FROM entrenadores
                         WHERE id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $idAlumnos]);
    $entrenadorAlumnos = $st->fetch();

    if (!$entrenadorAlumnos) {
        flash('error', 'El entrenador no existe o está archivado.');
        redirigir('entrenadores.php');
    }

    $st = $pdo->prepare('SELECT id, nombre, apellido, dni FROM socios
                         WHERE entrenador_id = :id AND archivado_en IS NULL ORDER BY apellido, nombre');
    $st->execute([':id' => $idAlumnos]);
    $alumnos = $st->fetchAll();

    // Socios que todavía no son alumnos de este entrenador
    $st = $pdo->prepare('SELECT id, nombre, apellido FROM socios
                         WHERE archivado_en IS NULL AND (entrenador_id IS NULL OR entrenador_id <> :id)
                         ORDER BY apellido, nombre');
    $st->execute([':id' => $idAlumnos]);
    foreach ($st->fetchAll() as $s) {
        $socioDisponibles[$s['id']] = $s['apellido'] . ', ' . $s['nombre'];
    }
}

// ---------- LISTADO ----------
$verArchivados = ($_GET['ver'] ?? '') === 'archivados';
$st = $pdo->prepare('SELECT e.id, e.nombre, e.apellido, e.dni, e.email, e.telefono, e.especialidad,
                            (SELECT COUNT(*) FROM socios s WHERE s.entrenador_id = e.id AND s.archivado_en IS NULL) AS alumnos
                     FROM entrenadores e
                     WHERE e.archivado_en ' . ($verArchivados ? 'IS NOT NULL' : 'IS NULL') . '
                     ORDER BY e.apellido, e.nombre');
$st->execute();
$entrenadores = $st->fetchAll();

encabezado('Entrenadores', $admin);
mostrar_errores($errores);
$modoEdicion = $idEditando !== null;
?>

<?php if ($entrenadorAlumnos !== null): ?>
<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Alumnos de <?= escapar($entrenadorAlumnos['apellido'] . ', ' . $entrenadorAlumnos['nombre']) ?></h2>
        <a class="boton-secundario" href="entrenadores.php">Volver al listado</a>
    </div>

    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="asignar_alumno">
        <input type="hidden" name="id" value="<?= (int) $idAlumnos ?>">
        <div class="grilla">
            <?php select('socio_id', 'Socio a asignar', $socioDisponibles, null, ['requerido' => true]); ?>
        </div>
        <div class="acciones"><button type="submit">Asignar alumno</button></div>
    </form>

    <h2 style="margin-top:1.4rem">Alumnos actuales (<?= count($alumnos) ?>)</h2>
    <?php if (!$alumnos): ?>
        <p class="vacio">Este entrenador todavía no tiene alumnos asignados.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead><tr><th>Apellido y nombre</th><th>DNI</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($alumnos as $a): ?>
                <tr>
                    <td><?= escapar($a['apellido'] . ', ' . $a['nombre']) ?></td>
                    <td><?= escapar($a['dni']) ?></td>
                    <td><?php boton_accion('entrenadores.php', ['accion' => 'quitar_alumno', 'id' => $idAlumnos, 'socio_id' => $a['id']], 'Quitar', 'boton-peligro', '¿Quitar a este alumno del entrenador?'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$verArchivados && $entrenadorAlumnos === null): ?>
<section class="tarjeta">
    <h2><?= $modoEdicion ? 'Editar entrenador' : 'Agregar entrenador' ?></h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <?php if ($modoEdicion): ?><input type="hidden" name="id" value="<?= (int) $idEditando ?>"><?php endif; ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre', $valores['nombre'], ['requerido' => true, 'max' => 60]);
            campo('apellido', 'Apellido', $valores['apellido'], ['requerido' => true, 'max' => 60]);
            campo('dni', 'DNI (sin puntos)', $valores['dni'], ['requerido' => true, 'max' => 8]);
            campo('email', 'Correo electrónico (opcional)', $valores['email'] ?? '', ['tipo' => 'email', 'max' => 120]);
            campo('telefono', 'Teléfono (opcional)', $valores['telefono'] ?? '', ['max' => 30]);
            campo('especialidad', 'Especialidad (ej. musculación, yoga)', $valores['especialidad'] ?? '', ['max' => 100]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Guardar</button>
            <?php if ($modoEdicion): ?><a href="entrenadores.php">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Listado de entrenadores</h2>
        <nav class="pestanas">
            <a href="entrenadores.php" class="<?= !$verArchivados ? 'activa' : '' ?>">Activos</a>
            <a href="entrenadores.php?ver=archivados" class="<?= $verArchivados ? 'activa' : '' ?>">Archivados</a>
        </nav>
    </div>

    <?php if (!$entrenadores): ?>
        <p class="vacio">No hay entrenadores para mostrar.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr><th>Apellido y nombre</th><th>Especialidad</th><th>Contacto</th><th>Foto</th><?php if (!$verArchivados): ?><th>Alumnos</th><?php endif; ?><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($entrenadores as $e): ?>
                <tr>
                    <td><?= escapar($e['apellido'] . ', ' . $e['nombre']) ?><br><small>DNI <?= escapar($e['dni']) ?></small></td>
                    <td><?= escapar($e['especialidad'] ?? '—') ?></td>
                    <td><?= escapar($e['telefono']) ?><br><small><?= escapar($e['email']) ?></small></td>
                    <td>
                        <?php $img = imagen_de('entrenadores', (int) $e['id']); ?>
                        <?php if ($img): ?>
                            <img class="miniatura" src="<?= escapar($img) ?>" alt="<?= escapar($e['nombre'] . ' ' . $e['apellido']) ?>">
                        <?php else: ?>
                            <span class="etiqueta etiqueta-gris">Sin foto</span>
                        <?php endif; ?>
                    </td>
                    <?php if (!$verArchivados): ?><td><?= (int) $e['alumnos'] ?></td><?php endif; ?>
                    <td class="acciones-tabla">
                        <?php if ($verArchivados): ?>
                            <?php boton_accion('entrenadores.php', ['accion' => 'reactivar', 'id' => $e['id']], 'Reactivar'); ?>
                        <?php else: ?>
                            <a class="boton-pequeno" href="entrenadores.php?alumnos=<?= (int) $e['id'] ?>">Alumnos</a>
                            <a class="boton-pequeno" href="entrenadores.php?editar=<?= (int) $e['id'] ?>">Editar</a>
                            <?php boton_accion('entrenadores.php', ['accion' => 'archivar', 'id' => $e['id']], 'Archivar', 'boton-peligro', '¿Archivar este entrenador? Sus alumnos quedarán sin entrenador.'); ?>
                            <form method="post" action="subir_imagen.php" enctype="multipart/form-data" class="en-linea subir-foto">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="carpeta" value="entrenadores">
                                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                                <button type="submit" class="boton-pequeno">Subir foto</button>
                            </form>
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
