<?php
// =====================================================================
// diseno.php — Encabezado, pie y componentes de formulario reutilizables.
// Todo lo que se imprime en pantalla pasa por escapar().
// =====================================================================

function encabezado(string $titulo, ?array $usuario = null): void
{
    $aviso = obtener_flash();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($titulo) ?> · Fit Zone</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<?php if ($usuario): ?>
<header class="barra">
    <a class="marca" href="panel.php">🏋️ Fit Zone</a>
    <nav class="menu">
        <a href="panel.php">Mi panel</a>
        <a href="instalacion.php">Instalaciones</a>
        <?php if ($usuario['rol'] === 'admin'): ?>
            <a href="socios.php">Socios</a>
            <a href="entrenadores.php">Entrenadores</a>
            <a href="planes.php">Planes</a>
            <a href="membresias.php">Membresías</a>
            <a href="pagos.php">Pagos</a>
            <a href="admin_usuarios.php">Usuarios</a>
        <?php else: ?>
            <a href="mi_membresia.php">Mi membresía</a>
            <a href="ver_planes.php">Planes</a>
            <a href="ver_entrenadores.php">Entrenadores</a>
        <?php endif; ?>
        <form method="post" action="cerrar_sesion.php" class="en-linea">
            <?= campo_csrf() ?>
            <button type="submit" class="boton-secundario">Cerrar sesión</button>
        </form>
    </nav>
</header>
<?php endif; ?>
<main class="contenedor">
<?php if ($aviso): ?>
    <div class="aviso aviso-<?= escapar($aviso['tipo']) ?>"><?= escapar($aviso['texto']) ?></div>
<?php endif; ?>
    <h1><?= escapar($titulo) ?></h1>
<?php
}

function pie(): void
{
    ?>
</main>
<footer class="pie">Fit Zone · Trabajo práctico final · Mateo Pereiro</footer>
</body>
</html>
<?php
}

// Muestra la lista de errores de validación (si hay).
function mostrar_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="aviso aviso-error"><strong>Revisá los datos:</strong><ul>';
    foreach ($errores as $error) {
        echo '<li>' . escapar($error) . '</li>';
    }
    echo '</ul></div>';
}

// Campo de texto (o email, número, fecha...). Opciones: tipo, requerido, max, min, paso.
function campo(string $nombre, string $etiqueta, $valor = '', array $o = []): void
{
    $atributos = '';
    if (!empty($o['requerido'])) {
        $atributos .= ' required';
    }
    if (isset($o['max'])) {
        $atributos .= ' maxlength="' . (int) $o['max'] . '"';
    }
    if (isset($o['min'])) {
        $atributos .= ' min="' . escapar($o['min']) . '"';
    }
    if (isset($o['paso'])) {
        $atributos .= ' step="' . escapar($o['paso']) . '"';
    }
    ?>
    <label class="campo">
        <span><?= escapar($etiqueta) ?></span>
        <input type="<?= escapar($o['tipo'] ?? 'text') ?>" name="<?= escapar($nombre) ?>"
               value="<?= escapar($valor) ?>"<?= $atributos ?>>
    </label>
    <?php
}

// Lista desplegable. $opciones = [valor => texto].
function select(string $nombre, string $etiqueta, array $opciones, $seleccionado = null, array $o = []): void
{
    $requerido = !empty($o['requerido']) ? ' required' : '';
    ?>
    <label class="campo">
        <span><?= escapar($etiqueta) ?></span>
        <select name="<?= escapar($nombre) ?>"<?= $requerido ?>>
            <?php if (empty($o['sin_placeholder'])): ?>
                <option value=""><?= escapar($o['primera'] ?? '— Elegir —') ?></option>
            <?php endif; ?>
            <?php foreach ($opciones as $valor => $texto): ?>
                <option value="<?= escapar($valor) ?>"<?= (string) $valor === (string) $seleccionado ? ' selected' : '' ?>>
                    <?= escapar($texto) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

// Botón que envía un formulario POST con token CSRF (archivar, reactivar, etc.).
function boton_accion(string $url, array $campos, string $texto, string $clase = 'boton-secundario', ?string $confirmar = null): void
{
    $onsubmit = $confirmar ? ' onsubmit="return confirm(' . escapar(json_encode($confirmar)) . ');"' : '';
    echo '<form method="post" action="' . escapar($url) . '" class="en-linea"' . $onsubmit . '>';
    echo campo_csrf();
    foreach ($campos as $nombre => $valor) {
        echo '<input type="hidden" name="' . escapar($nombre) . '" value="' . escapar($valor) . '">';
    }
    echo '<button type="submit" class="' . escapar($clase) . '">' . escapar($texto) . '</button></form>';
}
