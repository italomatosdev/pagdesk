<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV de relatórios: mesmo padrão de clientes/empréstimos (UTF-8 BOM, separador ;).
 */
final class RelatorioCsvStream
{
    public static function download(string $basename, Closure $writeRows): StreamedResponse
    {
        $filename = $basename.'_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($writeRows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            $writeRows($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
