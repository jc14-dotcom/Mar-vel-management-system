<script setup lang="ts">
defineProps<{
    message?: string;
    id?: string;
}>();
</script>

<template>
    <div v-show="message" :id="id" aria-live="polite" role="alert">
        <p class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>
