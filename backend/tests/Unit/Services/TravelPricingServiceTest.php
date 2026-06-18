<?php

namespace Tests\Unit\Services;

use App\Services\TravelPricingService;
use PHPUnit\Framework\TestCase;

class TravelPricingServiceTest extends TestCase
{
    private TravelPricingService $service;

    protected function setUp(): void
    {
        $this->service = new TravelPricingService();
    }

    // -------------------------------------------------------------------------
    // 1. PERÍODO MÍNIMO DE 5 DIAS
    // -------------------------------------------------------------------------

    /** Viagem de 1 dia real deve ser cobrada como 5 dias. */
    public function test_periodo_minimo_cinco_dias_quando_viagem_e_mais_curta(): void
    {
        // 01/06 → 01/06 = 1 dia real → max(5, 1) = 5
        // NACIONAL: 10 × 5 × 1.0 (adulto) = 50.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-01',
            [['nome' => 'Alice', 'data_nascimento' => '1990-01-01', 'adicionais' => []]]
        );

        $this->assertSame(5, $resultado['dias_cobrados']);
        $this->assertEqualsWithDelta(50.00, $resultado['total_final'], 0.001);
    }

    /** Viagem de exatamente 5 dias deve cobrar 5 dias (bate no mínimo, não excede). */
    public function test_periodo_de_cinco_dias_exatos_nao_e_alterado(): void
    {
        // 01/06 → 05/06 = (4+1) = 5 dias → max(5, 5) = 5
        // NACIONAL: 10 × 5 × 1.0 = 50.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-05',
            [['nome' => 'Alice', 'data_nascimento' => '1990-01-01', 'adicionais' => []]]
        );

        $this->assertSame(5, $resultado['dias_cobrados']);
        $this->assertEqualsWithDelta(50.00, $resultado['total_final'], 0.001);
    }

    /** Viagem de 10 dias não é afetada pelo mínimo de 5. */
    public function test_viagem_acima_do_minimo_cobra_os_dias_reais(): void
    {
        // 01/06 → 10/06 = (9+1) = 10 dias → max(5, 10) = 10
        // NACIONAL: 10 × 10 × 1.0 = 100.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-10',
            [['nome' => 'Alice', 'data_nascimento' => '1990-01-01', 'adicionais' => []]]
        );

        $this->assertSame(10, $resultado['dias_cobrados']);
        $this->assertEqualsWithDelta(100.00, $resultado['total_final'], 0.001);
    }

    // -------------------------------------------------------------------------
    // 2. CÁLCULO DE IDADE NA DATA DE INÍCIO (não na data de hoje)
    // -------------------------------------------------------------------------

    /**
     * Viajante que faz exatamente 18 anos NO DIA da viagem deve ser adulto (×1.0).
     * Prova que a idade é calculada na data de início, não em outra data.
     */
    public function test_idade_calculada_na_data_inicio_aniversario_no_proprio_dia(): void
    {
        // Nasce 2008-07-10 · início 2026-07-10 → 18 anos exatos → ×1.0
        // NACIONAL, 1 dia → 5 cobrados · 10 × 5 × 1.0 = 50.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-07-10',
            '2026-07-10',
            [['nome' => 'Bob', 'data_nascimento' => '2008-07-10', 'adicionais' => []]]
        );

        $this->assertSame(18, $resultado['viajantes'][0]['idade']);
        $this->assertEqualsWithDelta(50.00, $resultado['total_final'], 0.001);
    }

    /**
     * Viajante que faz 18 anos UM DIA DEPOIS do início ainda deve ser menor de idade (×0.5).
     * Par com o teste anterior: juntos provam que a fronteira de idade está correta.
     */
    public function test_idade_calculada_na_data_inicio_um_dia_antes_do_aniversario(): void
    {
        // Nasce 2008-07-11 · início 2026-07-10 → 17 anos (não completou 18) → ×0.5
        // NACIONAL, 1 dia → 5 cobrados · 10 × 5 × 0.5 = 25.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-07-10',
            '2026-07-10',
            [['nome' => 'Carol', 'data_nascimento' => '2008-07-11', 'adicionais' => []]]
        );

        $this->assertSame(17, $resultado['viajantes'][0]['idade']);
        $this->assertEqualsWithDelta(25.00, $resultado['total_final'], 0.001);
    }

    /** Viajante que faz exatamente 65 anos no dia da viagem deve ter multiplicador sênior (×2.0). */
    public function test_viajante_com_65_anos_exatos_usa_multiplicador_senior(): void
    {
        // Nasce 1961-07-10 · início 2026-07-10 → 65 anos exatos → ×2.0
        // NACIONAL, 5 dias · 10 × 5 × 2.0 = 100.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-07-10',
            '2026-07-10',
            [['nome' => 'Dona', 'data_nascimento' => '1961-07-10', 'adicionais' => []]]
        );

        $this->assertSame(65, $resultado['viajantes'][0]['idade']);
        $this->assertEqualsWithDelta(100.00, $resultado['total_final'], 0.001);
    }

    // -------------------------------------------------------------------------
    // 3. ESPORTES DE AVENTURA — NEGADO COM AVISO (fora da faixa 18-64)
    // -------------------------------------------------------------------------

    /**
     * Sênior (65+) solicitando ESPORTES_AVENTURA: adicional NÃO é aplicado,
     * subtotal não muda, mas um aviso é emitido. A cotação segue normalmente.
     */
    public function test_esportes_aventura_negado_para_senior_com_aviso(): void
    {
        // Nasce 1950-01-01, início 2026-06-01 → 76 anos → ×2.0
        // NACIONAL, 1 dia → 5 cobrados · 10 × 5 × 2.0 = 100.00
        // ESPORTES negado (76 anos) → subtotal permanece 100.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-01',
            [[
                'nome'            => 'Eduardo',
                'data_nascimento' => '1950-01-01',
                'adicionais'      => ['ESPORTES_AVENTURA'],
            ]]
        );

        $this->assertEqualsWithDelta(100.00, $resultado['total_final'], 0.001);
        $this->assertNotContains('ESPORTES_AVENTURA', $resultado['viajantes'][0]['adicionais_aplicados']);
        $this->assertCount(1, $resultado['avisos']);
        $this->assertStringContainsString('ESPORTES_AVENTURA', $resultado['avisos'][0]);
        $this->assertStringContainsString('Eduardo', $resultado['avisos'][0]);
        $this->assertStringContainsString('18-64', $resultado['avisos'][0]);
    }

    /** Menor de 18 solicitando ESPORTES_AVENTURA: mesmo comportamento — negado com aviso. */
    public function test_esportes_aventura_negado_para_menor_de_18_com_aviso(): void
    {
        // Nasce 2010-01-01, início 2026-06-01 → 16 anos → ×0.5
        // NACIONAL, 5 dias · 10 × 5 × 0.5 = 25.00 · ESPORTES negado (16 anos)
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-01',
            [[
                'nome'            => 'Fernanda',
                'data_nascimento' => '2010-01-01',
                'adicionais'      => ['ESPORTES_AVENTURA'],
            ]]
        );

        $this->assertEqualsWithDelta(25.00, $resultado['total_final'], 0.001);
        $this->assertNotContains('ESPORTES_AVENTURA', $resultado['viajantes'][0]['adicionais_aplicados']);
        $this->assertCount(1, $resultado['avisos']);
        $this->assertStringContainsString('Fernanda', $resultado['avisos'][0]);
    }

    /**
     * Adulto elegível (18-64) com ESPORTES_AVENTURA: adicional É aplicado, sem aviso.
     * Esportes incide sobre o subtotal (base × mult), não sobre a base pura.
     */
    public function test_esportes_aventura_aplicado_corretamente_para_adulto_elegivel(): void
    {
        // Nasce 1996-01-01, início 2026-06-01 → 30 anos → ×1.0
        // NACIONAL, 1 dia → 5 cobrados · base = 10 × 5 = 50
        // ESPORTES: 50 + (50 × 0.25) = 62.50
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-01',
            [[
                'nome'            => 'Gustavo',
                'data_nascimento' => '1996-01-01',
                'adicionais'      => ['ESPORTES_AVENTURA'],
            ]]
        );

        $this->assertEqualsWithDelta(62.50, $resultado['total_final'], 0.001);
        $this->assertContains('ESPORTES_AVENTURA', $resultado['viajantes'][0]['adicionais_aplicados']);
        $this->assertEmpty($resultado['avisos']);
    }

    // -------------------------------------------------------------------------
    // 4. ADD-ON BAGAGEM
    // -------------------------------------------------------------------------

    /**
     * Bagagem deve ser R$3,00 × dias_cobrados (não um valor fixo).
     * Testamos com 10 dias para deixar explícito que multiplica pelos dias.
     */
    public function test_bagagem_multiplica_pelo_numero_de_dias_cobrados(): void
    {
        // NACIONAL, 01/06 → 10/06 = 10 dias · adulto · base = 10 × 10 = 100
        // BAGAGEM = 3 × 10 = 30 → total = 130.00
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-10',
            [[
                'nome'            => 'Helena',
                'data_nascimento' => '1990-01-01',
                'adicionais'      => ['BAGAGEM'],
            ]]
        );

        $this->assertEqualsWithDelta(130.00, $resultado['total_final'], 0.001);
        $this->assertContains('BAGAGEM', $resultado['viajantes'][0]['adicionais_aplicados']);
    }

    /**
     * Esportes incide ANTES da bagagem (sobre base×mult).
     * Ordem correta: base=50 → esportes +12.50=62.50 → bagagem +15=77.50.
     * Se invertido: base=50 → bagagem +15=65 → esportes 25%=81.25 (errado).
     */
    public function test_esportes_incide_sobre_subtotal_antes_da_bagagem(): void
    {
        // NACIONAL 1 dia → 5 cobrados · adulto ×1.0
        // base = 10 × 5 = 50
        // esportes: 50 × 0.25 = 12.50 → 62.50
        // bagagem:  3 × 5 = 15.00     → 77.50
        $resultado = $this->service->quote(
            'NACIONAL',
            '2026-06-01',
            '2026-06-01',
            [[
                'nome'            => 'Igor',
                'data_nascimento' => '1990-01-01',
                'adicionais'      => ['ESPORTES_AVENTURA', 'BAGAGEM'],
            ]]
        );

        $this->assertEqualsWithDelta(77.50, $resultado['total_final'], 0.001);
    }

    // -------------------------------------------------------------------------
    // 5. DESCONTO DE GRUPO
    // -------------------------------------------------------------------------

    /** 4 viajantes = sem desconto (0%). */
    public function test_desconto_grupo_nao_aplicado_com_quatro_viajantes(): void
    {
        // 4 × (10 × 5 × 1.0) = 200.00 · 0% desconto
        $viajantes = array_fill(0, 4, [
            'nome'            => 'Viajante',
            'data_nascimento' => '1990-01-01',
            'adicionais'      => [],
        ]);

        $resultado = $this->service->quote('NACIONAL', '2026-06-01', '2026-06-01', $viajantes);

        $this->assertSame(0, $resultado['desconto_grupo_percentual']);
        $this->assertEqualsWithDelta(200.00, $resultado['total_final'], 0.001);
    }

    /** 5 viajantes = 10% de desconto sobre o total do grupo. */
    public function test_desconto_grupo_aplicado_com_cinco_viajantes(): void
    {
        // 5 × (10 × 5 × 1.0) = 250.00 · desconto 10% = 25 → total 225.00
        $viajantes = array_fill(0, 5, [
            'nome'            => 'Viajante',
            'data_nascimento' => '1990-01-01',
            'adicionais'      => [],
        ]);

        $resultado = $this->service->quote('NACIONAL', '2026-06-01', '2026-06-01', $viajantes);

        $this->assertSame(10, $resultado['desconto_grupo_percentual']);
        $this->assertEqualsWithDelta(225.00, $resultado['total_final'], 0.001);
    }

    // -------------------------------------------------------------------------
    // 6. TARIFAS POR ZONA
    // -------------------------------------------------------------------------

    /** Verifica as três zonas com cálculo simples (1 adulto, 5 dias, sem add-ons). */
    public function test_tarifas_por_zona_nacional_americas_europa(): void
    {
        $viajante = [['nome' => 'X', 'data_nascimento' => '1990-01-01', 'adicionais' => []]];

        // NACIONAL: 10 × 5 = 50.00
        $r1 = $this->service->quote('NACIONAL', '2026-06-01', '2026-06-01', $viajante);
        $this->assertEqualsWithDelta(50.00, $r1['total_final'], 0.001);

        // AMERICAS: 16 × 5 = 80.00
        $r2 = $this->service->quote('AMERICAS', '2026-06-01', '2026-06-01', $viajante);
        $this->assertEqualsWithDelta(80.00, $r2['total_final'], 0.001);

        // EUROPA: 22 × 5 = 110.00
        $r3 = $this->service->quote('EUROPA', '2026-06-01', '2026-06-01', $viajante);
        $this->assertEqualsWithDelta(110.00, $r3['total_final'], 0.001);
    }

    // -------------------------------------------------------------------------
    // 7. CENÁRIO COMPLETO — espelha exatamente o exemplo da spec
    // -------------------------------------------------------------------------

    /**
     * EUROPA · 10/07/2026 → 20/07/2026 · dias = (20-10)+1 = 11
     *
     * Ana (1990-03-15, 36 anos, ×1.0) + BAGAGEM + ESPORTES_AVENTURA:
     *   base      = 22 × 11 = 242.00
     *   esportes  = 242 × 0.25 = 60.50 → 302.50
     *   bagagem   = 3 × 11 = 33.00     → subtotal = 335.50
     *
     * João (1948-11-02, 77 anos, ×2.0) + ESPORTES_AVENTURA (negado) + BAGAGEM:
     *   base      = 22 × 11 × 2.0 = 484.00
     *   bagagem   = 3 × 11 = 33.00     → subtotal = 517.00
     *
     * total_grupo = 852.50 · 2 viajantes → 0% · total_final = 852.50
     */
    public function test_cenario_completo_multiplos_viajantes_e_addons(): void
    {
        $resultado = $this->service->quote(
            'EUROPA',
            '2026-07-10',
            '2026-07-20',
            [
                [
                    'nome'            => 'Ana',
                    'data_nascimento' => '1990-03-15',
                    'adicionais'      => ['BAGAGEM', 'ESPORTES_AVENTURA'],
                ],
                [
                    'nome'            => 'João',
                    'data_nascimento' => '1948-11-02',
                    'adicionais'      => ['ESPORTES_AVENTURA', 'BAGAGEM'],
                ],
            ]
        );

        // Estrutura geral
        $this->assertSame(11, $resultado['dias_cobrados']);
        $this->assertSame(0, $resultado['desconto_grupo_percentual']);
        $this->assertEqualsWithDelta(852.50, $resultado['total_final'], 0.001);

        // Ana
        $ana = $resultado['viajantes'][0];
        $this->assertSame('Ana', $ana['nome']);
        $this->assertSame(36, $ana['idade']);
        $this->assertEqualsWithDelta(335.50, $ana['subtotal'], 0.001);
        $this->assertContains('ESPORTES_AVENTURA', $ana['adicionais_aplicados']);
        $this->assertContains('BAGAGEM', $ana['adicionais_aplicados']);

        // João
        $joao = $resultado['viajantes'][1];
        $this->assertSame('João', $joao['nome']);
        $this->assertSame(77, $joao['idade']);
        $this->assertEqualsWithDelta(517.00, $joao['subtotal'], 0.001);
        $this->assertNotContains('ESPORTES_AVENTURA', $joao['adicionais_aplicados']);
        $this->assertContains('BAGAGEM', $joao['adicionais_aplicados']);

        // Aviso do João
        $this->assertCount(1, $resultado['avisos']);
        $this->assertStringContainsString('João', $resultado['avisos'][0]);
        $this->assertStringContainsString('ESPORTES_AVENTURA', $resultado['avisos'][0]);
    }
}
