<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue';
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { computed } from 'vue';

const page = usePage<PageProps>();

const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? 'there');

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
});

const todayLabel = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader
                title="Overview"
                :description="`${greeting}, ${firstName}. Your operations workspace is ready.`"
            >
                <template #actions>
                    <span class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#cbd9cf] bg-white/75 px-3.5 text-sm font-medium text-[#34483f]">
                        <Icon name="calendar" :size="17" />
                        <span>{{ todayLabel }}</span>
                    </span>
                </template>
            </PageHeader>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(16rem,.8fr)]">
                <section class="flex min-h-64 flex-col items-start justify-between gap-8 rounded-2xl bg-[#16483d] p-6 text-white sm:p-8" aria-labelledby="voucher-workspace-title">
                    <div>
                        <p class="text-sm font-medium text-[#c4ddd0]">Voucher workspace</p>
                        <h2 id="voucher-workspace-title" class="mt-3 text-2xl font-semibold tracking-[-.025em] sm:text-3xl">Shared records, ready when you are.</h2>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-[#d6e9da]">Create a voucher or continue with a saved record. Changes stay tied to an individual account.</p>
                    </div>
                    <Link
                        v-if="page.props.auth.can.viewVouchers"
                        :href="route('vouchers.index')"
                        prefetch
                        :cache-for="['10s', '30s']"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#e8b44e] px-4 text-sm font-semibold text-[#16201d] transition hover:bg-[#f1c96e] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#16483d]"
                    >
                        Open vouchers
                        <Icon name="arrow-right" :size="17" />
                    </Link>
                    <Link
                        v-else-if="page.props.auth.can.createVouchers"
                        :href="route('vouchers.create')"
                        prefetch
                        :cache-for="['10s', '30s']"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#e8b44e] px-4 text-sm font-semibold text-[#16201d] transition hover:bg-[#f1c96e] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#16483d]"
                    >
                        Create a voucher
                        <Icon name="arrow-right" :size="17" />
                    </Link>
                    <p v-else class="text-sm text-[#d6e9da]">Voucher access is not enabled for your account.</p>
                </section>

                <section class="panel flex flex-col justify-center p-6 sm:p-8" aria-labelledby="account-context-title">
                    <h2 id="account-context-title" class="text-lg font-semibold text-[#16201d]">Your account</h2>
                    <dl class="mt-5 divide-y divide-[#e1e9e3]">
                        <div class="flex items-center justify-between gap-4 py-4 first:pt-0">
                            <dt class="text-sm text-[#52635a]">Role</dt>
                            <dd class="text-right text-sm font-semibold text-[#16201d]">{{ $page.props.auth.user?.roles?.[0]?.label ?? 'Employee' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-4 last:pb-0">
                            <dt class="text-sm text-[#52635a]">Access</dt>
                            <dd class="inline-flex items-center gap-2 text-sm font-semibold text-[#16483d]">
                                <span class="h-2 w-2 rounded-full bg-[#2d7957]" aria-hidden="true" />
                                Active
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div class="mt-8 grid gap-3 border-t border-[#dce5de] pt-6 sm:grid-cols-[minmax(12rem,.55fr)_minmax(0,1fr)] sm:gap-8">
                <h2 class="text-base font-semibold text-[#16201d]">Records stay accountable</h2>
                <p class="max-w-3xl text-sm leading-6 text-[#52635a]">Voucher dates, creator identity, editable Prepared By text, revisions, and print history remain distinct. Each action is checked against your account permissions.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
