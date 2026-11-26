<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\GenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PixelforgeWebhookController extends Controller
{
    public function __construct(
        protected GenerationService $generationService,
    ) {}

    /**
     * Handle incoming Pixelforge webhooks.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signatureHeader = (string) $request->header('Pixelforge-Signature');
        $secret = (string) config('services.pixelforge.webhook_secret');

        // Verify HMAC-SHA256 signature if secret is configured
        if (!empty($secret)) {
            $signatureParts = [];
            foreach (explode(',', $signatureHeader) as $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
                $signatureParts[$key] = $value;
            }

            $timestamp = (int) ($signatureParts['t'] ?? 0);
            $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

            if (!hash_equals($expected, $signatureParts['v1'] ?? '') || abs(time() - $timestamp) > 300) {
                abort(400, 'Invalid Pixelforge signature.');
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['id'], $event['type'])) {
            abort(400, 'Malformed webhook event.');
        }

        $eventId = (string) $event['id'];
        $eventType = (string) $event['type'];
        $deliveryAttempt = (int) ($request->header('Pixelforge-Delivery-Attempt') ?? 1);

        // Deduplication: if event was already received, return 204 immediately
        if (WebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->noContent();
        }

        $generationData = $event['data']['object'] ?? null;
        $generationId = $generationData['id'] ?? null;

        DB::transaction(function () use ($eventId, $eventType, $generationId, $deliveryAttempt, $event, $generationData) {
            WebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'generation_id' => $generationId,
                'delivery_attempt' => $deliveryAttempt,
                'payload' => $event,
            ]);

            // If ping or not a generation event, no model update needed
            if ($eventType === 'ping' || !is_array($generationData) || empty($generationId)) {
                return;
            }

            // Look up generation by pixelforge_id or by metadata idempotency_key
            $idempotencyKey = $generationData['metadata']['idempotency_key'] ?? null;

            $generation = Generation::where('pixelforge_id', $generationId)->first();

            if (!$generation && !empty($idempotencyKey)) {
                $generation = Generation::where('idempotency_key', $idempotencyKey)->first();
            }

            if ($generation) {
                $this->generationService->syncFromPayload($generation, $generationData);
            } else {
                Log::info("Webhook received for unknown generation {$generationId}; event stored for reconciliation.");
            }
        });

        return response()->noContent();
    }
}
