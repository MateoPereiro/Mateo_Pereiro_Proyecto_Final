<?php
// =====================================================================
// planes.php — Planes y membresías: crear planes (mensual, trimestral,
// anual), modificar precios y archivar planes.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

// Duración permitida en meses para cada tipo de plan
const DURACIONES = [1 => 'Mensual (1 mes)', 3 => 'Trimestral (3 meses)', 12 => 'Anual (12 meses)'];

$errores = [];
$valores = ['nombre' => '', 'duracion_meses' => null, 'precio' => '', 'descripcion' => ''];
$idEditando = null;

// ---------- ACCIONES (POST) ----------
if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = id_valido($_POST['id'] ?? null);

    if ($accion === 'archivar' || $accion === 'reactivar') {
        if ($id !== null) {
            $archivar = $accion === 'archivar';
            $st = $pdo->prepare('UPDATE planes SET archivado_en = :valor WHERE id = :id AND archivado_en '
                                . ($archivar ? 'IS NULL' : 'IS NOT NULL'));
            $st->execute([':valor' => $archivar ? date('Y-m-d H:i:s') : null, ':id' => $id]);
            flash('ok', $archivar ? 'Plan archivado. Ya no se podrá asignar a nuevos socios.' : 'Plan reactivado.');
        }
        redirigir('planes.php');
    }

    if ($accion === 'guardar') {
        $valores = [
            'nombre' => texto_limpio($_POST['nombre'] ?? ''),
            'duracion_meses' => id_valido($_POST['duracion_meses'] ?? null),
            'precio' => texto_limpio($_POST['precio'] ?? ''),
            'descripcion' => texto_limpio($_POST['descripcion'] ?? ''),
        ];

        if ($valores['nombre'] === '') {
            $errores[] = 'El nombre del plan es obligatorio.';
        }
        if (!isset(DURACIONES[$valores['duracion_meses']])) {
            $errores[] = 'Elegí si el plan es mensual, trimestral o anual.';
        }
        if (!is_numeric($valores['precio']) || (float) $valores['precio'] < 0) {
            $errores[] = 'El precio debe ser un número mayor o igual a cero.';
        }

        // El nombre del plan no puede repetirse
        if (!$errores) {
            $st = $pdo->prepare('SELECT id FROM planes WHERE nombre = :nombre AND id <> :id');
            $st->execute([':nombre' => $valores['nombre'], ':id' => $id ?? 0]);
            if ($st->fetch()) {
                $errores[] = 'Ya existe un plan con ese nombre.';
            }
        }

        if (!$errores) {
            $datos = [
                ':nombre' => $valores['nombre'],
                ':duracion' => $valores['duracion_meses'],
                ':precio' => number_format((float) $valores['precio'], 2, '.', ''),
                ':descripcion' => nulo_si_vacio($valores['descripcion']),
            ];
            if ($id !== null) {
                $datos[':id'] = $id;
                // Cambiar el precio no altera las membresías ya asignadas (cada una guarda su precio)
                $st = $pdo->prepare('UPDATE planes SET nombre = :nombre, duracion_meses = :duracion,
                                     precio = :precio, descripcion = :descripcion WHERE id = :id');
            } else {
                $st = $pdo->prepare('INSERT INTO planes (nombre, duracion_meses, precio, descripcion)
                                     VALUES (:nombre, :duracion, :precio, :descripcion)');
            }
            $st->execute($datos);
            flash('ok', 'Plan guardado correctamente.');
            redirigir('planes.php');
        }
        $idEditando = $id;
    }
}

// Cargar plan para editar (GET)
if (!es_post() && isset($_GET['editar'])) {
    $idEditando = id_valido($_GET['editar']);
    $st = $pdo->prepare('SELECT nombre, duracion_meses, precio, descripcion FROM planes
                         WHERE id = :id AND archivado_en IS NULL');
    $st->execute([':id' => $idEditando]);
    $fila = $st->fetch();
    if (!$fila) {
        flash('error', 'El plan no existe o está archivado.');
        redirigir('planes.php');
    }
    $valores = $fila;
}

// Listado
$verArchivados = ($_GET['ver'] ?? '') === 'archivados';
$planes = $pdo->query('SELECT id, nombre, duracion_meses, precio, descripcion FROM planes
                       WHERE archivado_en ' . ($verArchivados ? 'IS NOT NULL' : 'IS NULL') . '
                       ORDER BY duracion_meses, precio')->fetchAll();

encabezado('Planes y membresías', $admin);
mostrar_errores($errores);
$modoEdicion = $idEditando !== null;
?>

<?php if (!$verArchivados): ?>
<section class="tarjeta">
    <h2><?= $modoEdicion ? 'Modificar plan y precio' : 'Crear nuevo plan' ?></h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <?php if ($modoEdicion): ?><input type="hidden" name="id" value="<?= (int) $idEditando ?>"><?php endif; ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre del plan', $valores['nombre'], ['requerido' => true, 'max' => 50]);
            select('duracion_meses', 'Tipo de plan', DURACIONES, $valores['duracion_meses'], ['requerido' => true]);
            campo('precio', 'Precio ($)', $valores['precio'], ['tipo' => 'number', 'requerido' => true, 'min' => 0, 'paso' => '0.01']);
            campo('descripcion', 'Descripción (opcional)', $valores['descripcion'] ?? '', ['max' => 255]);
            ?>
        </div>
        <p class="vacio">Si cambiás el precio, las membresías ya asignadas conservan el precio con el que se vendieron.</p>
        <div class="acciones">
            <button type="submit"><?= $modoEdicion ? 'Guardar cambios' : 'Crear plan' ?></button>
            <?php if ($modoEdicion): ?><a href="planes.php">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Planes disponibles</h2>
        <nav class="pestanas">
            <a href="planes.php" class="<?= !$verArchivados ? 'activa' : '' ?>">Activos</a>
            <a href="planes.php?ver=archivados" class="<?= $verArchivados ? 'activa' : '' ?>">Archivados</a>
        </nav>
    </div>

    <?php if (!$planes): ?>
        <p class="vacio">No hay planes para mostrar.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Precio</th><th>Descripción</th><th>Foto</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($planes as $p): ?>
                <tr>
                    <td><?= escapar($p['nombre']) ?></td>
                    <td><?= escapar(DURACIONES[(int) $p['duracion_meses']] ?? $p['duracion_meses'] . ' meses') ?></td>
                    <td><?= escapar(formato_dinero($p['precio'])) ?></td>
                    <td><?= escapar($p['descripcion']) ?></td>
                    <td>
                        <?php $img = imagen_de('planes', (int) $p['id']); ?>
                        <?php if ($img): ?>
                            <img class="miniatura" src="<?= escapar($img) ?>" alt="<?= escapar($p['nombre']) ?>">
                        <?php else: ?>
                            <span class="etiqueta etiqueta-gris">Sin foto</span>
                        <?php endif; ?>
                    </td>
                    <td class="acciones-tabla">
                        <?php if ($verArchivados): ?>
                            <?php boton_accion('planes.php', ['accion' => 'reactivar', 'id' => $p['id']], 'Reactivar'); ?>
                        <?php else: ?>
                            <a class="boton-pequeno" href="planes.php?editar=<?= (int) $p['id'] ?>">Modificar precio</a>
                            <form method="post" action="subir_imagen.php" enctype="multipart/form-data" class="en-linea subir-foto">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="carpeta" value="planes">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                                <button type="submit" class="boton-pequeno">Subir foto</button>
                            </form>
                            <?php boton_accion('planes.php', ['accion' => 'archivar', 'id' => $p['id']], 'Archivar', 'boton-peligro', '¿Archivar este plan?'); ?>
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
