<?php
// =====================================================================
// usuarios_archivados.php — Consulta de cuentas archivadas, con opción de reactivar.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$admin = requerir_admin($pdo);

$usuarios = $pdo->query('SELECT id, nombre, apellido, dni, email, rol, archivado_en
                         FROM usuarios WHERE archivado_en IS NOT NULL
                         ORDER BY archivado_en DESC')->fetchAll();

encabezado('Usuarios archivados', $admin);
?>
<section class="tarjeta">
    <div class="cabecera-seccion">
        <h2>Cuentas archivadas</h2>
        <a class="boton-secundario" href="admin_usuarios.php">Volver a usuarios activos</a>
    </div>

    <?php if (!$usuarios): ?>
        <p class="vacio">No hay usuarios archivados.</p>
    <?php else: ?>
    <div class="tabla-responsive">
        <table class="tabla">
            <thead>
                <tr><th>Apellido y nombre</th><th>DNI</th><th>Correo</th><th>Rol</th><th>Archivado el</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= escapar($u['apellido'] . ', ' . $u['nombre']) ?></td>
                    <td><?= escapar($u['dni']) ?></td>
                    <td><?= escapar($u['email']) ?></td>
                    <td><?= $u['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?></td>
                    <td><?= escapar(formato_fecha_hora($u['archivado_en'])) ?></td>
                    <td><?php boton_accion('reactivar_usuario.php', ['id' => $u['id']], 'Reactivar', 'boton-secundario'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php pie();
