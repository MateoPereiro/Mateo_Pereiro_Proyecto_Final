<?php
// Página de entrada: redirige según si hay sesión iniciada.
require_once __DIR__ . '/includes/funciones.php';

redirigir(empty($_SESSION['usuario_id']) ? 'login.php' : 'panel.php');
