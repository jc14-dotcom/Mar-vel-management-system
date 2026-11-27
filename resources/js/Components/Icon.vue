<script setup lang="ts">
import { computed } from 'vue';

export type IconName =
    | 'grid'
    | 'folder'
    | 'users'
    | 'shield'
    | 'sliders'
    | 'user'
    | 'chevron-down'
    | 'menu'
    | 'close'
    | 'logout'
    | 'search'
    | 'arrow-left'
    | 'arrow-right'
    | 'calendar'
    | 'pen'
    | 'history'
    | 'file-text';

const props = withDefaults(
    defineProps<{
        name: IconName;
        size?: number;
    }>(),
    {
        size: 20,
    },
);

const paths: Record<IconName, string[]> = {
    grid: [
        'M4 4h6v6H4z',
        'M14 4h6v6h-6z',
        'M4 14h6v6H4z',
        'M14 14h6v6h-6z',
    ],
    folder: [
        'M3.5 7.5A2.5 2.5 0 0 1 6 5h4l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z',
    ],
    users: [
        'M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20',
        'M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
        'M16 4.5a3.5 3.5 0 0 1 0 6.8',
        'M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.35',
    ],
    shield: [
        'm12 3 7 3v5c0 4.5-2.9 8.2-7 10-4.1-1.8-7-5.5-7-10V6l7-3Z',
        'm9 12 2 2 4-4',
    ],
    sliders: [
        'M4 6h16',
        'M4 12h16',
        'M4 18h16',
        'M8 4v4',
        'M15 10v4',
        'M11 16v4',
    ],
    user: [
        'M19 20a7 7 0 0 0-14 0',
        'M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    ],
    'chevron-down': ['m6 9 6 6 6-6'],
    menu: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
    close: ['m6 6 12 12', 'm18 6-12 12'],
    logout: ['M10 17l5-5-5-5', 'M15 12H3', 'M21 19V5a2 2 0 0 0-2-2h-6'],
    search: ['m21 21-4.35-4.35', 'M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z'],
    'arrow-left': ['m15 18-6-6 6-6', 'M9 12h12'],
    'arrow-right': ['m9 18 6-6-6-6', 'M3 12h12'],
    calendar: [
        'M8 2v4',
        'M16 2v4',
        'M3 9h18',
        'M5 4h14a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z',
    ],
    pen: [
        'M12 20h9',
        'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z',
    ],
    history: [
        'M3 12a9 9 0 1 0 2.6-6.4L3 8',
        'M3 3v5h5',
        'M12 7v5l3 2',
    ],
    'file-text': [
        'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z',
        'M14 2v6h6',
        'M8 13h8',
        'M8 17h8',
    ],
};

const iconPaths = computed(() => paths[props.name]);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="path in iconPaths" :key="path" :d="path" />
    </svg>
</template>
