<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { useRealtimeUpdates } from '@/useRealtimeUpdates';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface VoucherRow {
    id: number;
    voucher_number: string;
    voucher_date: string;
    payee: string;
    project_name_snapshot: string;
    expense_category: string | null;
    prepared_by: string;
    total_amount: string;
    status: string;
    record_version: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedVouchers {
    data: VoucherRow[];
    current_page: number;
    from: number | null;
    last_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    vouchers: PaginatedVouchers;
    filters: { search: string; status: string | null };
}>();

const page = usePage<PageProps>();
const search = ref(props.filters.search);
const status = ref(props.filters.status ?? '');
const paginationSummary = computed(() => props.vouchers.total === 0
    ? 'No vouchers found'
    : `Showing ${props.vouchers.from}–${props.vouchers.to} of ${props.vouchers.total}`);

const statusLabel = (value: string) => value.replace(/_/g, ' ');
const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Previous')
    .replace('Next &raquo;', 'Next');

const applyFilters = () => {
    router.get(route('vouchers.index'), {
        search: search.value.trim() || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    applyFilters();
};

const amountLabel = (amount: string) => Number(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const refreshVouchers = () => {
    router.reload({
        only: ['vouchers'],
    });
};

const { onVoucherChanged, onReconnect } = useRealtimeUpdates();
onVoucherChanged(refreshVouchers);
onReconnect(refreshVouchers);
</script>

<template>
    <Head title="Vouchers" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div>
                    <p class="eyebrow">Shared records</p>
                    <h1 class="page-title">Vouchers</h1>
                    <p class="page-lede">Find a voucher or continue working on a saved record.</p>
                </div>
                <Link
                    v-if="page.props.auth.can.createVouchers"
                    :href="route('vouchers.create')"
                    prefetch
                    :cache-for="['10s', '30s']"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#16483d] px-5 py-3 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]"
                >
                    <span aria-hidden="true">+</span>
                    New voucher
                </Link>
            </header>

            <div v-if="page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]" role="status">
                {{ page.props.flash.success }}
            </div>

            <section class="panel mt-8 overflow-hidden" aria-labelledby="voucher-list-heading">
                <div class="flex flex-col gap-5 border-b border-[#e1e9e3] px-5 py-6 sm:px-7">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#edf4ee] text-[#16483d]">
                                    <Icon name="file-text" :size="17" />
                                </span>
                                <h2 id="voucher-list-heading" class="font-semibold text-[#16201d]">Voucher register</h2>
                            </div>
                            <p class="mt-2 text-sm text-[#63716c]">Search by voucher number, payee, project, or category.</p>
                        </div>
                        <p class="text-xs font-semibold text-[#8a9790]">{{ paginationSummary }}</p>
                    </div>

                    <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]" role="search" @submit.prevent="applyFilters">
                        <label for="voucher-search" class="sr-only">Search vouchers</label>
                        <div class="relative min-w-0">
                            <Icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#8a9790]" />
                            <input
                                id="voucher-search"
                                v-model="search"
                                type="search"
                                maxlength="255"
                                placeholder="Number, payee, project, or category"
                                class="min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] pl-10 pr-4 text-sm focus:border-[#16483d] focus:ring-[#16483d]"
                            >
                        </div>
                        <label class="sr-only" for="voucher-status">Filter by status</label>
                        <select
                            id="voucher-status"
                            v-model="status"
                            class="min-h-11 rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]"
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="printed">Printed</option>
                            <option value="reprint_required">Reprint required</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button class="min-h-11 rounded-xl bg-[#16483d] px-5 py-2 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]" type="submit">
                            Search
                        </button>
                    </form>
                </div>

                <div v-if="vouchers.data.length" class="divide-y divide-[#eaf0eb]">
                    <article
                        v-for="voucher in vouchers.data"
                        :key="voucher.id"
                        class="flex flex-col gap-4 px-5 py-5 sm:px-7 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-semibold text-[#16201d]">{{ voucher.voucher_number }}</h3>
                                <span class="status-chip">{{ statusLabel(voucher.status) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-[#63716c]">{{ voucher.payee }} · {{ voucher.voucher_date }}</p>
                            <p class="mt-1 text-sm text-[#63716c]">
                                {{ voucher.project_name_snapshot }}
                                <span v-if="voucher.expense_category"> · {{ voucher.expense_category }}</span>
                            </p>
                        </div>
                        <div class="flex items-center justify-between gap-5 lg:justify-end">
                            <div class="text-right">
                                <p class="font-semibold tabular-nums text-[#16201d]">{{ amountLabel(voucher.total_amount) }}</p>
                                <p class="mt-1 text-xs text-[#63716c]">Prepared by {{ voucher.prepared_by }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <Link
                                    v-if="page.props.auth.can.editVouchers"
                                    :href="route('vouchers.edit', voucher.id)"
                                    prefetch
                                    :cache-for="['10s', '30s']"
                                    class="inline-flex min-h-10 items-center rounded-lg border border-[#d7e2da] px-3 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]"
                                >
                                    Edit
                                </Link>
                                <Link
                                    :href="route('vouchers.history', voucher.id)"
                                    prefetch
                                    :cache-for="['10s', '30s']"
                                    class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-[#63716c] hover:bg-[#f3f7f3] hover:text-[#16483d] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]"
                                >
                                    History
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-16 text-center sm:px-7">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#edf4ee] text-[#16483d]">
                        <Icon name="file-text" />
                    </span>
                    <h3 class="mt-4 font-semibold text-[#16201d]">No vouchers match this search</h3>
                    <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-[#63716c]">
                        Clear a filter or search for a different number, payee, project, or category.
                    </p>
                    <button class="mt-4 min-h-10 rounded-lg px-4 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]" type="button" @click="clearFilters">
                        Clear filters
                    </button>
                </div>

                <div v-if="vouchers.last_page > 1" class="flex flex-col gap-4 border-t border-[#e1e9e3] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <p class="text-sm text-[#63716c]">{{ paginationSummary }}</p>
                    <nav class="flex items-center gap-1" aria-label="Voucher pages">
                        <Link
                            v-for="link in vouchers.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            :aria-current="link.active ? 'page' : undefined"
                            :aria-label="paginationLabel(link.label)"
                            :prefetch="link.url ? ['hover', 'click'] : false"
                            :cache-for="link.url ? ['10s', '30s'] : undefined"
                            class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-2 text-xs font-semibold"
                            :class="link.active ? 'bg-[#16483d] text-white' : link.url ? 'text-[#63716c] hover:bg-[#edf4ee] hover:text-[#16483d]' : 'cursor-not-allowed text-[#c1cec5]'"
                            :preserve-scroll="true"
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </nav>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
