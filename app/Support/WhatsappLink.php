<?php

namespace App\Support;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperacaoDadosCliente;

/**
 * Monta URL wa.me a partir de um telefone em texto (mesma lógica conceitual do {@see \App\Modules\Core\Models\Cliente::getWhatsappLinkAttribute}).
 */
final class WhatsappLink
{
    public static function urlFromTelefone(?string $telefone): ?string
    {
        if ($telefone === null || trim($telefone) === '') {
            return null;
        }

        $telefoneLimpo = preg_replace('/[^0-9]/', '', $telefone);
        if ($telefoneLimpo === '') {
            return null;
        }

        if ($telefoneLimpo[0] === '0') {
            $telefoneLimpo = substr($telefoneLimpo, 1);
        }

        if ($telefoneLimpo === '') {
            return null;
        }

        if (strlen($telefoneLimpo) >= 10 && ! str_starts_with($telefoneLimpo, '55')) {
            $telefoneLimpo = '55'.$telefoneLimpo;
        }

        return 'https://wa.me/'.$telefoneLimpo;
    }

    public static function hasValidTelefone(?string $telefone): bool
    {
        return self::urlFromTelefone($telefone) !== null;
    }

    /**
     * WhatsApp alinhado à ficha da operação quando ela tem telefone válido; senão fallback no cliente (accessor).
     */
    public static function urlPreferindoFicha(?OperacaoDadosCliente $ficha, Cliente $cliente): ?string
    {
        if ($ficha !== null && self::hasValidTelefone($ficha->telefone)) {
            return self::urlFromTelefone($ficha->telefone);
        }

        return $cliente->temWhatsapp() ? $cliente->whatsapp_link : null;
    }

    public static function temWhatsappPreferindoFicha(?OperacaoDadosCliente $ficha, Cliente $cliente): bool
    {
        return self::urlPreferindoFicha($ficha, $cliente) !== null;
    }
}
