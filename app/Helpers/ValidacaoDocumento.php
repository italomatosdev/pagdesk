<?php

namespace App\Helpers;

class ValidacaoDocumento
{
    /**
     * Validar CPF
     *
     * @param string $cpf CPF sem formatação (apenas números)
     * @return bool
     */
    public static function validarCpf(string $cpf): bool
    {
        // Remove formatação
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cpf[9]) !== $digito1) {
            return false;
        }

        // Valida segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cpf[10]) !== $digito2) {
            return false;
        }

        return true;
    }

    /**
     * Validar CNPJ
     *
     * @param string $cnpj CNPJ sem formatação (apenas números)
     * @return bool
     */
    public static function validarCnpj(string $cnpj): bool
    {
        // Remove formatação
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Verifica se tem 14 dígitos
        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $soma = 0;
        $peso = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso == 2) ? 9 : $peso - 1;
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cnpj[12]) !== $digito1) {
            return false;
        }

        // Valida segundo dígito verificador
        $soma = 0;
        $peso = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso == 2) ? 9 : $peso - 1;
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cnpj[13]) !== $digito2) {
            return false;
        }

        return true;
    }

    /**
     * Formatar CPF
     *
     * @param string $cpf CPF sem formatação
     * @return string
     */
    public static function formatarCpf(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    /**
     * Formatar CNPJ
     *
     * @param string $cnpj CNPJ sem formatação
     * @return string
     */
    public static function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
}
