<script setup lang="ts">
import { computed } from 'vue';
import type { RealtimeConnectionStatus } from '@/useRealtimeUpdates';

const props = defineProps<{
    status: RealtimeConnectionStatus;
}>();

const statusText = computed(() => ({
    connecting: 'Realtime connecting',
    connected: 'Realtime connected',
    reconnecting: 'Realtime reconnecting',
    unavailable: 'Realtime unavailable',
}[props.status]));

const compactStatus = computed(() => ({
    connecting: 'Linking',
    connected: 'Live',
    reconnecting: 'Linking',
    unavailable: 'Offline',
}[props.status]));

const statusColor = computed(() => ({
    connecting: 'bg-[#fff4d8] text-[#725d31]',
    connected: 'bg-[#edf7ef] text-[#16483d]',
    reconnecting: 'bg-[#fff4d8] text-[#725d31]',
    unavailable: 'bg-[#f3f7f3] text-[#63716c]',
}[props.status]));

const indicatorColor = computed(() => ({
    connecting: 'bg-[#b38a34]',
    connected: 'bg-[#2d7957]',
    reconnecting: 'bg-[#b38a34]',
    unavailable: 'bg-[#8a9790]',
}[props.status]));
</script>

<template>
    <div
        class="inline-flex min-h-9 items-center gap-2 rounded-lg px-2.5 text-xs font-medium"
        :class="statusColor"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <span class="h-2 w-2 shrink-0 rounded-full" :class="indicatorColor" aria-hidden="true" />
        <span class="sr-only">{{ statusText }}</span>
        <span class="hidden sm:inline" aria-hidden="true">{{ statusText }}</span>
        <span class="sm:hidden" aria-hidden="true">{{ compactStatus }}</span>
    </div>
</template>
