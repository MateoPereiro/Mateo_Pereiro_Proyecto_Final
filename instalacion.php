<?php
// =====================================================================
// instalacion.php — Instalaciones del gimnasio: una foto por espacio y
// una descripción abajo. Las fotos van en img/instalacion/N.jpg (1, 2, 3...).
// Para cambiar los textos, editá el arreglo $espacios.
// =====================================================================
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/diseno.php';

$usuario = requerir_login($pdo);

// Devuelve la ruta de la foto del espacio N si existe, o null si todavía no se cargó
$fotoEspacio = function (int $numero): ?string {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $ruta = "img/instalacion/$numero.$ext";
        if (is_file(__DIR__ . '/' . $ruta)) {
            return $ruta;
        }
    }
    return null;
};

$espacios = [
    [
        'titulo' => 'Sala de musculación',
        'texto' => [
            'Zona equipada con máquinas y pesas libres para entrenar fuerza.',
            'Si querés, tu entrenador te puede armar una rutina a medida.',
        ],
    ],
    [
        'titulo' => 'Zona de cardio',
        'texto' => [
            'Cintas, bicicletas y elípticos para trabajar la resistencia.',
        ],
    ],
    [
        'titulo' => 'Clases grupales',
        'texto' => [
            'Espacio para las clases dirigidas. Consultá los horarios con el personal de recepción.',
        ],
    ],
    [
        'titulo' => 'Vestuarios',
        'texto' => [
            'Vestuarios con duchas y casilleros para usar antes y después de entrenar.',
        ],
    ],
    [
        'titulo' => 'Recepción',
        'texto' => [
            'Punto de atención para socios: altas, pagos y consultas sobre los planes.',
        ],
    ],
];

encabezado('Instalaciones', $usuario);
?>
<p class="vacio">Conocé los espacios del gimnasio.</p>

<?php foreach ($espacios as $indice => $espacio): ?>
    <?php $numero = $indice + 1; $foto = $fotoEspacio($numero); ?>
<section class="tarjeta paso">
    <?php if ($foto): ?>
        <img class="foto-paso" src="<?= escapar($foto) ?>" alt="<?= escapar($espacio['titulo']) ?>">
    <?php else: ?>
        <div class="foto-paso foto-pendiente">Foto de <?= escapar($espacio['titulo']) ?> (pendiente)</div>
    <?php endif; ?>

    <h2><?= escapar($espacio['titulo']) ?></h2>
    <div class="texto-paso">
        <?php foreach ($espacio['texto'] as $parrafo): ?>
            <p><?= escapar($parrafo) ?></p>
        <?php endforeach; ?>
    </div>

    <?php if ($usuario['rol'] === 'admin'): ?>
    <form method="post" action="subir_imagen.php" enctype="multipart/form-data" class="en-linea subir-foto">
        <?= campo_csrf() ?>
        <input type="hidden" name="carpeta" value="instalacion">
        <input type="hidden" name="id" value="<?= $numero ?>">
        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" class="boton-pequeno">Subir foto</button>
    </form>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php pie();
