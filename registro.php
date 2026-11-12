<?php
// =====================================================================
// registro.php — Registro público: cualquier persona crea una cuenta de usuario.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

if (!empty($_SESSION['usuario_id'])) {
    redirigir('panel.php');
}

$errores = [];
$valores = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => ''];

if (es_post()) {
    exigir_csrf();

    $valores = [
        'nombre' => texto_limpio($_POST['nombre'] ?? ''),
        'apellido' => texto_limpio($_POST['apellido'] ?? ''),
        'dni' => texto_limpio($_POST['dni'] ?? ''),
        'email' => mb_strtolower(texto_limpio($_POST['email'] ?? '')),
    ];
    $clave = (string) ($_POST['contrasena'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    // Campos obligatorios, formatos y duplicados de DNI / correo
    $errores = array_merge(
        validar_persona($pdo, 'usuarios', $valores),
        validar_clave($clave, $confirmar)
    );

    if (!$errores) {
        // Contraseña cifrada con password_hash(): nunca se guarda en texto plano
        $st = $pdo->prepare('INSERT INTO usuarios (nombre, apellido, dni, email, contrasena, rol)
                             VALUES (:nombre, :apellido, :dni, :email, :contrasena, :rol)');
        $st->execute([
            ':nombre' => $valores['nombre'],
            ':apellido' => $valores['apellido'],
            ':dni' => $valores['dni'],
            ':email' => $valores['email'],
            ':contrasena' => password_hash($clave, PASSWORD_DEFAULT),
            ':rol' => 'usuario',   // El registro público siempre crea usuarios comunes
        ]);

        flash('ok', 'Cuenta creada correctamente. Ya podés iniciar sesión.');
        redirigir('login.php');
    }
}

encabezado('Crear cuenta');
mostrar_errores($errores);
?>
<section class="tarjeta tarjeta-centrada">
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="grilla">
            <?php
            campo('nombre', 'Nombre', $valores['nombre'], ['requerido' => true, 'max' => 60]);
            campo('apellido', 'Apellido', $valores['apellido'], ['requerido' => true, 'max' => 60]);
            campo('dni', 'DNI (sin puntos)', $valores['dni'], ['requerido' => true, 'max' => 8]);
            campo('email', 'Correo electrónico', $valores['email'], ['tipo' => 'email', 'requerido' => true, 'max' => 120]);
            campo('contrasena', 'Contraseña (mínimo 8 caracteres)', '', ['tipo' => 'password', 'requerido' => true]);
            campo('confirmar', 'Repetir contraseña', '', ['tipo' => 'password', 'requerido' => true]);
            ?>
        </div>
        <div class="acciones">
            <button type="submit">Crear cuenta</button>
            <a href="login.php">Ya tengo cuenta</a>
        </div>
    </form>
</section>
<?php pie();
