<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { DailyStat, UserStat } from '@/types/pixelforge';

const props = defineProps<{
    dailyStats: DailyStat[];
    userStats: UserStat[];
    remoteUsage: {
        start_date: string;
        end_date: string;
        total: {
            generations: number;
            input_text_tokens: number;
            input_image_tokens: number;
            output_tokens: number;
            reasoning_tokens: number;
            cost_usd: string;
        };
        data: Array<any>;
    };
    reconciled: boolean;
    discrepancies: Record<string, { local_provider_cost: string; remote_provider_cost: string }>;
    filters: {
        start_date: string;
        end_date: string;
    };
}>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

const formatUsd = (val?: string | null) => {
    if (!val) return '$0.000000';
    return `$${parseFloat(val).toFixed(6)}`;
};

const filterDateRange = () => {
    router.get(
        route('admin.usage'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
        },
        { preserveState: true }
    );
};

// Compute aggregate sums
const totalLocalSpend = props.dailyStats.reduce((acc, d) => acc + parseFloat(d.user_spend || '0'), 0);
const totalLocalProviderCost = props.dailyStats.reduce((acc, d) => acc + parseFloat(d.provider_cost || '0'), 0);
const totalLocalProfit = props.dailyStats.reduce((acc, d) => acc + parseFloat(d.profit || '0'), 0);
const totalLocalGenerations = props.dailyStats.reduce((acc, d) => acc + d.generations_count, 0);
</script>

<template>
    <Head title="Operator Usage & Reconciliation Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        Admin Usage & Reconciliation
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Audit provider billing, user spend, and verify reconciliation against GET /v1/usage.
                    </p>
                </div>

                <!-- Date Range Filter -->
                <form @submit.prevent="filterDateRange" class="flex items-center gap-2">
                    <input
                        type="date"
                        v-model="startDate"
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs text-gray-900 dark:text-white"
                    />
                    <span class="text-xs text-gray-400">to</span>
                    <input
                        type="date"
                        v-model="endDate"
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs text-gray-900 dark:text-white"
                    />
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                    >
                        Filter
                    </button>
                </form>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Reconciliation Status Banner -->
                <div
                    :class="reconciled ? 'border-emerald-200 bg-emerald-50 dark:bg-emerald-950/40 dark:border-emerald-800' : 'border-rose-200 bg-rose-50 dark:bg-rose-950/40 dark:border-rose-800'"
                    class="rounded-2xl border p-4 sm:p-6 flex items-start sm:items-center justify-between gap-4 shadow-sm"
                >
                    <div class="flex items-center space-x-3">
                        <div
                            :class="reconciled ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/60' : 'text-rose-600 dark:text-rose-400 bg-rose-100 dark:bg-rose-900/60'"
                            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                        >
                            <svg v-if="reconciled" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ reconciled ? 'Reconciliation Verified (100% Match)' : 'Reconciliation Discrepancy Detected' }}
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                                Local provider records match Pixelforge's official GET /v1/usage source of truth for API key.
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pixelforge Reported Total:</div>
                        <div class="text-sm font-mono font-bold text-gray-900 dark:text-white">
                            {{ formatUsd(remoteUsage?.total?.cost_usd) }}
                        </div>
                    </div>
                </div>

                <!-- Aggregate Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Completed Generations</div>
                        <div class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ totalLocalGenerations }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">User Spend (Gross Billed)</div>
                        <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">${{ totalLocalSpend.toFixed(6) }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pixelforge Cost (COGS)</div>
                        <div class="text-2xl font-extrabold text-gray-700 dark:text-gray-300 mt-1">${{ totalLocalProviderCost.toFixed(6) }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Net Profit (+25% Margin)</div>
                        <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">${{ totalLocalProfit.toFixed(6) }}</div>
                    </div>
                </div>

                <!-- Daily Breakdown Table -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-800 dark:bg-gray-900">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Daily Cost & Profit Audit (UTC)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-950 text-gray-500 dark:text-gray-400 uppercase font-semibold">
                                <tr>
                                    <th class="px-5 py-3">Date (UTC)</th>
                                    <th class="px-5 py-3">Generations</th>
                                    <th class="px-5 py-3">User Spend</th>
                                    <th class="px-5 py-3">Provider Cost</th>
                                    <th class="px-5 py-3">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono">
                                <tr v-for="day in dailyStats" :key="day.date" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                                    <td class="px-5 py-3 font-sans font-medium text-gray-900 dark:text-white">{{ day.date }}</td>
                                    <td class="px-5 py-3">{{ day.generations_count }}</td>
                                    <td class="px-5 py-3 text-indigo-600 dark:text-indigo-400 font-bold">{{ formatUsd(day.user_spend) }}</td>
                                    <td class="px-5 py-3">{{ formatUsd(day.provider_cost) }}</td>
                                    <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold">{{ formatUsd(day.profit) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Breakdown Table -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-800 dark:bg-gray-900">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Customer Breakdown</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-950 text-gray-500 dark:text-gray-400 uppercase font-semibold">
                                <tr>
                                    <th class="px-5 py-3">User</th>
                                    <th class="px-5 py-3">Email</th>
                                    <th class="px-5 py-3">Generations</th>
                                    <th class="px-5 py-3">Total Spend</th>
                                    <th class="px-5 py-3">Provider Cost</th>
                                    <th class="px-5 py-3">Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-mono">
                                <tr v-for="u in userStats" :key="u.user_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                                    <td class="px-5 py-3 font-sans font-medium text-gray-900 dark:text-white">{{ u.user_name }}</td>
                                    <td class="px-5 py-3 font-sans text-gray-500">{{ u.user_email }}</td>
                                    <td class="px-5 py-3">{{ u.generations_count }}</td>
                                    <td class="px-5 py-3 text-indigo-600 dark:text-indigo-400 font-bold">{{ formatUsd(u.user_spend) }}</td>
                                    <td class="px-5 py-3">{{ formatUsd(u.provider_cost) }}</td>
                                    <td class="px-5 py-3 text-emerald-600 dark:text-emerald-400 font-bold">{{ formatUsd(u.profit) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
