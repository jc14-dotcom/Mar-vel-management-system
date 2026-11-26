<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm text-[#16201d] shadow-none focus:border-[#16483d] focus:ring-[#16483d]"
        v-model="model"
        ref="input"
    />
</template>
