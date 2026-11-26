<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CostEstimator from '@/Components/Generation/CostEstimator.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { CostEstimate, TestingKeywordItem, GenerationSize, GenerationQuality } from '@/types/pixelforge';
import axios from 'axios';

const props = defineProps<{
    pricing: Record<string, any>;
    keywords: TestingKeywordItem[];
    sizes: GenerationSize[];
    qualities: GenerationQuality[];
}>();

const page = usePage();
const userBalance = ref(parseFloat(page.props.auth.user.balance || '0'));

const form = useForm({
    prompt: 'A serene mountain lake reflecting snow peaks at dawn, digital painting',
    size: '1024x1024' as GenerationSize,
    quality: 'medium' as GenerationQuality,
    reference_images: [] as string[],
});

const referenceUrlInput = ref('');
const estimate = ref<CostEstimate | null>(null);
const loadingEstimate = ref(false);
let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

const addReferenceImage = () => {
    const url = referenceUrlInput.value.trim();
    if (!url) return;
    if (form.reference_images.length >= 4) return;
    if (form.reference_images.includes(url)) return;

    form.reference_images.push(url);
    referenceUrlInput.value = '';
    fetchEstimate();
};

const removeReferenceImage = (index: number) => {
    form.reference_images.splice(index, 1);
    fetchEstimate();
};

const appendKeyword = (keyword: string) => {
    if (!form.prompt.includes(keyword)) {
        form.prompt = form.prompt.trim() + ` ${keyword}`;
        fetchEstimate();
    }
};

const fetchEstimate = () => {
    if (!form.prompt.trim()) {
        estimate.value = null;
        return;
    }

    loadingEstimate.value = true;
    if (debounceTimeout) clearTimeout(debounceTimeout);

    debounceTimeout = setTimeout(async () => {
        try {
            const response = await axios.post('/generations/estimate', {
                prompt: form.prompt,
                size: form.size,
                quality: form.quality,
                reference_images_count: form.reference_images.length,
            });
            estimate.value = response.data;
        } catch (err) {
            console.error('Failed to calculate estimate', err);
        } finally {
            loadingEstimate.value = false;
        }
    }, 250);
};

watch(() => [form.prompt, form.size, form.quality], () => {
    fetchEstimate();
});

onMounted(() => {
    fetchEstimate();
});

const submit = () => {
    form.post(route('generations.store'));
};
</script>

<template>
    <Head title="Create Generation" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        Image Generation Studio
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Create AI images powered by Pixelforge with real-time token and price calculation.
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Your Credits:</span>
                    <span
                        :class="parseFloat(page.props.auth.user.balance || '0') <= 0
                            ? 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 animate-pulse'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800'"
                        class="inline-flex items-center rounded-lg px-3 py-1 text-sm font-bold border"
                    >
                        ${{ parseFloat(page.props.auth.user.balance || '0').toFixed(4) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Zero Balance Alert Banner -->
                <div
                    v-if="parseFloat(page.props.auth.user.balance || '0') <= 0"
                    class="rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40 p-4 flex items-center justify-between text-rose-800 dark:text-rose-200 text-xs shadow-xs"
                >
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>
                            <strong>Warning:</strong> Your credit balance has hit <strong>$0.00</strong>. Image generation is locked. Please click "+ Buy Credits" in the navigation bar to add funds.
                        </span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left Column: Form Controls -->
                    <div class="lg:col-span-7 space-y-6">
                        <!-- Prompt Card -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-center justify-between mb-2">
                                <label for="prompt" class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Prompt <span class="text-red-500">*</span>
                                </label>
                                <span class="text-xs text-gray-400" :class="{ 'text-red-500 font-medium': form.prompt.length > 4000 }">
                                    {{ form.prompt.length }} / 4,000 characters
                                </span>
                            </div>

                            <textarea
                                id="prompt"
                                v-model="form.prompt"
                                rows="4"
                                maxlength="4000"
                                required
                                placeholder="Describe the image you want to generate in detail..."
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-indigo-400 text-sm"
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.prompt" />

                            <!-- Testing Helper Keywords -->
                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-2">
                                    Testing Keywords (Click to add):
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="kw in keywords"
                                        :key="kw.keyword"
                                        type="button"
                                        @click="appendKeyword(kw.keyword)"
                                        :title="kw.description"
                                        class="inline-flex items-center rounded-md bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 px-2.5 py-1 text-xs font-mono text-gray-700 transition dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-indigo-950 dark:hover:text-indigo-300"
                                    >
                                        {{ kw.keyword }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Size & Aspect Ratio -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                Image Dimensions & Ratio
                            </label>
                            <div class="grid grid-cols-3 gap-3">
                                <button
                                    type="button"
                                    @click="form.size = '1024x1024'"
                                    :class="form.size === '1024x1024' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300'"
                                    class="rounded-xl border p-3 flex flex-col items-center justify-center text-center transition"
                                >
                                    <div class="w-8 h-8 rounded border-2 border-current mb-2 opacity-80"></div>
                                    <span class="text-xs font-bold">Square</span>
                                    <span class="text-[11px] opacity-70">1024×1024 (1:1)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="form.size = '1536x1024'"
                                    :class="form.size === '1536x1024' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300'"
                                    class="rounded-xl border p-3 flex flex-col items-center justify-center text-center transition"
                                >
                                    <div class="w-10 h-7 rounded border-2 border-current mb-2 opacity-80"></div>
                                    <span class="text-xs font-bold">Landscape</span>
                                    <span class="text-[11px] opacity-70">1536×1024 (3:2)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="form.size = '1024x1536'"
                                    :class="form.size === '1024x1536' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300'"
                                    class="rounded-xl border p-3 flex flex-col items-center justify-center text-center transition"
                                >
                                    <div class="w-7 h-10 rounded border-2 border-current mb-2 opacity-80"></div>
                                    <span class="text-xs font-bold">Portrait</span>
                                    <span class="text-[11px] opacity-70">1024×1536 (2:3)</span>
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.size" />
                        </div>

                        <!-- Quality Selection -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                Generation Quality
                            </label>
                            <div class="grid grid-cols-3 gap-3">
                                <label
                                    v-for="q in qualities"
                                    :key="q"
                                    :class="form.quality === q ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300'"
                                    class="rounded-xl border p-3 cursor-pointer text-center capitalize transition font-medium text-xs flex flex-col justify-center"
                                >
                                    <input type="radio" v-model="form.quality" :value="q" class="sr-only" />
                                    <span class="font-bold text-sm">{{ q }}</span>
                                    <span class="text-[11px] opacity-75 mt-0.5">
                                        {{ q === 'low' ? 'Fast / Draft' : q === 'medium' ? 'Standard (Default)' : 'High Detail & Thought' }}
                                    </span>
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors.quality" />
                        </div>

                        <!-- Reference Images -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Style Reference Images (Optional)
                                </label>
                                <span class="text-xs text-gray-400">
                                    {{ form.reference_images.length }} / 4 images (+765 tokens each)
                                </span>
                            </div>

                            <div v-if="form.reference_images.length < 4" class="flex gap-2">
                                <input
                                    type="url"
                                    v-model="referenceUrlInput"
                                    placeholder="https://example.com/reference-style.jpg"
                                    class="flex-1 rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white text-xs"
                                    @keydown.enter.prevent="addReferenceImage"
                                />
                                <button
                                    type="button"
                                    @click="addReferenceImage"
                                    class="rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 px-3.5 py-2 text-xs font-semibold text-gray-800 dark:text-gray-200 transition"
                                >
                                    Add
                                </button>
                            </div>

                            <div v-if="form.reference_images.length > 0" class="mt-3 space-y-2">
                                <div
                                    v-for="(url, idx) in form.reference_images"
                                    :key="idx"
                                    class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3 py-2 text-xs border border-gray-200 dark:border-gray-700"
                                >
                                    <span class="truncate max-w-[85%] font-mono text-gray-600 dark:text-gray-400">{{ url }}</span>
                                    <button
                                        type="button"
                                        @click="removeReferenceImage(idx)"
                                        class="text-red-500 hover:text-red-700 font-bold ml-2 text-sm"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Cost Estimator & Action -->
                    <div class="lg:col-span-5 space-y-6">
                        <!-- Cost Estimator Widget -->
                        <CostEstimator :estimate="estimate" :loading="loadingEstimate" />

                        <!-- Submission Action -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4">
                            <PrimaryButton
                                :class="{ 'opacity-50 cursor-not-allowed': form.processing || !form.prompt.trim() || parseFloat(page.props.auth.user.balance || '0') <= 0 }"
                                :disabled="form.processing || !form.prompt.trim() || parseFloat(page.props.auth.user.balance || '0') <= 0"
                                class="w-full justify-center py-3 text-sm font-bold shadow-md shadow-indigo-500/20"
                            >
                                <span v-if="form.processing">Submitting to Pixelforge...</span>
                                <span v-else-if="parseFloat(page.props.auth.user.balance || '0') <= 0">Locked: Balance Hit $0.00</span>
                                <span v-else>Generate Image Now</span>
                            </PrimaryButton>

                            <p class="text-[11px] text-center text-gray-400">
                                Requests are asynchronous. You will be redirected to the live monitor to view processing progress.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
