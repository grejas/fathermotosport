<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingOption;
use App\Services\ShippingWeightService;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private ShippingWeightService $weights)
    {
    }

    /**
     * Opciones de envío para un país y un peso.
     *
     * GET /api/v1/shipping/calculate?country_code=BO&weight_kg=3.5
     * Opcional: items=variantUuid:cantidad,variantUuid:cantidad — si viene, el peso
     * se calcula en el servidor con el peso real de cada producto (y se ignora weight_kg).
     *
     * Nunca devuelve 500 por falta de tarifa: responde available=false y options vacío.
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'weight_kg' => ['required_without:items', 'nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'string'],
        ]);

        $countryCode = strtoupper($data['country_code']);
        $items = $this->weights->parseItemsParam($data['items'] ?? null);
        $weight = $items !== []
            ? $this->weights->forItems($items)
            : round((float) $data['weight_kg'], 3);

        if (! Countries::isValid($countryCode)) {
            return $this->sinOpciones($countryCode, $weight, 'El código de país no es válido.');
        }

        $options = ShippingOption::availableFor($countryCode, $weight);

        if ($options->isEmpty()) {
            return $this->sinOpciones(
                $countryCode,
                $weight,
                'No hay opciones de envío configuradas para '.Countries::name($countryCode).' con un peso de '.$weight.' kg.'
            );
        }

        return response()->json([
            'available' => true,
            'country_code' => $countryCode,
            'country_name' => Countries::name($countryCode),
            'weight_kg' => $weight,
            'options' => $options->map(fn (ShippingOption $option) => [
                'id' => $option->id,
                'method_name' => $option->method_name,
                'price' => (string) $option->price,
                'currency' => $option->currency,
                'estimated_days_min' => $option->estimated_days_min,
                'estimated_days_max' => $option->estimated_days_max,
            ])->all(),
        ]);
    }

    private function sinOpciones(string $countryCode, float $weight, string $message): JsonResponse
    {
        return response()->json([
            'available' => false,
            'country_code' => $countryCode,
            'country_name' => Countries::name($countryCode),
            'weight_kg' => $weight,
            'options' => [],
            'message' => $message.' Coordiná el envío por WhatsApp.',
        ]);
    }
}
