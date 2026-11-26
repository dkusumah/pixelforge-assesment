<script setup lang="ts">
import { computed } from 'vue';
import type { CostEstimate } from '@/types/pixelforge';

const props = defineProps<{
    estimate: CostEstimate | null;
    loading: boolean;
}>();

const formatUsd = (val?: string) => {
    if (!val) return '$0.000000';
    const num = parseFloat(val);
    return `$${num.toFixed(6)}`;
};
</script>

<template>
    <div class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/50 via-white to-purple-50/30 p-5 shadow-sm dark:border-indigo-900/40 dark:from-gray-900 dark:via-gray-900 dark:to-indigo-950/20">
        <div class="flex items-center justify-between pb-3 border-b border-indigo-100/60 dark:border-gray-800">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Estimated Upfront Cost</h3>
            </div>
            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                +25% Platform Markup Included
            </span>
        </div>

        <div v-if="loading" class="py-6 text-center text-sm text-gray-500 animate-pulse">
            Calculating token estimates...
        </div>

        <div v-else-if="estimate" class="mt-4 space-y-4">
            <!-- Price Summary Range -->
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-white/70 dark:bg-gray-800/60 p-2.5 border border-gray-100 dark:border-gray-800">
                    <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Min Expected</div>
                    <div class="text-sm font-bold text-gray-700 dark:text-gray-300 mt-0.5">
                        {{ formatUsd(estimate.estimated_user_cost.min) }}
                    </div>
                </div>
                <div class="rounded-lg bg-indigo-600 text-white p-2.5 shadow-sm">
                    <div class="text-[11px] font-medium text-indigo-200 uppercase tracking-wider">Expected Price</div>
                    <div class="text-base font-extrabold mt-0.5">
                        {{ formatUsd(estimate.estimated_user_cost.expected) }}
                    </div>
                </div>
                <div class="rounded-lg bg-white/70 dark:bg-gray-800/60 p-2.5 border border-gray-100 dark:border-gray-800">
                    <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Max Cap</div>
                    <div class="text-sm font-bold text-gray-700 dark:text-gray-300 mt-0.5">
                        {{ formatUsd(estimate.estimated_user_cost.max) }}
                    </div>
                </div>
            </div>

            <!-- Token Breakdown -->
            <div class="rounded-lg bg-gray-50/70 dark:bg-gray-800/40 p-3 text-xs space-y-1.5 border border-gray-100/80 dark:border-gray-800">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Input Text Tokens:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        ~{{ estimate.estimated_tokens.input_text.expected }} ({{ estimate.estimated_tokens.input_text.min }}–{{ estimate.estimated_tokens.input_text.max }})
                    </span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Reference Image Tokens:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ estimate.estimated_tokens.input_image }} ({{ estimate.estimated_tokens.input_image / 765 }} images @ 765 tokens)
                    </span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Output Tokens:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ estimate.estimated_tokens.output }} (fixed)
                    </span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Reasoning Bounds:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ estimate.estimated_tokens.reasoning.min }} to {{ estimate.estimated_tokens.reasoning.max }} tokens
                    </span>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 italic">
                * Failed generations cost $0.00. The final actual cost is recorded upon completion once exact reasoning tokens and prompt variance are finalized.
            </p>
        </div>
    </div>
</template>
