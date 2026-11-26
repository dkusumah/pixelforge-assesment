<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Pixelforge AI Image Studio" />

    <div class="min-h-screen bg-gradient-to-b from-gray-50 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 text-gray-900 dark:text-gray-100 flex flex-col justify-between">
        <!-- Top Navigation -->
        <header class="border-b border-gray-200/80 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md sticky top-0 z-30">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-md shadow-indigo-500/20">
                        PX
                    </div>
                    <div>
                        <span class="font-extrabold text-base tracking-tight text-gray-900 dark:text-white">Pixelforge</span>
                        <span class="ml-2 rounded-md bg-indigo-100 dark:bg-indigo-950 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">Assessment Edition</span>
                    </div>
                </div>

                <nav class="flex items-center space-x-3 text-xs font-semibold">
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('generations.create')"
                            class="rounded-xl bg-indigo-600 px-3.5 py-2 text-white hover:bg-indigo-500 transition shadow-sm"
                        >
                            + Create Image
                        </Link>
                        <Link
                            :href="route('generations.index')"
                            class="rounded-xl bg-gray-100 dark:bg-gray-800 px-3.5 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                        >
                            Generations
                        </Link>
                        <Link
                            :href="route('admin.usage')"
                            class="rounded-xl bg-gray-100 dark:bg-gray-800 px-3.5 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                        >
                            Admin Usage
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="rounded-xl px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-indigo-600 transition"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500 transition shadow-sm"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 sm:py-24 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-950/40 px-3.5 py-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-6 shadow-xs">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Asynchronous AI Image Generation + Exact Token Accounting
                </div>

                <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-gray-900 dark:text-white max-w-4xl mx-auto leading-tight">
                    Generate images with <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">exact upfront pricing</span> & real-time delivery.
                </h1>

                <p class="mt-6 text-base sm:text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    Powered by Laravel, Vue 3, Inertia, and Pixelforge API. Features exact arbitrary-precision arithmetic, live status webhooks, out-of-order idempotency, and automated operator reconciliation.
                </p>

                <!-- Primary CTAs -->
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('generations.create')"
                        class="rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-500 transition"
                    >
                        Launch Image Studio &rarr;
                    </Link>
                    <Link
                        v-else
                        :href="route('register')"
                        class="rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-500 transition"
                    >
                        Get Started (Free $10 Credits) &rarr;
                    </Link>

                    <Link
                        :href="route('generations.index')"
                        class="rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-3.5 text-sm font-bold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                    >
                        View History & Monitor
                    </Link>
                </div>

                <!-- Entry Point Cards Grid -->
                <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
                    <!-- Feature 1: Studio -->
                    <Link
                        :href="route('generations.create')"
                        class="group rounded-3xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 shadow-sm hover:shadow-xl hover:border-indigo-400 dark:hover:border-indigo-600 transition flex flex-col justify-between"
                    >
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-6 text-xl font-bold group-hover:scale-110 transition">
                                🎨
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                                1. Image Studio
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                                Write prompts, pick dimensions (Square, Landscape, Portrait), select quality tiers, add style reference images, and view live estimated costs with the 25% markup calculated upfront.
                            </p>
                        </div>
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-6 inline-flex items-center">
                            Open Studio &rarr;
                        </span>
                    </Link>

                    <!-- Feature 2: Monitor & History -->
                    <Link
                        :href="route('generations.index')"
                        class="group rounded-3xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 shadow-sm hover:shadow-xl hover:border-indigo-400 dark:hover:border-indigo-600 transition flex flex-col justify-between"
                    >
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-6 text-xl font-bold group-hover:scale-110 transition">
                                ⚡
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition">
                                2. Live Monitor & History
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                                Track generations in real time (<span class="font-mono text-amber-600">queued</span>, <span class="font-mono text-blue-600">processing</span>, <span class="font-mono text-emerald-600">succeeded</span>, <span class="font-mono text-rose-600">failed</span>). Audit estimated vs. actual charged costs, preview artwork in high-res, and monitor 24h signed URL lifespans.
                            </p>
                        </div>
                        <span class="text-xs font-bold text-purple-600 dark:text-purple-400 mt-6 inline-flex items-center">
                            View History &rarr;
                        </span>
                    </Link>

                    <!-- Feature 3: Operator Usage & Admin -->
                    <Link
                        :href="route('admin.usage')"
                        class="group rounded-3xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 shadow-sm hover:shadow-xl hover:border-indigo-400 dark:hover:border-indigo-600 transition flex flex-col justify-between"
                    >
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-6 text-xl font-bold group-hover:scale-110 transition">
                                📊
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">
                                3. Operator Audit & Usage
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                                Operator reporting dashboard reconciling daily credit spend, provider costs, and net margins directly against Pixelforge's official <span class="font-mono">GET /v1/usage</span> endpoint.
                            </p>
                        </div>
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-6 inline-flex items-center">
                            Audit Usage &rarr;
                        </span>
                    </Link>
                </div>

                <!-- Testing Keyword Cheat Sheet -->
                <div class="mt-16 rounded-3xl border border-gray-200/80 dark:border-gray-800 bg-white/60 dark:bg-gray-900/60 p-8 text-left shadow-xs backdrop-blur-sm">
                    <div class="flex items-center space-x-2 mb-4">
                        <span class="text-lg">🧪</span>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            Assessment Testing Keywords Reference
                        </h3>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Add any keyword to your prompt in the Image Studio to test specific provider conditions:
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-emerald-600 block">[succeed]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">Guaranteed success</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-rose-600 block">[fail]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">Fails in processing ($0)</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-red-600 block">[blocked]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">400 policy violation</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-amber-600 block">[overloaded]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">503 provider overload</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-blue-600 block">[slow]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">60-90s slow generation</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-3 text-xs border border-gray-200/60 dark:border-gray-700">
                            <span class="font-mono font-bold text-purple-600 block">[duplicate]</span>
                            <span class="text-[11px] text-gray-500 mt-1 block">2x duplicate webhooks</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 dark:border-gray-800 py-6 text-center text-xs text-gray-500 dark:text-gray-400">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>Pixelforge Assessment Project · Built with Laravel {{ laravelVersion }} & PHP {{ phpVersion }}</span>
                <span>Exact Decimal Accounting · Zero Floating-Point Drift</span>
            </div>
        </footer>
    </div>
</template>
