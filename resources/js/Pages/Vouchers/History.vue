<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { PageProps } from '@/types';
import { useRealtimeUpdates } from '@/useRealtimeUpdates';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

interface VoucherHeader {
    id: number;
    voucher_number: string;
    status: string;
}

interface Revision {
    id: number;
    revision_number: number;
    snapshot: {
        voucher_date?: string;
        payee?: string;
        project_name_snapshot?: string;
        total_amount?: string;
        items?: Array<{
            description: string;
            amount: string;
            line_type: 'expense' | 'deduction';
        }>;
    };
    reason: string | null;
    printed_at: string | null;
    superseded_at: string | null;
    actor: { name: string };
}

interface ActivityEvent {
    id: number;
    action: string;
    metadata: Record<string, unknown>;
    created_at: string;
    actor: { name: string };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEvents {
    data: ActivityEvent[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
}

const props = defineProps<{
    voucher: VoucherHeader;
    revisions: Revision[];
    events: PaginatedEvents;
}>();

const page = usePage<PageProps>();
const refreshHistory = () => {
    router.reload({
        only: ['voucher', 'revisions', 'events'],
    });
};
const statusLabel = (value: string) => value.replace(/_/g, ' ');
const actionLabel = (action: string) => action
    .replace(/^voucher\./, '')
    .replace(/\./g, ' ')
    .replace(/_/g, ' ');
const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Previous')
    .replace('Next &raquo;', 'Next');
const amountLabel = (amount: string) => Number(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const formatTimestamp = (value: string) => new Date(value).toLocaleString();
const metadataText = (metadata: Record<string, unknown>) => JSON.stringify(metadata, null, 2);

const { onVoucherChanged, onReconnect } = useRealtimeUpdates();
onVoucherChanged((event) => {
    if (event.voucher_id === props.voucher.id) {
        refreshHistory();
    }
});
onReconnect(refreshHistory);
</script>

<template>
    <Head :title="`History · ${voucher.voucher_number}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <Link :href="route('vouchers.index')" prefetch :cache-for="['10s', '30s']" class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-[#63716c] hover:text-[#16483d]">
                        <Icon name="arrow-left" :size="17" />
                        Vouchers
                    </Link>
                    <p class="eyebrow mt-6">Activity record</p>
                    <h1 class="page-title">{{ voucher.voucher_number }}</h1>
                    <p class="mt-3 text-sm text-[#63716c]">Current status: <span class="font-semibold text-[#16201d]">{{ statusLabel(voucher.status) }}</span></p>
                </div>
                <Link
                    v-if="page.props.auth.can.editVouchers"
                    :href="route('vouchers.edit', voucher.id)"
                    prefetch
                    :cache-for="['10s', '30s']"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#16483d] px-5 py-3 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]"
                >
                    Open voucher
                </Link>
            </header>

            <section class="mt-8" aria-labelledby="revision-history-heading">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">Printed versions</p>
                        <h2 id="revision-history-heading" class="mt-2 text-xl font-semibold text-[#16201d]">Revision history</h2>
                    </div>
                </div>

                <div v-if="revisions.length" class="mt-4 space-y-4">
                    <article v-for="revision in revisions" :key="revision.id" class="panel p-5 sm:p-7">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                                <h3 class="font-semibold text-[#16201d]">Revision {{ revision.revision_number }}</h3>
                                <p class="mt-1 text-sm text-[#63716c]">
                                    Recorded by {{ revision.actor.name }}
                                    <span v-if="revision.printed_at"> · Printed {{ formatTimestamp(revision.printed_at) }}</span>
                                </p>
                            </div>
                            <span v-if="revision.superseded_at" class="rounded-full bg-[#f3f7f3] px-3 py-1 text-xs font-semibold text-[#63716c]">Superseded</span>
                            <span v-else-if="revision.printed_at" class="rounded-full bg-[#dce9df] px-3 py-1 text-xs font-semibold text-[#16483d]">Current print</span>
                            <span v-else class="rounded-full bg-[#fff4d8] px-3 py-1 text-xs font-semibold text-[#725d31]">Pending print</span>
                        </div>

                        <p v-if="revision.reason" class="mt-4 rounded-lg bg-[#f8faf8] px-4 py-3 text-sm text-[#16201d]">
                            <span class="font-semibold">Reason:</span> {{ revision.reason }}
                        </p>

                        <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-[#63716c]">Voucher date</dt>
                                <dd class="mt-1 font-medium text-[#16201d]">{{ revision.snapshot.voucher_date ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[#63716c]">Payee</dt>
                                <dd class="mt-1 font-medium text-[#16201d]">{{ revision.snapshot.payee ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[#63716c]">Project</dt>
                                <dd class="mt-1 font-medium text-[#16201d]">{{ revision.snapshot.project_name_snapshot ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[#63716c]">Printed total</dt>
                                <dd class="mt-1 font-medium tabular-nums text-[#16201d]">{{ revision.snapshot.total_amount ? amountLabel(revision.snapshot.total_amount) : '—' }}</dd>
                            </div>
                        </dl>

                        <div v-if="revision.snapshot.items?.length" class="mt-5 overflow-x-auto rounded-xl border border-[#e1e9e3]">
                            <table class="w-full min-w-[32rem] text-left text-sm">
                                <caption class="sr-only">Particulars on revision {{ revision.revision_number }}</caption>
                                <thead class="bg-[#f8faf8] text-xs font-semibold text-[#63716c]">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Particular</th>
                                        <th scope="col" class="px-4 py-3">Line type</th>
                                        <th scope="col" class="px-4 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#eaf0eb]">
                                    <tr v-for="(item, index) in revision.snapshot.items" :key="`${revision.id}-${index}`">
                                        <td class="px-4 py-3 text-[#16201d]">{{ item.description }}</td>
                                        <td class="px-4 py-3 capitalize text-[#63716c]">{{ item.line_type }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-[#16201d]">{{ amountLabel(item.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <div v-else class="panel mt-4 px-5 py-10 text-center">
                    <p class="font-semibold text-[#16201d]">No printed revisions yet</p>
                    <p class="mt-1 text-sm text-[#63716c]">Revision snapshots appear here after a voucher is printed.</p>
                </div>
            </section>

            <section class="mt-10" aria-labelledby="activity-events-heading">
                <p class="eyebrow">Who changed what</p>
                <h2 id="activity-events-heading" class="mt-2 text-xl font-semibold text-[#16201d]">Activity history</h2>

                <div v-if="events.data.length" class="mt-4 divide-y divide-[#eaf0eb] overflow-hidden rounded-2xl border border-[#e1e9e3] bg-white">
                    <article v-for="event in events.data" :key="event.id" class="px-5 py-5 sm:px-7">
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#edf4ee] text-[#16483d]">
                                    <Icon name="file-text" :size="16" />
                                </span>
                                <div>
                                    <h3 class="font-semibold capitalize text-[#16201d]">{{ actionLabel(event.action) }}</h3>
                                    <p class="mt-1 text-sm text-[#63716c]">By {{ event.actor.name }}</p>
                                </div>
                            </div>
                            <time class="text-xs text-[#8a9790]" :datetime="event.created_at">{{ formatTimestamp(event.created_at) }}</time>
                        </div>
                        <p v-if="event.metadata.reason" class="mt-3 text-sm text-[#16201d]">
                            <span class="font-semibold">Reason:</span> {{ event.metadata.reason }}
                        </p>
                        <details class="mt-3">
                            <summary class="min-h-10 cursor-pointer py-2 text-sm font-semibold text-[#16483d] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]">
                                View recorded changes
                            </summary>
                            <pre class="mt-2 overflow-x-auto rounded-xl bg-[#f8faf8] p-4 text-xs leading-5 text-[#3c4b44]">{{ metadataText(event.metadata) }}</pre>
                        </details>
                    </article>
                </div>
                <div v-else class="panel mt-4 px-5 py-10 text-center">
                    <p class="font-semibold text-[#16201d]">No activity recorded</p>
                </div>

                <nav v-if="events.last_page > 1" class="mt-4 flex flex-wrap justify-end gap-1" aria-label="Activity pages">
                    <Link
                        v-for="link in events.links"
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
            </section>
        </div>
    </AuthenticatedLayout>
</template>
