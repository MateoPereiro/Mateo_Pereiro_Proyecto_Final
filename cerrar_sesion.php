<?php
// =====================================================================
// cerrar_sesion.php — Cierra la sesión. Solo acepta POST con token CSRF,
// así un enlace externo no puede cerrar la sesión del usuario.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';

if (!es_post()) {
    redirigir('panel.php');
}
exigir_csrf();

destruir_sesion();
iniciar_sesion_segura();
flash('ok', 'Sesión cerrada correctamente.');
redirigir('login.php');
