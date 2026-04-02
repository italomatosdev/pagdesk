<?php

namespace Tests\Unit;

use App\Modules\Core\Controllers\RelatorioController;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class RelatorioControllerRepartirJurosTest extends TestCase
{
    /**
     * @return array{juros: float, investido: float, juros_contrato: float, juros_atraso: float, juros_incorporados: float}
     */
    private static function repartir(Pagamento $p): array
    {
        $ref = new ReflectionClass(RelatorioController::class);
        $m = $ref->getMethod('repartirInvestidoJurosParaRelatorio');
        $m->setAccessible(true);

        return $m->invoke(null, $p);
    }

    #[Test]
    public function renovacao_pagamento_so_juros_trata_nominal_como_juros_contrato(): void
    {
        $emp = new Emprestimo;
        $emp->emprestimo_origem_id = 1;
        $emp->juros_incorporados = 0;

        $parcela = new Parcela;
        $parcela->valor = 379250;
        $parcela->valor_juros = 9250;
        $parcela->valor_amortizacao = 370000;
        $parcela->setRelation('emprestimo', $emp);

        $p = new Pagamento;
        $p->valor = 9250;
        $p->valor_juros = 0;
        $p->setRelation('parcela', $parcela);

        $out = self::repartir($p);

        $this->assertSame(9250.0, $out['juros_contrato']);
        $this->assertSame(9250.0, $out['juros']);
        $this->assertSame(0.0, $out['investido']);
    }

    #[Test]
    public function contrato_origem_com_renovacao_gerada_so_juros_usa_heuristica(): void
    {
        $emp = new Emprestimo;
        $emp->emprestimo_origem_id = null;
        $emp->setAttribute('renovacoes_count', 1);
        $emp->juros_incorporados = 0;

        $parcela = new Parcela;
        $parcela->valor = 379250;
        $parcela->valor_juros = 9250;
        $parcela->valor_amortizacao = 370000;
        $parcela->setRelation('emprestimo', $emp);

        $p = new Pagamento;
        $p->valor = 9250;
        $p->valor_juros = 0;
        $p->setRelation('parcela', $parcela);

        $out = self::repartir($p);

        $this->assertSame(9250.0, $out['juros_contrato']);
        $this->assertSame(9250.0, $out['juros']);
        $this->assertSame(0.0, $out['investido']);
    }

    #[Test]
    public function mesmo_valor_sem_cadeia_renovacao_mantem_proporcao_da_parcela(): void
    {
        $emp = new Emprestimo;
        $emp->emprestimo_origem_id = null;
        $emp->setAttribute('renovacoes_count', 0);
        $emp->juros_incorporados = 0;

        $parcela = new Parcela;
        $parcela->valor = 379250;
        $parcela->valor_juros = 9250;
        $parcela->valor_amortizacao = 370000;
        $parcela->setRelation('emprestimo', $emp);

        $p = new Pagamento;
        $p->valor = 9250;
        $p->valor_juros = 0;
        $p->setRelation('parcela', $parcela);

        $out = self::repartir($p);

        $esperadoJurosContrato = round(9250 * (9250 / 379250), 2);
        $this->assertSame($esperadoJurosContrato, $out['juros_contrato']);
        $this->assertGreaterThan(8000.0, $out['investido']);
        $this->assertLessThan(9300.0, $out['juros']);
    }

    #[Test]
    public function renovacao_pagamento_parcela_inteira_nao_usa_heuristica_so_juros(): void
    {
        $emp = new Emprestimo;
        $emp->emprestimo_origem_id = 1;
        $emp->juros_incorporados = 0;
        $emp->numero_parcelas = 1;
        $emp->valor_total = 370000;

        $parcela = new Parcela;
        $parcela->valor = 379250;
        $parcela->valor_juros = 9250;
        $parcela->valor_amortizacao = 370000;
        $parcela->setRelation('emprestimo', $emp);

        $p = new Pagamento;
        $p->valor = 379250;
        $p->valor_juros = 0;
        $p->setRelation('parcela', $parcela);

        $out = self::repartir($p);

        $esperadoJurosContrato = round(379250 * (9250 / 379250), 2);
        $this->assertSame(9250.0, $esperadoJurosContrato);
        $this->assertSame(9250.0, $out['juros_contrato']);
        $this->assertSame(370000.0, $out['investido']);
    }

    #[Test]
    public function juros_de_atraso_ficam_explicitos_base_reparte_sem_atraso(): void
    {
        $emp = new Emprestimo;
        $emp->emprestimo_origem_id = 1;
        $emp->juros_incorporados = 0;

        $parcela = new Parcela;
        $parcela->valor = 379250;
        $parcela->valor_juros = 9250;
        $parcela->valor_amortizacao = 370000;
        $parcela->setRelation('emprestimo', $emp);

        $p = new Pagamento;
        $p->valor = 10250;
        $p->valor_juros = 1000;
        $p->setRelation('parcela', $parcela);

        $out = self::repartir($p);

        $this->assertSame(1000.0, $out['juros_atraso']);
        $this->assertSame(9250.0, $out['juros_contrato']);
        $this->assertSame(10250.0, $out['juros']);
        $this->assertSame(0.0, $out['investido']);
    }
}
