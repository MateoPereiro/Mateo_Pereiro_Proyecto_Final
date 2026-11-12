<?php
// =====================================================================
// login.php — Inicio de sesión con correo y contraseña.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

if (!empty($_SESSION['usuario_id'])) {
    redirigir('panel.php');
}

$errores = [];
$email = '';

if (es_post()) {
    exigir_csrf();

    $email = mb_strtolower(texto_limpio($_POST['email'] ?? ''));
    $clave = (string) ($_POST['contrasena'] ?? '');

    if ($email === '' || $clave === '') {
        $errores[] = 'Completá el correo y la contraseña.';
    } else {
        // Solo cuentas activas (no archivadas)
        $st = $pdo->prepare('SELECT id, contrasena, rol FROM usuarios
                             WHERE email = :email AND archivado_en IS NULL');
        $st->execute([':email' => $email]);
        $usuario = $st->fetch();

        // password_verify() compara la clave ingresada con el hash guardado
        if ($usuario && password_verify($clave, $usuario['contrasena'])) {
            session_regenerate_id(true);   // Evita que se reutilice un identificador de sesión anterior
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            unset($_SESSION['csrf']);      // Se genera un token nuevo para esta sesión

            flash('ok', '¡Bienvenido/a!');
            redirigir('panel.php');
        }

        // Mensaje genérico: no revelamos si el correo existe o si falló la contraseña
        $errores[] = 'Correo o contraseña incorrectos.';
    }
}

encabezado('Iniciar sesión');
mostrar_errores($errores);
?>
<section class="tarjeta tarjeta-centrada">
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="grilla">
            <?php
            campo('email', 'Correo electrónico', $email, ['tipo' => 'email', 'requerido' => true]);
            campo('contrasena', 'Contraseña', '', ['tipo' => 'password', 'requerido' => true]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Ingresar</button>
            <a href="registro.php">Crear una cuenta</a>
        </div>
    </form>
</section>
<?php pie();
