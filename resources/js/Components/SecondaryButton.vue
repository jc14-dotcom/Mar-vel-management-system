<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#d7e2da] bg-white px-5 py-3 text-sm font-semibold text-[#16483d] transition hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] focus-visible:ring-offset-2 disabled:opacity-50"
    >
        <slot />
    </button>
</template>
