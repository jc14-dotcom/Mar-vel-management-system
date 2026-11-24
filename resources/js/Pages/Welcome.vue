<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="Operations portal" />

    <main class="min-h-screen bg-[#f5f7f3] text-[#16201d]">
        <header class="mx-auto flex max-w-[1440px] items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
            <Link href="/" class="flex items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#16483d]">
                <ApplicationLogo class="h-9 w-9 fill-[#16483d] sm:h-10 sm:w-10" />
                <span class="text-base font-semibold tracking-tight text-[#16483d] sm:text-lg">Mar-vel OMS</span>
            </Link>

            <div class="flex items-center gap-3">
                <span class="hidden items-center gap-2 text-sm text-[#63716c] sm:flex">
                    <span class="h-2 w-2 rounded-full bg-[#2f7666]" aria-hidden="true"></span>
                    Office LAN
                </span>
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-xl border border-[#cbd8d0] bg-white px-4 py-2.5 text-sm font-semibold text-[#16483d] hover:border-[#9fb4a6]"
                >
                    Dashboard
                </Link>
                <Link
                    v-else-if="canLogin"
                    :href="route('login')"
                    class="rounded-xl bg-[#16483d] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#0f382f]"
                >
                    Sign in
                </Link>
            </div>
        </header>

        <section class="mx-auto grid min-h-[calc(100vh-82px)] max-w-[1440px] gap-5 px-5 pb-6 sm:px-8 lg:grid-cols-[1.15fr_.85fr] lg:px-12 lg:pb-12">
            <div class="relative overflow-hidden rounded-2xl bg-[#16483d] px-7 py-10 text-white sm:px-10 sm:py-12 lg:flex lg:min-h-[620px] lg:flex-col lg:justify-between lg:px-14 lg:py-14">
                <div class="flex items-center justify-between border-b border-white/15 pb-5">
                    <p class="text-sm font-medium text-[#d5e6d8]">Operations console</p>
                    <p class="font-mono text-xs text-[#a9c1b1]">OMS / 01</p>
                </div>

                <div class="mt-12 max-w-2xl lg:mt-16">
                    <h1 class="text-[clamp(2.5rem,7vw,5.75rem)] font-semibold leading-[.94] tracking-[-.055em]">
                        Control the office record.
                    </h1>
                    <p class="mt-7 max-w-xl text-base leading-7 text-[#d5e6d8] sm:text-lg sm:leading-8">
                        Mar-vel OMS keeps vouchers, projects, print revisions, and account activity in one accountable office workspace.
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-white/15 bg-white/15 sm:grid-cols-3 lg:mt-16">
                    <div class="bg-[#16483d] px-5 py-5">
                        <p class="text-sm font-semibold">Vouchers</p>
                        <p class="mt-1 text-sm text-[#b8d5bf]">Enter and revise</p>
                    </div>
                    <div class="bg-[#16483d] px-5 py-5">
                        <p class="text-sm font-semibold">Projects</p>
                        <p class="mt-1 text-sm text-[#b8d5bf]">Track scope</p>
                    </div>
                    <div class="bg-[#16483d] px-5 py-5">
                        <p class="text-sm font-semibold">Print records</p>
                        <p class="mt-1 text-sm text-[#b8d5bf]">Preserve revisions</p>
                    </div>
                </div>
            </div>

            <aside class="flex min-h-[420px] flex-col rounded-2xl border border-[#dfe7e1] bg-white p-7 shadow-[0_12px_32px_rgba(27,53,45,.06)] sm:p-9 lg:min-h-[620px] lg:p-11">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-[#63716c]">Entry desk</p>
                    <span class="rounded-full border border-[#d4dfd7] px-3 py-1 text-xs font-medium text-[#63716c]">Authorized staff</span>
                </div>

                <div class="my-auto py-10">
                    <p class="text-sm font-medium text-[#63716c]">Access point</p>
                    <h2 class="mt-3 max-w-md text-3xl font-semibold leading-tight tracking-[-.035em] text-[#16201d] sm:text-4xl">
                        Sign in to open today’s work.
                    </h2>
                    <p class="mt-5 max-w-md leading-7 text-[#63716c]">
                        Use the individual account assigned by the office administrator.
                    </p>

                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="mt-8 inline-flex min-h-12 w-full items-center justify-between rounded-xl bg-[#e8b44e] px-5 py-3 text-sm font-semibold text-[#16201d] hover:bg-[#dba43a] sm:w-auto sm:min-w-56"
                    >
                        <span>Continue to sign in</span>
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>

                <div class="border-t border-[#e4ebe6] pt-5 text-sm text-[#63716c]">
                    Private office workspace · Individual access
                </div>
            </aside>
        </section>
    </main>
</template>
