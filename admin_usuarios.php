<?php
// =====================================================================
// admin_usuarios.php — Panel administrativo de cuentas: listado activo,
// buscador (nombre, apellido, DNI o correo) y alta de nuevos usuarios.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

$errores = [];
$valores = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'rol' => 'usuario'];

// ---------- Alta de usuario desde el panel ----------
if (es_post()) {
    exigir_csrf();

    $valores = [
        'nombre' => texto_limpio($_POST['nombre'] ?? ''),
        'apellido' => texto_limpio($_POST['apellido'] ?? ''),
        'dni' => texto_limpio($_POST['dni'] ?? ''),
        'email' => mb_strtolower(texto_limpio($_POST['email'] ?? '')),
        'rol' => ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'usuario',
    ];
    $clave = (string) ($_POST['contrasena'] ?? '');

    $errores = array_merge(
        validar_persona($pdo, 'usuarios', $valores),
        validar_clave($clave, $clave)   // Clave inicial: sin pedir confirmación aquí
    );

    if (!$errores) {
        $st = $pdo->prepare('INSERT INTO usuarios (nombre, apellido, dni, email, contrasena, rol)
                             VALUES (:nombre, :apellido, :dni, :email, :contrasena, :rol)');
        $st->execute([
            ':nombre' => $valores['nombre'],
            ':apellido' => $valores['apellido'],
            ':dni' => $valores['dni'],
            ':email' => $valores['email'],
            ':contrasena' => password_hash($clave, PASSWORD_DEFAULT),
            ':rol' => $valores['rol'],
        ]);
        flash('ok', 'Usuario registrado correctamente.');
        redirigir('admin_usuarios.php');
    }
}

// ---------- Listado y búsqueda (solo activos) ----------
$buscar = texto_limpio($_GET['buscar'] ?? '');
$sql = 'SELECT id, nombre, apellido, dni, email, rol, creado_en FROM usuarios WHERE archivado_en IS NULL';
$parametros = [];

if ($buscar !== '') {
    $sql .= ' AND (nombre LIKE :nombre OR apellido LIKE :apellido OR dni LIKE :dni OR email LIKE :email)';
    $patron = patron_like($buscar);
    $parametros = [':nombre' => $patron, ':apellido' => $patron, ':dni' => $patron, ':email' => $patron];
}
$sql .= ' ORDER BY apellido, nombre';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$usuarios = $st->fetchAll();

encabezado('Usuarios del sistema', $admin);
mostrar_errores($errores);
?>
<section class="tarjeta">
    <h2>Registrar nuevo usuario</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre', $valores['nombre'], ['requerido' => true, 'max' => 60]);
            campo('apellido', 'Apellido', $valores['apellido'], ['requerido' => true, 'max' => 60]);
            campo('dni', 'DNI (sin puntos)', $valores['dni'], ['requerido' => true, 'max' => 8]);
            campo('email', 'Correo electrónico', $valores['email'], ['tipo' => 'email', 'requerido' => true, 'max' => 120]);
            campo('contrasena', 'Contraseña inicial (mínimo 8)', '', ['tipo' => 'password', 'requerido' => true]);
            select('rol', 'Rol', ['usuario' => 'Usuario', 'admin' => 'Administrador'], $valores['rol'], ['sin_placeholder' => true]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Registrar usuario</button>
        </div>
    </form>
</section>

<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Usuarios registrados</h2>
        <a class="boton-secundario" href="usuarios_archivados.php">Ver archivados</a>
    </div>

    <form method="get" class="buscador">
        <input type="search" name="buscar" value="<?= escapar($buscar) ?>" maxlength="100"
               placeholder="Nombre, apellido, DNI o correo">
        <button type="submit">Buscar</button>
        <?php if ($buscar !== ''): ?>
            <a class="boton-secundario" href="admin_usuarios.php">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (!$usuarios): ?>
        <p class="vacio">No se encontraron usuarios activos.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Apellido y nombre</th>
                    <th>DNI</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= escapar($u['apellido'] . ', ' . $u['nombre']) ?></td>
                    <td><?= escapar($u['dni']) ?></td>
                    <td><?= escapar($u['email']) ?></td>
                    <td><?= $u['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?></td>
                    <td><?= escapar(formato_fecha($u['creado_en'])) ?></td>
                    <td class="acciones-tabla">
                        <a class="boton-pequeno" href="editar_usuario.php?id=<?= (int) $u['id'] ?>">Editar</a>
                        <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                            <?php boton_accion('archivar_usuario.php', ['id' => $u['id']], 'Archivar', 'boton-peligro', '¿Archivar este usuario? Podrás reactivarlo luego.'); ?>
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
