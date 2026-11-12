<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'app'): Response
    {
        $file = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vue introuvable : {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return Response::html($content);
        }

        $layoutFile = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout introuvable : {$layout}");
        }

        ob_start();
        require $layoutFile;
        return Response::html((string) ob_get_clean());
    }
}
