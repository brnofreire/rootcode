<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class QuoteApiTest extends TestCase
{
    /**
     * Cenário completo da spec via HTTP: POST /api/quotes deve devolver 200
     * com a estrutura esperada e o total_final correto (852.50).
     */
    public function test_post_quotes_retorna_cotacao_completa(): void
    {
        $payload = [
            'destino'     => 'EUROPA',
            'data_inicio' => '2026-07-10',
            'data_fim'    => '2026-07-20',
            'viajantes'   => [
                ['nome' => 'Ana', 'data_nascimento' => '1990-03-15', 'adicionais' => ['BAGAGEM', 'ESPORTES_AVENTURA']],
                ['nome' => 'João', 'data_nascimento' => '1948-11-02', 'adicionais' => ['ESPORTES_AVENTURA', 'BAGAGEM']],
            ],
        ];

        $response = $this->postJson('/api/quotes', $payload);

        $response->assertOk();
        $response->assertJsonPath('dias_cobrados', 11);
        $response->assertJsonPath('desconto_grupo_percentual', 0);
        $response->assertJsonPath('viajantes.0.idade', 36);
        $response->assertJsonPath('viajantes.1.idade', 77);
        $response->assertJsonCount(1, 'avisos');

        // Valores monetários: o JSON não preserva casas decimais (517.00 vira 517),
        // então comparamos numericamente com tolerância.
        $data = $response->json();
        $this->assertEqualsWithDelta(852.50, $data['total_final'], 0.001);
        $this->assertEqualsWithDelta(335.50, $data['viajantes'][0]['subtotal'], 0.001);
        $this->assertEqualsWithDelta(517.00, $data['viajantes'][1]['subtotal'], 0.001);
    }

    /** data_fim anterior a data_inicio deve retornar 422 com mensagem de validação. */
    public function test_post_quotes_valida_data_fim_anterior(): void
    {
        $response = $this->postJson('/api/quotes', [
            'destino'     => 'NACIONAL',
            'data_inicio' => '2026-07-20',
            'data_fim'    => '2026-07-10',
            'viajantes'   => [['nome' => 'X', 'data_nascimento' => '1990-01-01', 'adicionais' => []]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('data_fim');
    }

    /** Destino inválido deve retornar 422. */
    public function test_post_quotes_valida_destino_invalido(): void
    {
        $response = $this->postJson('/api/quotes', [
            'destino'     => 'LUA',
            'data_inicio' => '2026-07-10',
            'data_fim'    => '2026-07-20',
            'viajantes'   => [['nome' => 'X', 'data_nascimento' => '1990-01-01', 'adicionais' => []]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('destino');
    }

    /** Lista de viajantes vazia deve retornar 422. */
    public function test_post_quotes_exige_ao_menos_um_viajante(): void
    {
        $response = $this->postJson('/api/quotes', [
            'destino'     => 'NACIONAL',
            'data_inicio' => '2026-07-10',
            'data_fim'    => '2026-07-20',
            'viajantes'   => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('viajantes');
    }
}
