<?php
declare(strict_types=1);

/**
 * Redirección o inclusión transparente hacia el Front Controller en public/
 */
$publicIndex = __DIR__ . '/public/index.php';
if (file_exists($publicIndex)) {
    // Si se accede directamente mediante Apache apuntando al directorio raíz del proyecto
    require $publicIndex;
} else {
    header('Location: public/index.php');
    exit;
}
