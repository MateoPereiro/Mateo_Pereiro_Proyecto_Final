<?php
// =====================================================================
// rutinas.php — Rutinas de entrenamiento: crear, asignar a un socio,
// editar ejercicios (series y repeticiones) y archivar rutinas anteriores.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

// Valida un ejercicio: nombre obligatorio, series 1–20 y repeticiones 1–500
function validar_ejercicio(array $e): array
{
    $errores = [];
    if ($e['ejercicio'] === '') {
        $errores[] = 'El nombre del ejercicio es obligatorio.';
    }
    if ($e['series'] === null || $e['series'] > 20) {
        $errores[] = 'Las series deben ser un número entre 1 y 20.';
    }
    if ($e['repeticiones'] === null || $e['repeticiones'] > 500) {
        $errores[] = 'Las repeticiones deben ser un número entre 1 y 500.';
    }
    return $errores;
}

function leer_ejercicio_post(): array
{
    return [
        'ejercicio' => texto_limpio($_POST['ejercicio'] ?? ''),
        'series' => id_valido($_POST['series'] ?? null),
        'repeticiones' => id_valido($_POST['repeticiones'] ?? null),
    ];
}

$errores = [];
$valores = ['nombre' => '', 'objetivo' => '', 'socio_id' => null, 'entrenador_id' => null];
$idEditando = null;

// ---------- ACCIONES (POST) ----------
if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = id_valido($_POST['id'] ?? null);

    // Archivar / reactivar rutinas (no se borran)
    if ($accion === 'archivar' || $accion === 'reactivar') {
        if ($id !== null) {
            $archivar = $accion === 'archivar';
            $st = $pdo->prepare('UPDATE rutinas SET archivado_en = :valor WHERE id = :id AND archivado_en '
                                . ($archivar ? 'IS NULL' : 'IS NOT NULL'));
            $st->execute([':valor' => $archivar ? date('Y-m-d H:i:s') : null, ':id' => $id]);
            flash('ok', $archivar ? 'Rutina archivada.' : 'Rutina reactivada.');
        }
        redirigir('rutinas.php');
    }

    // Alta o edición de la cabecera de la rutina
    if ($accion === 'guardar') {
        $valores = [
            'nombre' => texto_limpio($_POST['nombre'] ?? ''),
            'objetivo' => texto_limpio($_POST['objetivo'] ?? ''),
            'socio_id' => id_valido($_POST['socio_id'] ?? null),
            'entrenador_id' => id_valido($_POST['entrenador_id'] ?? null),
        ];

        if ($valores['nombre'] === '') {
            $errores[] = 'El nombre de la rutina es obligatorio.';
        }
        // Sin socio = rutina del catálogo general (la pueden elegir los usuarios)
        if ($valores['socio_id'] !== null) {
            $st = $pdo->prepare('SELECT id FROM socios WHERE id = :id AND archivado_en IS NULL');
            $st->execute([':id' => $valores['socio_id']]);
            if (!$st->fetch()) {
                $errores[] = 'El socio elegido no existe o está archivado.';
            }
        }

        if (!$errores) {
            $datos = [
                ':nombre' => $valores['nombre'],
                ':objetivo' => nulo_si_vacio($valores['objetivo']),
                ':socio' => $valores['socio_id'],
                ':entrenador' => $valores['entrenador_id'],
            ];

            if ($id !== null) {
                $datos[':id'] = $id;
                $st = $pdo->prepare('UPDATE rutinas SET nombre = :nombre, objetivo = :objetivo,
                                     socio_id = :socio, entrenador_id = :entrenador
                                     WHERE id = :id AND archivado_en IS NULL');
                $st->execute($datos);
                flash('ok', 'Rutina actualizada.');
                redirigir('rutinas.php');
            }

            $st = $pdo->prepare('INSERT INTO rutinas (nombre, objetivo, socio_id, entrenador_id)
                                 VALUES (:nombre, :objetivo, :socio, :entrenador)');
            $st->execute($datos);
            $nuevoId = (int) $pdo->lastInsertId();

            // Después de crear, se abre la rutina para cargar los ejercicios
            flash('ok', 'Rutina creada. Ahora podés agregar los ejercicios.');
            redirigir('rutinas.php?editar=' . $nuevoId);
        }
        $idEditando = $id;
    }

    // Agregar, modificar o quitar ejercicios de una rutina
    if (in_array($accion, ['agregar_ejercicio', 'guardar_ejercicio', 'quitar_ejercicio'], true)) {
        $st = $pdo->prepare('SELECT id FROM rutinas WHERE id = :id AND archivado_en IS NULL');
        $st->execute([':id' => $id]);
        if (!$st->fetch()) {
            flash('error', 'La rutina no existe o está archivada.');
            redirigir('rutinas.php');
        }

        $destino = 'rutinas.php?editar=' . $id;
        $ejercicioId = id_valido($_POST['ejercicio_id'] ?? null);

        if ($accion === 'quitar_ejercicio') {
            // La condición rutina_id protege que solo se borre un ejercicio de ESTA rutina
            $st = $pdo->prepare('DELETE FROM rutina_ejercicios WHERE id = :ej AND rutina_id = :rutina');
            $st->execute([':ej' => $ejercicioId, ':rutina' => $id]);
            flash('ok', 'Ejercicio quitado de la rutina.');
            redirigir($destino);
        }

        $datosEj = leer_ejercicio_post();
        $erroresEj = validar_ejercicio($datosEj);
        if ($erroresEj) {
            flash('error', implode(' ', $erroresEj));
            redirigir($destino);
        }

        if ($accion === 'agregar_ejercicio') {
            $st = $pdo->prepare('INSERT INTO rutina_ejercicios (rutina_id, ejercicio, series, repeticiones)
                                 VALUES (:rutina, :ejercicio, :series, :repeticiones)');
            $st->execute([
                ':rutina' => $id,
                ':ejercicio' => $datosEj['ejercicio'],
                ':series' => $datosEj['series'],
                ':repeticiones' => $datosEj['repeticiones'],
            ]);
            flash('ok', 'Ejercicio agregado.');
        } else {
            $st = $pdo->prepare('UPDATE rutina_ejercicios SET ejercicio = :ejercicio, series = :series,
                                 repeticiones = :repeticiones WHERE id = :ej AND rutina_id = :rutina');
            $st->execute([
                ':ejercicio' => $datosEj['ejercicio'],
                ':series' => $datosEj['series'],
                ':repeticiones' => $datosEj['repeticiones'],
                ':ej' => $ejercicioId,
                ':rutina' => $id,
            ]);
            flash('ok', 'Ejercicio actualizado.');
        }
        redirigir($destino);
    }
}

// ---------- Cargar una rutina para editar (GET) ----------
if (!es_post() && isset($_GET['editar'])) {
    $idEditando = id_valido($_GET['editar']);
    $st = $pdo->prepare('SELECT nombre, objetivo, socio_id, entrenador_id FROM rutinas
                         WHERE id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $idEditando]);
    $fila = $st->fetch();
    if (!$fila) {
        flash('error', 'La rutina no existe o está archivada.');
        redirigir('rutinas.php');
    }
    $valores = $fila;
}

// Ejercicios de la rutina que se está editando
$ejercicios = [];
if ($idEditando !== null) {
    $st = $pdo->prepare('SELECT id, ejercicio, series, repeticiones FROM rutina_ejercicios
                         WHERE rutina_id = :id ORDER BY id');
    $st->execute([':id' => $idEditando]);
    $ejercicios = $st->fetchAll();
}

// ---------- LISTADO (con filtro por socio) ----------
$verArchivadas = ($_GET['ver'] ?? '') === 'archivadas';
$filtroSocio = id_valido($_GET['socio'] ?? null);

$where = ['r.archivado_en ' . ($verArchivadas ? 'IS NOT NULL' : 'IS NULL')];
$parametros = [];
if ($filtroSocio !== null) {
    $where[] = 'r.socio_id = :socio';
    $parametros[':socio'] = $filtroSocio;
}

$st = $pdo->prepare('SELECT r.id, r.nombre, r.objetivo,
                            s.nombre AS socio_nombre, s.apellido AS socio_apellido,
                            e.nombre AS entrenador_nombre, e.apellido AS entrenador_apellido,
                            (SELECT COUNT(*) FROM rutina_ejercicios re WHERE re.rutina_id = r.id) AS cantidad
                     FROM rutinas r
                     LEFT JOIN socios s ON s.id = r.socio_id
                     LEFT JOIN entrenadores e ON e.id = r.entrenador_id
                     WHERE ' . implode(' AND ', $where) . '
                     ORDER BY r.creado_en DESC');
$st->execute($parametros);
$rutinas = $st->fetchAll();

$socios = opciones_personas($pdo, 'socios');
$entrenadores = opciones_personas($pdo, 'entrenadores');

encabezado('Rutinas de entrenamiento', $admin);
mostrar_errores($errores);
$modoEdicion = $idEditando !== null;
?>

<?php if (!$verArchivadas): ?>
<section class="tarjeta">
    <h2><?= $modoEdicion ? 'Editar rutina' : 'Crear rutina' ?></h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <?php if ($modoEdicion): ?><input type="hidden" name="id" value="<?= (int) $idEditando ?>"><?php endif; ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre de la rutina', $valores['nombre'], ['requerido' => true, 'max' => 100]);
            campo('objetivo', 'Objetivo (opcional)', $valores['objetivo'] ?? '', ['max' => 255]);
            select('socio_id', 'Socio asignado', $socios, $valores['socio_id'], ['primera' => 'Catálogo general (sin socio)']);
            select('entrenador_id', 'Entrenador (opcional)', $entrenadores, $valores['entrenador_id'], ['primera' => 'Sin entrenador']);
            ?>
        </div>
        <div class="acciones">
            <button type="submit"><?= $modoEdicion ? 'Guardar cambios' : 'Crear rutina' ?></button>
            <?php if ($modoEdicion): ?><a href="rutinas.php">Cerrar edición</a><?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($modoEdicion): ?>
<section class="tarjeta">
    <h2>Ejercicios de la rutina</h2>

    <?php if (!$ejercicios): ?>
        <p class="vacio">Todavía no hay ejercicios cargados.</p>
    <?php endif; ?>

    <?php foreach ($ejercicios as $ej): ?>
        <form method="post" class="fila-ejercicio">
            <?= campo_csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $idEditando ?>">
            <input type="hidden" name="ejercicio_id" value="<?= (int) $ej['id'] ?>">
            <?php
            campo('ejercicio', 'Ejercicio', $ej['ejercicio'], ['requerido' => true, 'max' => 100]);
            campo('series', 'Series', $ej['series'], ['tipo' => 'number', 'requerido' => true, 'min' => 1]);
            campo('repeticiones', 'Repeticiones', $ej['repeticiones'], ['tipo' => 'number', 'requerido' => true, 'min' => 1]);
            ?>
            <div class="acciones-tabla">
                <button type="submit" name="accion" value="guardar_ejercicio">Guardar</button>
                <button type="submit" name="accion" value="quitar_ejercicio" class="boton-peligro"
                        onclick="return confirm('¿Quitar este ejercicio de la rutina?');">Quitar</button>
            </div>
        </form>
    <?php endforeach; ?>

    <h2 style="margin-top:1.4rem">Agregar ejercicio</h2>
    <form method="post" class="fila-ejercicio">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_ejercicio">
        <input type="hidden" name="id" value="<?= (int) $idEditando ?>">
        <?php
        campo('ejercicio', 'Ejercicio', '', ['requerido' => true, 'max' => 100]);
        campo('series', 'Series', '', ['tipo' => 'number', 'requerido' => true, 'min' => 1]);
        campo('repeticiones', 'Repeticiones', '', ['tipo' => 'number', 'requerido' => true, 'min' => 1]);
        ?>
        <div class="acciones-tabla"><button type="submit">Agregar</button></div>
    </form>
</section>
<?php endif; ?>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Listado de rutinas</h2>
        <nav class="pestanas">
            <a href="rutinas.php" class="<?= !$verArchivadas ? 'activa' : '' ?>">Activas</a>
            <a href="rutinas.php?ver=archivadas" class="<?= $verArchivadas ? 'activa' : '' ?>">Archivadas</a>
        </nav>
    </div>

    <form method="get" class="buscador">
        <?php if ($verArchivadas): ?><input type="hidden" name="ver" value="archivadas"><?php endif; ?>
        <?php select('socio', 'Ver rutinas de', $socios, $filtroSocio, ['primera' => 'Todos los socios']); ?>
        <div class="acciones"><button type="submit">Filtrar</button></div>
    </form>

    <?php if (!$rutinas): ?>
        <p class="vacio">No hay rutinas para mostrar.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead><tr><th>Rutina</th><th>Socio</th><th>Entrenador</th><th>Ejercicios</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rutinas as $r): ?>
                <tr>
                    <td><?= escapar($r['nombre']) ?><br><small><?= escapar($r['objetivo']) ?></small></td>
                    <td><?= $r['socio_nombre'] !== null ? escapar($r['socio_apellido'] . ', ' . $r['socio_nombre']) : '<span class="etiqueta etiqueta-gris">Catálogo general</span>' ?></td>
                    <td><?= $r['entrenador_nombre'] !== null ? escapar($r['entrenador_apellido'] . ', ' . $r['entrenador_nombre']) : '—' ?></td>
                    <td><?= (int) $r['cantidad'] ?></td>
                    <td class="acciones-tabla">
                        <?php if ($verArchivadas): ?>
                            <?php boton_accion('rutinas.php', ['accion' => 'reactivar', 'id' => $r['id']], 'Reactivar'); ?>
                        <?php else: ?>
                            <a class="boton-pequeno" href="rutinas.php?editar=<?= (int) $r['id'] ?>">Editar ejercicios</a>
                            <?php boton_accion('rutinas.php', ['accion' => 'archivar', 'id' => $r['id']], 'Archivar', 'boton-peligro', '¿Archivar esta rutina?'); ?>
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
