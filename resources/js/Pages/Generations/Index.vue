<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PaginatedData, Generation, GenerationStatus } from '@/types/pixelforge';
import axios from 'axios';

const props = defineProps<{
    generations: PaginatedData<Generation>;
    filters: {
        status?: string | null;
    };
    statuses: GenerationStatus[];
}>();

const items = ref<Generation[]>([...props.generations.data]);
const selectedImage = ref<Generation | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const formatUsd = (val?: string | null) => {
    if (!val) return '$0.000000';
    return `$${parseFloat(val).toFixed(6)}`;
};

const formatDate = (isoString?: string | null) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleString();
};

const pollActiveGenerations = async () => {
    const pending = items.value.filter(
        (g) => g.status === 'queued' || g.status === 'processing'
    );

    if (pending.length === 0) return;

    for (const gen of pending) {
        try {
            const res = await axios.get(`/generations/${gen.id}/poll`);
            const data = res.data;

            const index = items.value.findIndex((i) => i.id === gen.id);
            if (index !== -1) {
                items.value[index] = {
                    ...items.value[index],
                    status: data.status,
                    image_url: data.image_url,
                    image_url_expires_at: data.image_url_expires_at,
                    actual_cost: data.actual_cost,
                    error_message: data.error_message,
                };
            }

            // Automatically refresh balance in header if changed
            if (data.user_balance !== undefined) {
                window.dispatchEvent(new CustomEvent('balance-updated', { detail: data.user_balance }));
            }
        } catch (err) {
            console.error('Polling error', err);
        }
    }
};

onMounted(() => {
    pollInterval = setInterval(pollActiveGenerations, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const filterByStatus = (status: string | null) => {
    router.get(
        route('generations.index'),
        status ? { status } : {},
        { preserveState: true }
    );
};
</script>

<template>
    <Head title="Generations History" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        Generations History
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Track live status, exact token costs, and download finished artwork.
                    </p>
                </div>
                <div>
                    <Link
                        :href="route('generations.create')"
                        class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-500 focus:outline-none transition"
                    >
                        + Create Generation
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Status Filter Pills -->
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <button
                        @click="filterByStatus(null)"
                        :class="!filters.status ? 'bg-indigo-600 text-white font-semibold' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200'"
                        class="rounded-lg px-3 py-1.5 text-xs transition"
                    >
                        All
                    </button>
                    <button
                        v-for="st in statuses"
                        :key="st"
                        @click="filterByStatus(st)"
                        :class="filters.status === st ? 'bg-indigo-600 text-white font-semibold' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200'"
                        class="rounded-lg px-3 py-1.5 text-xs capitalize transition"
                    >
                        {{ st }}
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="items.length === 0" class="rounded-2xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mx-auto w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">No generations found</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Get started by creating your first image generation.</p>
                    <div class="mt-4">
                        <Link
                            :href="route('generations.create')"
                            class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-500"
                        >
                            Create Generation
                        </Link>
                    </div>
                </div>

                <!-- Generations Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="gen in items"
                        :key="gen.id"
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col justify-between dark:border-gray-800 dark:bg-gray-900 transition hover:shadow-md"
                    >
                        <!-- Top Image / Placeholder Section -->
                        <div class="relative bg-gray-100 dark:bg-gray-950 aspect-video flex items-center justify-center overflow-hidden group">
                            <!-- Image Available -->
                            <img
                                v-if="gen.status === 'succeeded' && gen.image_url"
                                :src="gen.image_url"
                                :alt="gen.prompt"
                                class="w-full h-full object-cover cursor-pointer transition group-hover:scale-105"
                                @click="selectedImage = gen"
                            />

                            <!-- Queued or Processing Spinner -->
                            <div v-else-if="gen.status === 'queued' || gen.status === 'processing'" class="text-center p-6 space-y-3">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent dark:border-indigo-400"></div>
                                <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 capitalize">
                                    {{ gen.status }} in background...
                                </div>
                                <div class="text-[11px] text-gray-400">Status refreshes automatically</div>
                            </div>

                            <!-- Failed State -->
                            <div v-else-if="gen.status === 'failed'" class="text-center p-6 space-y-2 text-rose-500">
                                <svg class="w-8 h-8 mx-auto opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="text-xs font-bold block">Generation Failed</span>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block max-w-xs truncate">
                                    {{ gen.error_message || gen.error_code || 'Unbilled' }}
                                </span>
                            </div>

                            <!-- Status Badge Overlay -->
                            <div class="absolute top-3 left-3">
                                <span
                                    v-if="gen.status === 'succeeded'"
                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300/40 shadow-sm"
                                >
                                    ● Succeeded
                                </span>
                                <span
                                    v-else-if="gen.status === 'processing'"
                                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 border border-blue-300/40 shadow-sm"
                                >
                                    ● Processing
                                </span>
                                <span
                                    v-else-if="gen.status === 'queued'"
                                    class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300/40 shadow-sm"
                                >
                                    ● Queued
                                </span>
                                <span
                                    v-else-if="gen.status === 'failed'"
                                    class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300/40 shadow-sm"
                                >
                                    ● Failed ($0.00)
                                </span>
                            </div>

                            <!-- Dimension & Quality pill -->
                            <div class="absolute top-3 right-3">
                                <span class="rounded-md bg-black/60 backdrop-blur-sm px-2 py-0.5 text-[11px] font-mono text-white">
                                    {{ gen.size }} · {{ gen.quality }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <div>
                                <p class="text-xs font-medium text-gray-900 dark:text-gray-100 line-clamp-2" :title="gen.prompt">
                                    "{{ gen.prompt }}"
                                </p>
                            </div>

                            <!-- Cost Audit Box -->
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-3 text-xs border border-gray-100 dark:border-gray-800 space-y-1">
                                <div class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Estimated Upfront:</span>
                                    <span class="font-mono text-gray-700 dark:text-gray-300 font-semibold">
                                        {{ formatUsd(gen.estimated_cost) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500 dark:text-gray-400">Actual Charged:</span>
                                    <span
                                        v-if="gen.status === 'failed'"
                                        class="font-mono text-emerald-600 dark:text-emerald-400 font-bold"
                                    >
                                        $0.000000 (Free)
                                    </span>
                                    <span
                                        v-else-if="gen.actual_cost"
                                        class="font-mono text-indigo-600 dark:text-indigo-400 font-bold"
                                    >
                                        {{ formatUsd(gen.actual_cost) }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Pending...</span>
                                </div>
                            </div>

                            <!-- Footer Meta -->
                            <div class="flex items-center justify-between text-[11px] text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">
                                <span>{{ formatDate(gen.created_at) }}</span>
                                <span v-if="gen.image_url_expires_at" class="text-amber-600/80 dark:text-amber-400/80">
                                    URL valid 24h
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="generations.links.length > 3" class="flex justify-center pt-6">
                    <nav class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in generations.links"
                            :key="i"
                            :href="link.url || '#'"
                            :class="[
                                link.active ? 'bg-indigo-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            class="rounded-lg px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-700"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>

        <!-- Image Lightbox Modal -->
        <div
            v-if="selectedImage"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="selectedImage = null"
        >
            <div class="relative max-w-4xl w-full bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-2xl border border-gray-800">
                <button
                    @click="selectedImage = null"
                    class="absolute top-4 right-4 z-10 rounded-full bg-black/60 text-white w-8 h-8 flex items-center justify-center font-bold hover:bg-black"
                >
                    &times;
                </button>
                <img
                    v-if="selectedImage.image_url"
                    :src="selectedImage.image_url"
                    :alt="selectedImage.prompt"
                    class="w-full max-h-[70vh] object-contain bg-black"
                />
                <div class="p-6 space-y-3">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        "{{ selectedImage.prompt }}"
                    </h4>
                    <div class="flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                        <div>Dimensions: <strong class="text-gray-900 dark:text-white">{{ selectedImage.size }}</strong></div>
                        <div>Quality: <strong class="text-gray-900 dark:text-white capitalize">{{ selectedImage.quality }}</strong></div>
                        <div>Actual Charged: <strong class="text-indigo-600 dark:text-indigo-400">{{ formatUsd(selectedImage.actual_cost) }}</strong></div>
                        <div>Provider Cost: <strong class="text-gray-700 dark:text-gray-300">{{ formatUsd(selectedImage.provider_cost) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
