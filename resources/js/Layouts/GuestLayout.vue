<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="soft-grid flex min-h-screen items-center justify-center px-4 py-5 sm:px-6 sm:py-8">
        <div class="grid w-full max-w-6xl overflow-hidden rounded-2xl border border-[#dfe7e1] bg-white shadow-[0_20px_50px_rgba(27,53,45,.10)] lg:min-h-[680px] lg:grid-cols-[1.05fr_.95fr]">
            <section class="hidden bg-[#16483d] p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <Link href="/" class="flex items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#e8b44e]">
                    <ApplicationLogo class="h-10 w-10 fill-[#e8b44e]" />
                    <span class="text-xl font-semibold tracking-tight">Mar-vel OMS</span>
                </Link>
                <div>
                    <p class="mb-5 text-sm font-medium text-[#b8d5bf]">Office operations</p>
                    <h1 class="max-w-md text-5xl font-semibold leading-[1.02] tracking-[-.045em]">Keep every office record in order.</h1>
                    <p class="mt-6 max-w-md text-base leading-7 text-[#d5e6d8]">A private workspace for the people, projects, and paper records that keep the office moving.</p>
                </div>
                <p class="text-sm text-[#b8d5bf]">LAN workspace · Individual access · Paper-ready records</p>
            </section>
            <section class="p-6 sm:p-10 lg:p-12">
                <div class="mb-8 lg:hidden">
                    <Link href="/" class="flex items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#16483d]">
                        <ApplicationLogo class="h-9 w-9 fill-[#16483d]" />
                        <span class="text-xl font-semibold tracking-tight text-[#16483d]">Mar-vel OMS</span>
                    </Link>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>
