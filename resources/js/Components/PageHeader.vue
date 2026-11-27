<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="page-masthead">
        <div class="relative flex flex-col justify-between gap-6 px-6 py-7 sm:px-8 sm:py-8 lg:flex-row lg:items-end">
            <div class="min-w-0">
                <div v-if="$slots.before" class="mb-4">
                    <slot name="before" />
                </div>
                <h1 class="text-[1.9rem] font-semibold leading-[1.05] tracking-[-.04em] text-[#16201d] sm:text-[2.4rem]">
                    {{ title }}
                </h1>
                <p v-if="description || $slots.description" class="mt-3 max-w-2xl text-sm leading-6 text-[#4d5c55]">
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-3">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
