<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    href: string;
    active?: boolean;
}>();

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-3 pt-1 border-b-2 border-indigo-600 dark:border-indigo-400 rounded-t-lg bg-indigo-50 text-sm font-semibold leading-5 text-indigo-700 dark:text-indigo-300 dark:bg-indigo-500/15 focus:outline-none focus:border-indigo-700 focus:text-indigo-800 dark:focus:text-indigo-200 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-3 pt-1 border-b-2 border-transparent rounded-t-lg text-sm font-medium leading-5 text-gray-500 dark:text-gray-400 hover:text-indigo-600 hover:border-indigo-400 hover:bg-indigo-50/60 dark:hover:text-indigo-300 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10 focus:outline-none focus:text-indigo-600 focus:border-indigo-400 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
