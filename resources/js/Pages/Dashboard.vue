<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-medium text-[#63716c]">Tuesday, November 24, 2026</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-.03em] text-[#16201d]">Good morning, {{ $page.props.auth.user?.name.split(' ')[0] ?? 'there' }}.</h1>
                    <p class="mt-2 text-sm text-[#63716c]">Your operations workspace is ready.</p>
                </div>
                <div class="rounded-full bg-[#dce9df] px-4 py-2 text-sm font-semibold text-[#16483d]">Access active</div>
            </div>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <div class="panel p-5">
                    <p class="text-sm text-[#63716c]">Voucher workspace</p>
                    <p class="mt-3 text-2xl font-semibold text-[#16201d]">Shared records</p>
                    <p class="mt-2 text-sm leading-5 text-[#63716c]">Create vouchers and review saved activity.</p>
                    <Link v-if="page.props.auth.can.viewVouchers" :href="route('vouchers.index')" class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-[#16483d] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]">Open vouchers</Link>
                    <Link v-else-if="page.props.auth.can.createVouchers" :href="route('vouchers.create')" class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-[#16483d] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]">Create a voucher</Link>
                </div>
                <div class="panel p-5"><p class="text-sm text-[#63716c]">Your role</p><p class="mt-3 text-2xl font-semibold text-[#16201d]">{{ $page.props.auth.user?.roles?.[0]?.label ?? 'Employee' }}</p><p class="mt-2 text-sm leading-5 text-[#63716c]">Permissions are checked on the server.</p></div>
                <div class="panel p-5"><p class="text-sm text-[#63716c]">Workspace status</p><p class="mt-3 text-2xl font-semibold text-[#16483d]">Ready</p><p class="mt-2 text-sm leading-5 text-[#63716c]">Individual access is enabled for this account.</p></div>
            </div>
            <div class="panel mt-6 p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-semibold text-[#16201d]">What happens here</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-[#63716c]">Mar-vel OMS will keep voucher dates, creators, Prepared By text, revisions, and print history distinct. This first foundation makes sure every future action starts from a known person and a verified permission.</p></div>
                    <span class="hidden rounded-full bg-[#f7edd6] px-3 py-1 text-xs font-semibold text-[#8b641e] sm:inline-flex">Foundation</span>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
