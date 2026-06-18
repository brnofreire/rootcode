<?php

namespace App\Services;

use Carbon\Carbon;

class TravelPricingService
{
    // Define as tarifas por destino, os dias mínimos, custos adicionais e regras de desconto como constantes para fácil manutenção conforme regra de negócio.
    private const TARIFAS = ['NACIONAL' => 10.0, 'AMERICAS' => 16.0, 'EUROPA' => 22.0];
    private const DIAS_MINIMOS = 5;
    private const CUSTO_BAGAGEM_DIA = 3.0;
    private const PCT_ESPORTES = 0.25;
    private const PCT_DESCONTO_GRUPO = 0.10;
    private const MINIMO_VIAJANTES_DESCONTO = 5;

    public function quote(string $destino, string $dataInicio, string $dataFim, array $viajantes): array
    {
        $inicio = Carbon::parse($dataInicio); // Converte a data de início para um objeto Carbon para manipulação de datas
        $fim    = Carbon::parse($dataFim); // Converte a data de fim para um objeto Carbon para manipulação de datas

        $dias   = $this->diasCobrados($inicio, $fim); // Calcula o número de dias cobrados, garantindo que seja pelo menos o mínimo definido
        $tarifa = self::TARIFAS[$destino]; // Obtém a tarifa base para o destino especificado, assumindo que o destino é válido e está presente no array de tarifas

        $resultadoViajantes = []; // Inicializa um array para armazenar os resultados individuais de cada viajante, incluindo nome, idade, subtotal e adicionais aplicados
        $avisos             = []; // Inicializa um array para armazenar avisos sobre adicionais não aplicados devido a restrições de idade ou outras regras
        $totalGrupo         = 0.0; // Inicializa o total do grupo, que será acumulado com os subtotais de cada viajante

        foreach ($viajantes as $v) {
            $nascimento = Carbon::parse($v['data_nascimento']); // Converte a data de nascimento do viajante para um objeto Carbon para cálculo da idade
            $idade      = $this->idade($nascimento, $inicio); // Calcula a idade do viajante na data de início da viagem, truncando para anos completos
            $mult       = $this->multiplicador($idade); // Obtém o multiplicador de preço com base na idade do viajante, aplicando as regras definidas para diferentes faixas etárias
            $adicionais = $v['adicionais'] ?? []; // Obtém os adicionais solicitados pelo viajante, garantindo que seja um array mesmo que não haja adicionais especificados

            // Em ordem de calculo, para garantir que cada adicional incida sobre o valor correto:
            // Passo 1 – valor da base
            $base = $tarifa * $dias;

            // Passo 2 – aplica multiplicador de faixa etária
            $subtotal = $base * $mult;

            // Passo 3 – ESPORTES_AVENTURA (incide sobre o subtotal antes da bagagem)
            $adicionaisAplicados = [];

            // Verifica se o adicional foi solicitado e se o viajante está na faixa etária permitida
            if (in_array('ESPORTES_AVENTURA', $adicionais)) {
                if ($idade >= 18 && $idade <= 64) {
                    // Aplica o percentual sobre o subtotal atual (que já inclui o multiplicador de idade)
                    $subtotal             += $subtotal * self::PCT_ESPORTES;
                    // Registra que o adicional foi aplicado para este viajante
                    $adicionaisAplicados[] = 'ESPORTES_AVENTURA';
                } else {
                    $avisos[] = "ESPORTES_AVENTURA não aplicado para {$v['nome']}: fora da faixa etária permitida (18-64).";
                }
            }

            // Passo 4 – BAGAGEM (incide sobre os dias, independente de tudo)
            if (in_array('BAGAGEM', $adicionais)) {
                // Aplica o custo fixo por dia, independente do subtotal ou multiplicador
                $subtotal             += self::CUSTO_BAGAGEM_DIA * $dias;
                // Registra que o adicional foi aplicado para este viajante
                $adicionaisAplicados[] = 'BAGAGEM';
            }

            $totalGrupo += $subtotal; // soma com valor não arredondado

            // Armazena resultado para este viajante, com subtotal arredondado para apresentação
            $resultadoViajantes[] = [
                'nome'                 => $v['nome'],
                'idade'                => $idade,
                'subtotal'             => round($subtotal, 2),  // arredondado só na apresentação
                'adicionais_aplicados' => $adicionaisAplicados,
            ];
        }

        // Desconto de grupo (sobre o total, depois de somar todos os viajantes)
        $nViajantes  = count($viajantes);
        // Aplica o desconto percentual se o número de viajantes atingir o mínimo, caso contrário fica em 0%
        $pctDesconto = $nViajantes >= self::MINIMO_VIAJANTES_DESCONTO
            ? self::PCT_DESCONTO_GRUPO // aplica desconto
            : 0.0; // sem desconto

        // Calcula o total final aplicando o desconto sobre o total do grupo, e arredonda para apresentação
        $totalFinal = $totalGrupo - ($totalGrupo * $pctDesconto);

        // Arredonda o total final para 2 casas decimais usando arredondamento comercial (PHP_ROUND_HALF_UP)
        $totalFinal = round($totalFinal, 2, PHP_ROUND_HALF_UP);

        // Retorna o resultado completo, incluindo detalhes por viajante, avisos e o total final
        return [
            'dias_cobrados'             => $dias,
            'viajantes'                 => $resultadoViajantes,
            'avisos'                    => $avisos,
            'desconto_grupo_percentual' => (int) ($pctDesconto * 100),
            'total_final'               => $totalFinal,
        ];
    }

    private function diasCobrados(Carbon $inicio, Carbon $fim): int
    {
        // Ambos os dias contam: 01/06 → 01/06 = 1 dia
        $dias = (int) $inicio->diffInDays($fim) + 1;

        // Garante que o número de dias cobrados seja pelo menos o mínimo definido, retornando o maior valor entre os dias calculados e o mínimo.
        return max(self::DIAS_MINIMOS, $dias);
    }

    // Calcula a idade do viajante na data de início da viagem, truncando para anos completos.
    private function idade(Carbon $nascimento, Carbon $inicio): int
    {
        // Idade calculada na data de início, não hoje.
        // diffInYears no Carbon 3 retorna float; cast para int trunca (correto para anos completos).
        return (int) $nascimento->diffInYears($inicio);
    }

    // Calcula o multiplicador de preço com base na idade do viajante.
    private function multiplicador(int $idade): float
    {
        // Define as faixas etárias e seus respectivos multiplicadores de preço.
        if ($idade <= 17) return 0.5;
        if ($idade <= 64) return 1.0;
        // Para 65 anos ou mais, aplica o multiplicador de 2.0.
        return 2.0;
    }
}
