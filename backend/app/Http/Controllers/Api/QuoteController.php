<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Services\TravelPricingService;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    public function __construct(
        private readonly TravelPricingService $service,
    ) {
    }

    /**
     * POST /api/quotes
     *
     * Apenas orquestra: a validação é feita pelo QuoteRequest e o cálculo
     * pelo TravelPricingService. O controller não contém regra de negócio.
     */
    public function store(QuoteRequest $request): JsonResponse
    {
        $dados = $request->validated();

        $cotacao = $this->service->quote(
            $dados['destino'],
            $dados['data_inicio'],
            $dados['data_fim'],
            $dados['viajantes'],
        );

        return response()->json($cotacao);
    }
}
