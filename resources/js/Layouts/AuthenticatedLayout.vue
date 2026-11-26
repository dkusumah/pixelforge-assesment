<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const showingTopUpModal = ref(false);
const currentBalance = ref(page.props.auth.user.balance || '0.00000000');
const isBalanceAnimating = ref(false);

const topUpForm = useForm({
    amount: 10,
});

const handleBalanceUpdate = (e: Event) => {
    const customEvent = e as CustomEvent<string>;
    if (customEvent.detail && customEvent.detail !== currentBalance.value) {
        currentBalance.value = customEvent.detail;
        page.props.auth.user.balance = customEvent.detail;
        isBalanceAnimating.value = true;
        setTimeout(() => {
            isBalanceAnimating.value = false;
        }, 1200);
    }
};

onMounted(() => {
    window.addEventListener('balance-updated', handleBalanceUpdate);
});

onUnmounted(() => {
    window.removeEventListener('balance-updated', handleBalanceUpdate);
});

watch(() => page.props.auth.user.balance, (newVal) => {
    if (newVal) {
        currentBalance.value = newVal;
    }
});

const submitTopUp = () => {
    topUpForm.post(route('wallet.top-up'), {
        onSuccess: () => {
            showingTopUpModal.value = false;
        },
    });
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-50/50 dark:bg-gray-950">
            <nav class="border-b border-gray-200/80 bg-white dark:border-gray-800 dark:bg-gray-900 sticky top-0 z-40 backdrop-blur-md bg-white/90 dark:bg-gray-900/90">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('generations.index')" class="flex items-center space-x-2">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-sm">
                                        PX
                                    </div>
                                    <span class="font-extrabold text-gray-900 dark:text-white tracking-tight">Pixelforge</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex">
                                <NavLink
                                    :href="route('generations.index')"
                                    :active="route().current('generations.index')"
                                >
                                    Generations History
                                </NavLink>
                                <NavLink
                                    :href="route('generations.create')"
                                    :active="route().current('generations.create')"
                                >
                                    + Create Image
                                </NavLink>
                                <NavLink
                                    :href="route('admin.usage')"
                                    :active="route().current('admin.usage')"
                                >
                                    Admin Usage & Profit
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center sm:space-x-4">
                            <!-- Credits Pill & Top Up Button -->
                            <div
                                :class="parseFloat(currentBalance || '0') <= 0
                                    ? 'bg-rose-50 dark:bg-rose-950/80 border-rose-300 dark:border-rose-800'
                                    : 'bg-gray-50 dark:bg-gray-800/80 border-gray-200 dark:border-gray-700'"
                                class="flex items-center space-x-2 border rounded-xl px-3 py-1.5 shadow-xs transition"
                            >
                                <span class="text-xs text-gray-500 dark:text-gray-400">Balance:</span>
                                <span
                                    :class="[
                                        parseFloat(currentBalance || '0') <= 0
                                            ? 'text-rose-600 dark:text-rose-400 font-extrabold animate-pulse'
                                            : 'text-emerald-600 dark:text-emerald-400 font-bold',
                                        isBalanceAnimating ? 'scale-110 text-amber-500 bg-amber-50 dark:bg-amber-950/60 rounded px-1' : ''
                                    ]"
                                    class="font-mono text-xs transition-all duration-300 inline-block"
                                >
                                    ${{ parseFloat(currentBalance || '0').toFixed(4) }}
                                </span>
                                <button
                                    type="button"
                                    @click="showingTopUpModal = true"
                                    :class="parseFloat(currentBalance || '0') <= 0
                                        ? 'bg-rose-600 hover:bg-rose-500 text-white font-extrabold'
                                        : 'bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 font-bold'"
                                    class="ml-1 rounded-md px-2 py-0.5 text-[11px] transition"
                                >
                                    + Buy Credits
                                </button>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-2">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-xl border border-transparent bg-transparent px-3 py-2 text-xs font-medium text-gray-700 transition duration-150 ease-in-out hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg class="-me-0.5 ms-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none dark:hover:bg-gray-800"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('generations.index')" :active="route().current('generations.index')">
                            Generations History
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('generations.create')" :active="route().current('generations.create')">
                            + Create Image
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('admin.usage')" :active="route().current('admin.usage')">
                            Admin Usage & Profit
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 pb-3 pt-4 dark:border-gray-800">
                        <div class="px-4 flex justify-between items-center mb-2">
                            <div>
                                <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ $page.props.auth.user.name }}</div>
                                <div class="text-sm font-medium text-gray-500">{{ $page.props.auth.user.email }}</div>
                            </div>
                            <button
                                @click="showingTopUpModal = true"
                                class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white"
                            >
                                Buy Credits
                            </button>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Zero Balance Warning Banner -->
            <div
                v-if="parseFloat(currentBalance || '0') <= 0"
                class="bg-gradient-to-r from-rose-700 via-rose-600 to-red-700 text-white px-4 py-3 text-xs font-semibold shadow-md flex items-center justify-between"
            >
                <div class="mx-auto flex flex-wrap items-center justify-center gap-3">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-white font-bold">!</span>
                    <span>
                        <strong>Warning:</strong> Your credit balance has hit <strong>$0.00</strong>. You cannot generate new images until you add credits.
                    </span>
                    <button
                        type="button"
                        @click="showingTopUpModal = true"
                        class="rounded-lg bg-white text-rose-700 px-3 py-1 text-xs font-extrabold hover:bg-rose-50 transition shadow-sm ml-2"
                    >
                        + Buy Credits Now
                    </button>
                </div>
            </div>

            <!-- Flash Banners -->
            <div v-if="$page.props.flash?.success" class="bg-emerald-600 text-white text-xs font-semibold py-2.5 px-4 text-center shadow-xs">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.warning" class="bg-amber-500 text-white text-xs font-semibold py-2.5 px-4 text-center shadow-xs">
                {{ $page.props.flash.warning }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-rose-600 text-white text-xs font-semibold py-2.5 px-4 text-center shadow-xs">
                {{ $page.props.flash.error }}
            </div>

            <!-- Page Heading -->
            <header class="bg-white border-b border-gray-200/70 shadow-xs dark:bg-gray-900 dark:border-gray-800" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>

        <!-- Buy Credits Modal (Simulated Purchase) -->
        <Modal :show="showingTopUpModal" @close="showingTopUpModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Buy Generation Credits</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Select an amount to simulate adding funds to your balance (no real payment required).
                </p>

                <div class="grid grid-cols-4 gap-2.5 my-6">
                    <button
                        v-for="amt in [5, 10, 25, 50]"
                        :key="amt"
                        type="button"
                        @click="topUpForm.amount = amt"
                        :class="topUpForm.amount === amt ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-bold dark:bg-indigo-950/60 dark:text-indigo-300' : 'border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:text-gray-300'"
                        class="rounded-xl border p-3 text-center transition"
                    >
                        <span class="text-sm block font-extrabold">${{ amt }}</span>
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton @click="showingTopUpModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="topUpForm.processing" @click="submitTopUp">
                        Add ${{ topUpForm.amount }} Credits
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
