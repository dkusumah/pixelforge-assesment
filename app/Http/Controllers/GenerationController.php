<?php

namespace App\Http\Controllers;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Enums\TestingKeyword;
use App\Exceptions\PixelforgeApiException;
use App\Models\Generation;
use App\Services\GenerationService;
use App\Services\PixelforgeClient;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GenerationController extends Controller
{
    public function __construct(
        protected PixelforgeClient $client,
        protected PricingService $pricingService,
        protected GenerationService $generationService,
    ) {}

    /**
     * Display a listing of the user's generations.
     */
    public function index(Request $request): InertiaResponse
    {
        $statusFilter = $request->query('status');

        $query = $request->user()->generations()->latest();

        if ($statusFilter && in_array($statusFilter, GenerationStatus::values(), true)) {
            $query->where('status', $statusFilter);
        }

        $generations = $query->paginate(12)->withQueryString();

        return Inertia::render('Generations/Index', [
            'generations' => $generations,
            'filters' => [
                'status' => $statusFilter,
            ],
            'statuses' => GenerationStatus::values(),
        ]);
    }

    /**
     * Show the form for creating a new generation.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('Generations/Create', [
            'pricing' => $this->pricingService->getPricing(),
            'keywords' => TestingKeyword::all(),
            'sizes' => GenerationSize::values(),
            'qualities' => GenerationQuality::values(),
        ]);
    }

    /**
     * Live estimate endpoint for frontend estimator.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(GenerationSize::values())],
            'quality' => ['required', Rule::in(GenerationQuality::values())],
            'reference_images_count' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        $estimate = $this->pricingService->estimateCost(
            prompt: $validated['prompt'],
            size: $validated['size'],
            quality: $validated['quality'],
            referenceImagesCount: (int) ($validated['reference_images_count'] ?? 0)
        );

        return response()->json($estimate);
    }

    /**
     * Store a newly created generation in storage and dispatch to Pixelforge.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(GenerationSize::values())],
            'quality' => ['required', Rule::in(GenerationQuality::values())],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'url'],
        ]);

        $user = $request->user();
        $referenceImages = array_values(array_filter($validated['reference_images'] ?? []));

        // Upfront estimation calculation
        $estimate = $this->pricingService->estimateCost(
            prompt: $validated['prompt'],
            size: $validated['size'],
            quality: $validated['quality'],
            referenceImagesCount: count($referenceImages)
        );

        // Check user balance: block immediately if balance has hit zero
        if (bccomp((string) $user->balance, '0.00000000', 9) <= 0) {
            throw ValidationException::withMessages([
                'prompt' => 'Your credit balance has hit $0.000000. You cannot generate new images. Please buy credits to continue.',
            ]);
        }

        // Check user balance against maximum expected user cost
        $maxCost = $estimate['estimated_user_cost']['max'];
        if (bccomp((string) $user->balance, $maxCost, 9) < 0) {
            throw ValidationException::withMessages([
                'prompt' => "Insufficient balance. Estimated max cost is \${$maxCost}, but your current balance is \${$user->balance}. Please buy credits.",
            ]);
        }

        $idempotencyKey = (string) Str::uuid();

        // 1. Create local generation record
        $generation = $user->generations()->create([
            'idempotency_key' => $idempotencyKey,
            'status' => GenerationStatus::Queued,
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_images' => $referenceImages,
            'metadata' => [
                'idempotency_key' => $idempotencyKey,
                'user_id' => (string) $user->id,
            ],
            'estimated_cost' => $estimate['estimated_user_cost']['expected'],
            'rates' => $estimate['rates'],
        ]);

        // 2. Call Pixelforge API
        try {
            $apiPayload = [
                'prompt' => $validated['prompt'],
                'size' => $validated['size'],
                'quality' => $validated['quality'],
                'reference_images' => $referenceImages,
                'metadata' => [
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => (string) $user->id,
                ],
            ];

            $response = $this->client->createGeneration($apiPayload, $idempotencyKey);

            $this->generationService->syncFromPayload($generation, $response);
        } catch (PixelforgeApiException $e) {
            if ($e->isContentPolicyViolation()) {
                $generation->update([
                    'status' => GenerationStatus::Failed,
                    'error_code' => 'content_policy_violation',
                    'error_message' => $e->getMessage(),
                    'actual_cost' => '0.00000000',
                    'provider_cost' => '0.00000000',
                ]);

                throw ValidationException::withMessages([
                    'prompt' => 'Content policy violation: ' . $e->getMessage(),
                ]);
            }

            if ($e->isRetryable()) {
                // Keep generation as queued so polling or background worker can retry
                return redirect()->route('generations.index')->with(
                    'warning',
                    'Pixelforge is currently experiencing high load. Your generation is queued and will retry automatically.'
                );
            }

            $generation->update([
                'status' => GenerationStatus::Failed,
                'error_code' => $e->errorCode ?? 'api_error',
                'error_message' => $e->getMessage(),
                'actual_cost' => '0.00000000',
                'provider_cost' => '0.00000000',
            ]);

            return redirect()->route('generations.index')->with('error', 'Generation error: ' . $e->getMessage());
        }

        return redirect()->route('generations.index')->with('success', 'Generation submitted successfully!');
    }

    /**
     * Poll endpoint to update and return live status for a generation.
     */
    public function poll(Generation $generation, Request $request): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 403);

        // If not terminal and we have a pixelforge_id, poll provider
        if (!$generation->status->isTerminal() && !empty($generation->pixelforge_id)) {
            try {
                $payload = $this->client->getGeneration($generation->pixelforge_id);
                $this->generationService->syncFromPayload($generation, $payload);
            } catch (\Throwable) {
                // Ignore transient polling failure
            }
        }

        return response()->json([
            'id' => $generation->id,
            'pixelforge_id' => $generation->pixelforge_id,
            'status' => $generation->status->value,
            'image_url' => $generation->image_url,
            'image_url_expires_at' => $generation->image_url_expires_at?->toIso8601String(),
            'estimated_cost' => (string) $generation->estimated_cost,
            'actual_cost' => (string) ($generation->actual_cost ?? ''),
            'error_message' => $generation->error_message,
            'is_terminal' => $generation->status->isTerminal(),
            'user_balance' => (string) $request->user()->fresh()->balance,
        ]);
    }
}
