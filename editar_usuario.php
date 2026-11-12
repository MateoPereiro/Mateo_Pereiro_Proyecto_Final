<?php
// =====================================================================
// editar_usuario.php — Edición de datos de un usuario existente.
// El cambio de contraseña es una operación separada.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

// Se toma el id de POST (al guardar) o de GET (al abrir el formulario)
$id = id_valido($_POST['id'] ?? $_GET['id'] ?? null);
if ($id === null) {
    flash('error', 'Identificador de usuario no válido.');
    redirigir('admin_usuarios.php');
}

$st = $pdo->prepare('SELECT id, nombre, apellido, dni, email, rol FROM usuarios
                     WHERE id = :id AND archivado_en IS NULL');
$st->execute([':id' => $id]);
$usuario = $st->fetch();

if (!$usuario) {
    flash('error', 'El usuario no existe o está archivado.');
    redirigir('admin_usuarios.php');
}

$errores = [];
$erroresClave = [];
$valores = $usuario;

if (es_post()) {
    exigir_csrf();
    $accion = $_POST['accion'] ?? '';

    // ---------- Guardar datos personales y rol ----------
    if ($accion === 'datos') {
        $valores = [
            'nombre' => texto_limpio($_POST['nombre'] ?? ''),
            'apellido' => texto_limpio($_POST['apellido'] ?? ''),
            'dni' => texto_limpio($_POST['dni'] ?? ''),
            'email' => mb_strtolower(texto_limpio($_POST['email'] ?? '')),
            'rol' => ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'usuario',
        ];

        $errores = validar_persona($pdo, 'usuarios', $valores, $id);

        // Protección: nadie puede quitarse a sí mismo el rol de administrador
        if ($id === (int) $admin['id'] && $valores['rol'] !== 'admin') {
            $errores[] = 'No podés quitarte a vos mismo el rol de administrador.';
        }

        if (!$errores) {
            $st = $pdo->prepare('UPDATE usuarios
                                 SET nombre = :nombre, apellido = :apellido, dni = :dni,
                                     email = :email, rol = :rol
                                 WHERE id = :id AND archivado_en IS NULL');
            $st->execute([
                ':nombre' => $valores['nombre'],
                ':apellido' => $valores['apellido'],
                ':dni' => $valores['dni'],
                ':email' => $valores['email'],
                ':rol' => $valores['rol'],
                ':id' => $id,
            ]);
            flash('ok', 'Datos del usuario actualizados.');
            redirigir('admin_usuarios.php');
        }
    }

    // ---------- Cambiar contraseña (operación separada) ----------
    if ($accion === 'contrasena') {
        $clave = (string) ($_POST['contrasena'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');
        $erroresClave = validar_clave($clave, $confirmar);

        if (!$erroresClave) {
            $st = $pdo->prepare('UPDATE usuarios SET contrasena = :hash WHERE id = :id');
            $st->execute([':hash' => password_hash($clave, PASSWORD_DEFAULT), ':id' => $id]);
            flash('ok', 'Contraseña actualizada.');
            redirigir('editar_usuario.php?id=' . $id);
        }
    }
}

encabezado('Editar usuario', $admin);
mostrar_errores($errores);
mostrar_errores($erroresClave);
?>
<section class="tarjeta">
    <h2>Datos personales</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="datos">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre', $valores['nombre'], ['requerido' => true, 'max' => 60]);
            campo('apellido', 'Apellido', $valores['apellido'], ['requerido' => true, 'max' => 60]);
            campo('dni', 'DNI (sin puntos)', $valores['dni'], ['requerido' => true, 'max' => 8]);
            campo('email', 'Correo electrónico', $valores['email'], ['tipo' => 'email', 'requerido' => true, 'max' => 120]);
            select('rol', 'Rol', ['usuario' => 'Usuario', 'admin' => 'Administrador'], $valores['rol'], ['sin_placeholder' => true]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Guardar cambios</button>
            <a href="admin_usuarios.php">Volver al listado</a>
        </div>
    </form>
</section>

<section class="tarjeta">
    <h2>Cambiar contraseña</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="contrasena">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="grilla">
            <?php
            campo('contrasena', 'Nueva contraseña (mínimo 8)', '', ['tipo' => 'password', 'requerido' => true]);
            campo('confirmar', 'Repetir nueva contraseña', '', ['tipo' => 'password', 'requerido' => true]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Actualizar contraseña</button>
        </div>
    </form>
</section>
<?php pie();
