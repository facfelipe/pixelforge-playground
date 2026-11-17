<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\EstimateCost;
use App\Actions\Generation\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function index(Request $request): Response
    {
        $generations = Generation::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Generation $generation): array => $this->generationPayload($generation));

        $hasActive = $generations->contains(
            fn (array $row): bool => in_array($row['status'], [
                GenerationStatus::Queued->value,
                GenerationStatus::Processing->value,
            ], true)
        );

        return Inertia::render('Studio', [
            'generations' => $generations,
            'hasActiveGenerations' => $hasActive,
            'defaults' => [
                'size' => '1024x1024',
                'quality' => 'medium',
            ],
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $createGeneration->handle($request->user(), $request->validated());

        return redirect()->route('studio');
    }

    public function estimate(EstimateGenerationRequest $request, EstimateCost $estimateCost): JsonResponse
    {
        $data = $request->validated();
        $estimate = $estimateCost->handle(
            $data['prompt'] ?? '',
            $data['size'],
            $data['quality'],
            array_values($data['reference_images'] ?? []),
        );

        return response()->json([
            'estimated_user_cost' => $estimate['estimated_user_cost'],
            'estimated_user_cost_max' => $estimate['estimated_user_cost_max'],
            'estimated_user_cost_display' => Money::formatDisplay($estimate['estimated_user_cost']),
            'estimated_user_cost_max_display' => Money::formatDisplay($estimate['estimated_user_cost_max']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generationPayload(Generation $generation): array
    {
        return [
            'uuid' => $generation->uuid,
            'status' => $generation->status->value,
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'estimated_user_cost' => (string) $generation->estimated_user_cost,
            'estimated_user_cost_max' => (string) $generation->estimated_user_cost_max,
            'actual_user_cost' => $generation->actual_user_cost !== null
                ? (string) $generation->actual_user_cost
                : null,
            'estimated_user_cost_display' => Money::formatDisplay((string) $generation->estimated_user_cost),
            'actual_user_cost_display' => $generation->actual_user_cost !== null
                ? Money::formatDisplay((string) $generation->actual_user_cost)
                : null,
            'image_url' => $generation->image_url,
            'error_message' => $generation->error_message,
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }
}
