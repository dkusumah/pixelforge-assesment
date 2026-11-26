<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Simulated purchase of credits.
     */
    public function topUp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:500'],
        ]);

        $user = $request->user();
        $amountFormatted = number_format((float) $validated['amount'], 8, '.', '');

        DB::transaction(function () use ($user, $amountFormatted) {
            $newBalance = bcadd((string) $user->balance, $amountFormatted, 9);
            $user->update(['balance' => $newBalance]);
        });

        return back()->with('success', "Added \${$validated['amount']} in credits to your balance.");
    }

    /**
     * Get the current user's balance.
     */
    public function getBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'balance' => (string) $request->user()->fresh()->balance,
        ]);
    }
}
