<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    /**
     * Endpoint público de cotação — não há autenticação.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação da requisição de cotação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destino'     => ['required', 'string', 'in:NACIONAL,AMERICAS,EUROPA'],
            'data_inicio' => ['required', 'date'],
            // data_fim deve ser maior ou igual a data_inicio (regra do enunciado)
            'data_fim'    => ['required', 'date', 'after_or_equal:data_inicio'],

            'viajantes'                    => ['required', 'array', 'min:1'],
            'viajantes.*.nome'             => ['required', 'string', 'max:255'],
            // data_nascimento não pode ser depois do início da viagem
            'viajantes.*.data_nascimento'  => ['required', 'date', 'before_or_equal:data_inicio'],
            'viajantes.*.adicionais'       => ['sometimes', 'array'],
            'viajantes.*.adicionais.*'     => ['string', 'in:BAGAGEM,ESPORTES_AVENTURA'],
        ];
    }

    /**
     * Mensagens de validação em português.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destino.required'     => 'O destino é obrigatório.',
            'destino.in'           => 'O destino deve ser NACIONAL, AMERICAS ou EUROPA.',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_inicio.date'     => 'A data de início é inválida.',
            'data_fim.required'    => 'A data de fim é obrigatória.',
            'data_fim.date'        => 'A data de fim é inválida.',
            'data_fim.after_or_equal' => 'A data de fim deve ser maior ou igual à data de início.',
            'viajantes.required'   => 'Informe ao menos um viajante.',
            'viajantes.min'        => 'Informe ao menos um viajante.',
            'viajantes.*.nome.required'            => 'O nome do viajante é obrigatório.',
            'viajantes.*.data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'viajantes.*.data_nascimento.before_or_equal' => 'A data de nascimento não pode ser posterior ao início da viagem.',
            'viajantes.*.adicionais.*.in'          => 'Adicional inválido. Use BAGAGEM ou ESPORTES_AVENTURA.',
        ];
    }
}
