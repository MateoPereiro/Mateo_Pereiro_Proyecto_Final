<?php
// =====================================================================
// panel.php — Inicio del usuario: datos personales y accesos a cada sección.
// Cada sección tiene su propia página.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);   // Sin sesión válida, no se puede entrar
$esAdmin = $usuario['rol'] === 'admin';

encabezado('Mi panel', $usuario);
?>
<section class="tarjeta">
    <h2>Mis datos personales</h2>
    <dl class="datos">
        <dt>Nombre</dt>
        <dd><?= escapar($usuario['nombre']) ?></dd>
        <dt>Apellido</dt>
        <dd><?= escapar($usuario['apellido']) ?></dd>
        <dt>DNI</dt>
        <dd><?= escapar($usuario['dni']) ?></dd>
        <dt>Correo</dt>
        <dd><?= escapar($usuario['email']) ?></dd>
        <dt>Rol</dt>
        <dd><?= $esAdmin ? 'Administrador' : 'Usuario' ?></dd>
        <dt>Cuenta creada</dt>
        <dd><?= escapar(formato_fecha_hora($usuario['creado_en'])) ?></dd>
    </dl>
</section>

<section class="tarjeta">
    <h2>Mi cuenta</h2>
    <div class="modulos">
        <a class="modulo" href="mi_membresia.php"><strong>Mi membresía</strong><small>Estado de la cuota y mis pagos</small></a>
        <a class="modulo" href="ver_planes.php"><strong>Planes</strong><small>Ver precios y comprar un plan</small></a>
        <a class="modulo" href="ver_entrenadores.php"><strong>Entrenadores</strong><small>Conocé al equipo</small></a>
    </div>
</section>

<?php if ($esAdmin): ?>
<section class="tarjeta">
    <h2>Administración de Fit Zone</h2>
    <div class="modulos">
        <a class="modulo" href="socios.php"><strong>Socios</strong><small>Alta, edición, búsqueda y cuota al día</small></a>
        <a class="modulo" href="entrenadores.php"><strong>Entrenadores</strong><small>Especialidades y alumnos asignados</small></a>
        <a class="modulo" href="planes.php"><strong>Planes</strong><small>Mensual, trimestral y anual</small></a>
        <a class="modulo" href="membresias.php"><strong>Membresías</strong><small>Asignación y vencimientos</small></a>
        <a class="modulo" href="pagos.php"><strong>Pagos</strong><small>Registro, historial y filtros</small></a>
        <a class="modulo" href="admin_usuarios.php"><strong>Usuarios del sistema</strong><small>Cuentas de acceso</small></a>
    </div>
</section>
<?php endif; ?>
<?php pie();
