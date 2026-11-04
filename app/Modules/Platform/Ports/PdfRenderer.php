<?php

namespace App\Modules\Platform\Ports;

/**
 * Turns a Blade view into an A4 PDF that shapes Arabic correctly (right to left, joined letters).
 */
interface PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $title): string;
}
