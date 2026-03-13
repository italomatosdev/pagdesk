<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/**
 * Codifica/decodifica referência de cadastro (operacao_id + consultor_id) para uso em URL.
 * Não altera banco; usa criptografia reversível com APP_KEY.
 */
class RefEncoder
{
    /**
     * Gera string ofuscada para usar na URL (ex.: ?ref=xxx).
     *
     * @param int $operacaoId
     * @param int $consultorId
     * @return string
     */
    public static function encode(int $operacaoId, int $consultorId): string
    {
        $payload = [
            'o' => $operacaoId,
            'c' => $consultorId,
        ];

        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Decodifica ref e retorna [operacao_id, consultor_id].
     *
     * @param string $ref
     * @return array{0: int, 1: int} [operacao_id, consultor_id]
     * @throws InvalidArgumentException
     */
    public static function decode(string $ref): array
    {
        try {
            $json = Crypt::decryptString($ref);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Link de cadastro inválido ou expirado.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['o'], $payload['c'])) {
            throw new InvalidArgumentException('Link de cadastro inválido.');
        }

        $operacaoId = (int) $payload['o'];
        $consultorId = (int) $payload['c'];

        if ($operacaoId < 1 || $consultorId < 1) {
            throw new InvalidArgumentException('Link de cadastro inválido.');
        }

        return [$operacaoId, $consultorId];
    }
}
