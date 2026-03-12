<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Operacao;
use Illuminate\Database\Seeder;

class OperacaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operacoes = [
            [
                'nome' => 'Operação Principal',
                'codigo' => 'OP001',
                'descricao' => 'Operação principal do sistema',
                'ativo' => true,
            ],
            [
                'nome' => 'Operação Secundária',
                'codigo' => 'OP002',
                'descricao' => 'Operação secundária para testes',
                'ativo' => true,
            ],
        ];

        foreach ($operacoes as $operacao) {
            Operacao::updateOrCreate(
                ['codigo' => $operacao['codigo']],
                $operacao
            );
        }

        $this->command->info('Operações criadas com sucesso!');
    }
}
