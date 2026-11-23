<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="soft-grid flex min-h-screen items-center justify-center px-6 py-10">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-[1.05fr_.95fr]">
            <section class="hidden bg-[#16483d] p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <Link href="/" class="flex items-center gap-3">
                    <ApplicationLogo class="h-10 w-10 fill-current text-[#e8b44e]" />
                    <span class="text-xl font-semibold tracking-tight">Mar-vel OMS</span>
                </Link>
                <div>
                    <p class="mb-5 text-sm font-medium uppercase tracking-[.18em] text-[#b8d5bf]">Office operations</p>
                    <h1 class="max-w-md text-4xl font-semibold leading-tight tracking-[-.03em]">A calmer way to keep every voucher accountable.</h1>
                    <p class="mt-6 max-w-md text-base leading-7 text-[#d5e6d8]">A private workspace for the people, projects, and paper records that keep the office moving.</p>
                </div>
                <p class="text-sm text-[#b8d5bf]">LAN workspace · Individual access · Paper-ready records</p>
            </section>
            <section class="p-8 sm:p-12">
                <div class="mb-10 lg:hidden">
                    <Link href="/" class="flex items-center gap-3">
                        <ApplicationLogo class="h-9 w-9 fill-current text-[#16483d]" />
                        <span class="text-xl font-semibold tracking-tight text-[#16483d]">Mar-vel OMS</span>
                    </Link>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>
