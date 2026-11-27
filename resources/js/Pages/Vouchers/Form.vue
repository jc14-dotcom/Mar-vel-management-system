<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useRealtimeUpdates } from '@/useRealtimeUpdates';
import type { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
}

interface VoucherItem {
    description: string;
    amount: string;
    line_type: 'expense' | 'deduction';
}

interface VoucherRecord {
    id: number;
    voucher_number: string;
    voucher_date: string;
    payee: string;
    project_id: number;
    project_name_snapshot: string;
    scope_of_work: string | null;
    expense_category: string | null;
    payment_type: 'full' | 'partial';
    created_by: string;
    prepared_by: string;
    total_amount: string;
    status: 'draft' | 'printed' | 'reprint_required' | 'cancelled';
    record_version: number;
    items: VoucherItem[];
}

interface VoucherFormData {
    record_version: number | null;
    reason: string;
    voucher_date: string;
    payee: string;
    project_id: number | null;
    scope_of_work: string;
    expense_category: string;
    payment_type: 'full' | 'partial';
    prepared_by: string;
    items: VoucherItem[];
}

const props = defineProps<{
    voucher: VoucherRecord | null;
    projects: ProjectOption[];
    preparedBy: string;
    today: string;
}>();

const page = usePage<PageProps>();
const selectedProjectName = (voucher: VoucherRecord | null) =>
    voucher === null
        ? ''
        : props.projects.find((project) => project.id === voucher.project_id)?.name
            ?? voucher.project_name_snapshot;
const projectInput = ref(selectedProjectName(props.voucher));
const projectForm = useForm({ name: '' });
const staleConflict = ref(false);

const form = useForm<VoucherFormData>({
    record_version: props.voucher?.record_version ?? null,
    reason: '',
    voucher_date: props.voucher?.voucher_date ?? props.today,
    payee: props.voucher?.payee ?? '',
    project_id: props.voucher?.project_id ?? null,
    scope_of_work: props.voucher?.scope_of_work ?? '',
    expense_category: props.voucher?.expense_category ?? '',
    payment_type: props.voucher?.payment_type ?? 'full',
    prepared_by: props.voucher?.prepared_by ?? props.preparedBy,
    items: props.voucher?.items.map((item) => ({ ...item })) ?? [
        { description: '', amount: '', line_type: 'expense' },
    ],
});

const cancelForm = useForm({
    record_version: props.voucher?.record_version ?? 0,
    reason: '',
});

const isEditing = computed(() => props.voucher !== null);
const isCancelled = computed(() => props.voucher?.status === 'cancelled');
const needsEditReason = computed(() => ['printed', 'reprint_required'].includes(props.voucher?.status ?? ''));
const canCancel = computed(() => page.props.auth.can.cancelVouchers && isEditing.value && !isCancelled.value);
const statusLabel = (value: string) => value.replace(/_/g, ' ');
const matchingProject = computed(() => {
    const normalizedName = projectInput.value.trim().replace(/\s+/g, ' ').toLowerCase();

    return props.projects.find((project) =>
        project.name.trim().replace(/\s+/g, ' ').toLowerCase() === normalizedName);
});
const canAddProject = computed(() =>
    projectInput.value.trim() !== '' && !matchingProject.value && !projectForm.processing);

const centsFor = (value: string): number => {
    const [whole = '', fractional = ''] = value.trim().split('.');

    if ((whole !== '' && !/^\d+$/.test(whole)) || (fractional !== '' && !/^\d{0,2}$/.test(fractional))) {
        return 0;
    }

    return Number(whole || 0) * 100 + Number((fractional + '00').slice(0, 2));
};

const totalPreview = computed(() => {
    const total = form.items.reduce((sum, item) => {
        const amount = centsFor(item.amount);

        return sum + (item.line_type === 'deduction' ? -amount : amount);
    }, 0);

    return (total / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});

const itemError = (index: number, field: keyof VoucherItem) =>
    Object.entries(form.errors).find(([key]) => key === `items.${index}.${field}`)?.[1];

const addItem = () => {
    form.items.push({ description: '', amount: '', line_type: 'expense' });
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

watch(projectInput, () => {
    form.project_id = matchingProject.value?.id ?? null;
    form.clearErrors('project_id');
});

const addProject = () => {
    projectForm.name = projectInput.value.trim();
    const destination = props.voucher
        ? route('vouchers.edit.projects.store', props.voucher.id)
        : route('vouchers.projects.store');

    projectForm.post(destination, {
        preserveScroll: true,
        onSuccess: () => {
            const createdProject = page.props.flash.createdProject;

            if (createdProject) {
                projectInput.value = createdProject.name;
                form.project_id = createdProject.id;
            }

            projectForm.reset();
        },
    });
};

const submit = () => {
    if (!form.project_id) {
        form.setError('project_id', 'Choose an existing project or add this name before saving.');

        return;
    }

    if (props.voucher) {
        form.patch(route('vouchers.update', props.voucher.id), { preserveScroll: true });

        return;
    }

    form.post(route('vouchers.store'), { preserveScroll: true });
};

const handleInvalidResponse = (event: Event) => {
    const response = (event as CustomEvent<{ response: { status: number } }>).detail.response;

    if (response.status === 409) {
        event.preventDefault();
        staleConflict.value = true;
    }
};

const removeInvalidListener = router.on('invalid', handleInvalidResponse);

const applyFreshVoucher = (voucher: VoucherRecord) => {
    const freshData: VoucherFormData = {
        record_version: voucher.record_version,
        reason: '',
        voucher_date: voucher.voucher_date,
        payee: voucher.payee,
        project_id: voucher.project_id,
        scope_of_work: voucher.scope_of_work ?? '',
        expense_category: voucher.expense_category ?? '',
        payment_type: voucher.payment_type,
        prepared_by: voucher.prepared_by,
        items: voucher.items.map((item) => ({ ...item })),
    };

    form.defaults(freshData);
    form.reset();
    form.clearErrors();
    projectInput.value = selectedProjectName(voucher);
    cancelForm.defaults({ record_version: voucher.record_version, reason: '' });
    cancelForm.reset();
};

const reloadCurrentVoucher = () => {
    if (!props.voucher) {
        return;
    }

    router.reload({
        only: ['voucher', 'projects', 'preparedBy'],
        onSuccess: () => {
            if (props.voucher) {
                applyFreshVoucher(props.voucher);
            }

            staleConflict.value = false;
        },
    });
};

const refreshProjectOptions = () => {
    router.reload({
        only: ['projects'],
    });
};

const refreshAfterReconnect = () => {
    router.reload({
        only: props.voucher ? ['voucher', 'projects', 'preparedBy'] : ['projects', 'preparedBy'],
        onSuccess: () => {
            if (props.voucher && props.voucher.record_version > (form.record_version ?? 0)) {
                staleConflict.value = true;
            }
        },
    });
};

const { onVoucherChanged, onProjectChanged, onReconnect } = useRealtimeUpdates();
onVoucherChanged((event) => {
    if (
        props.voucher
        && event.voucher_id === props.voucher.id
        && event.record_version > (form.record_version ?? 0)
    ) {
        staleConflict.value = true;
    }
});
onProjectChanged(refreshProjectOptions);
onReconnect(refreshAfterReconnect);

const cancelVoucher = () => {
    if (!props.voucher || !window.confirm(`Cancel ${props.voucher.voucher_number}?`)) {
        return;
    }

    cancelForm.post(route('vouchers.cancel', props.voucher.id), { preserveScroll: true });
};

onBeforeUnmount(removeInvalidListener);
</script>

<template>
    <Head :title="isEditing ? `Edit ${voucher?.voucher_number}` : 'New voucher'" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <PageHeader
                :title="isEditing ? 'Edit voucher' : 'New voucher'"
                description="Enter the voucher details and particulars. The saved total is calculated by the server."
            >
                <template #before>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Link :href="route('vouchers.index')" class="inline-flex min-h-10 items-center gap-2 font-semibold text-[#63716c] hover:text-[#16483d]">
                            <Icon name="arrow-left" :size="17" />
                            Vouchers
                        </Link>
                        <template v-if="isEditing && voucher">
                            <span class="text-[#c1cec5]" aria-hidden="true">/</span>
                            <span class="font-medium text-[#63716c]">{{ voucher.voucher_number }}</span>
                        </template>
                    </div>
                </template>
                <template #actions>
                    <span v-if="voucher" class="status-chip">{{ statusLabel(voucher.status) }}</span>
                </template>
            </PageHeader>

            <div v-if="staleConflict" class="mt-6 flex flex-col gap-3 rounded-xl border border-[#d7b76b] bg-[#fff8e7] px-4 py-4 sm:flex-row sm:items-center sm:justify-between" role="alert" aria-live="assertive">
                <div>
                    <h2 class="font-semibold text-[#5b4314]">This voucher changed while you were editing.</h2>
                    <p class="mt-1 text-sm text-[#725d31]">Your unsaved changes are still on this screen. Reload the latest saved version before making another edit.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[#16483d] px-4 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50"
                    :disabled="!voucher"
                    @click="reloadCurrentVoucher"
                >
                    Reload latest voucher
                </button>
            </div>

            <div v-if="page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]" role="status">
                {{ page.props.flash.success }}
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <section class="panel p-5 sm:p-7" aria-labelledby="voucher-details-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="voucher-details-heading" class="text-lg font-semibold text-[#16201d]">Voucher details</h2>
                            <p class="mt-1 text-sm text-[#63716c]">Keep the business date and Prepared By name separate from the account that created the record.</p>
                        </div>
                        <span v-if="voucher" class="hidden rounded-lg bg-[#f3f7f3] px-3 py-2 text-xs font-medium text-[#63716c] sm:inline-flex">
                            Created by {{ voucher.created_by }}
                        </span>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="voucher-date" class="block text-sm font-medium text-[#16201d]">Voucher date</label>
                            <input id="voucher-date" v-model="form.voucher_date" type="date" required :disabled="isCancelled" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                            <InputError class="mt-1" :message="form.errors.voucher_date" />
                        </div>
                        <div>
                            <label for="payee" class="block text-sm font-medium text-[#16201d]">Paid to</label>
                            <input id="payee" v-model="form.payee" type="text" maxlength="255" required :disabled="isCancelled" autocomplete="organization" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                            <InputError class="mt-1" :message="form.errors.payee" />
                        </div>
                        <div>
                            <label for="project-name" class="block text-sm font-medium text-[#16201d]">Project</label>
                            <input
                                id="project-name"
                                v-model="projectInput"
                                list="voucher-project-options"
                                type="text"
                                maxlength="255"
                                required
                                :disabled="isCancelled"
                                aria-describedby="project-name-help project-name-error"
                                :aria-invalid="Boolean(form.errors.project_id)"
                                autocomplete="off"
                                class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]"
                            >
                            <datalist id="voucher-project-options">
                                <option v-for="project in projects" :key="project.id" :value="project.name" />
                            </datalist>
                            <p id="project-name-help" class="mt-1 text-xs text-[#63716c]">Choose a shared name or add a new one to the directory.</p>
                            <InputError id="project-name-error" class="mt-1" :message="form.errors.project_id" />
                            <button
                                v-if="canAddProject && !isCancelled"
                                type="button"
                                class="mt-2 inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50"
                                :disabled="projectForm.processing"
                                @click="addProject"
                            >
                                <span aria-hidden="true">+</span>
                                Add “{{ projectInput.trim() }}” to projects
                            </button>
                            <InputError class="mt-1" :message="projectForm.errors.name" />
                        </div>
                        <div>
                            <label for="prepared-by" class="block text-sm font-medium text-[#16201d]">Prepared by</label>
                            <input id="prepared-by" v-model="form.prepared_by" type="text" maxlength="255" required :disabled="isCancelled" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                            <InputError class="mt-1" :message="form.errors.prepared_by" />
                        </div>
                        <div>
                            <label for="scope-of-work" class="block text-sm font-medium text-[#16201d]">Scope of work</label>
                            <textarea id="scope-of-work" v-model="form.scope_of_work" rows="3" maxlength="2000" :disabled="isCancelled" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]" />
                            <InputError class="mt-1" :message="form.errors.scope_of_work" />
                        </div>
                        <div>
                            <label for="expense-category" class="block text-sm font-medium text-[#16201d]">Expense category</label>
                            <textarea id="expense-category" v-model="form.expense_category" rows="3" maxlength="2000" :disabled="isCancelled" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]" />
                            <InputError class="mt-1" :message="form.errors.expense_category" />
                        </div>
                        <fieldset class="sm:col-span-2">
                            <legend class="text-sm font-medium text-[#16201d]">Payment</legend>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#d7e2da] px-4 text-sm text-[#16201d]">
                                    <input v-model="form.payment_type" type="radio" value="full" :disabled="isCancelled" class="border-[#9aaba0] text-[#16483d] focus:ring-[#16483d]">
                                    Full
                                </label>
                                <label class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-[#d7e2da] px-4 text-sm text-[#16201d]">
                                    <input v-model="form.payment_type" type="radio" value="partial" :disabled="isCancelled" class="border-[#9aaba0] text-[#16483d] focus:ring-[#16483d]">
                                    Partial
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors.payment_type" />
                        </fieldset>
                    </div>
                </section>

                <section class="panel overflow-hidden" aria-labelledby="particulars-heading">
                    <div class="flex flex-col justify-between gap-3 border-b border-[#e1e9e3] px-5 py-5 sm:flex-row sm:items-center sm:px-7">
                        <div>
                            <h2 id="particulars-heading" class="text-lg font-semibold text-[#16201d]">Particulars</h2>
                            <p class="mt-1 text-sm text-[#63716c]">Enter positive amounts. Deductions are subtracted by the server.</p>
                        </div>
                        <button v-if="!isCancelled" type="button" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#16483d] px-4 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]" @click="addItem">
                            Add particular
                        </button>
                    </div>
                    <div class="divide-y divide-[#eaf0eb]">
                        <div v-for="(item, index) in form.items" :key="index" class="grid gap-4 px-5 py-5 sm:grid-cols-[minmax(0,1fr)_10rem_10rem_auto] sm:items-end sm:px-7">
                            <div>
                                <label :for="`item-description-${index}`" class="block text-sm font-medium text-[#16201d]">Description</label>
                                <input :id="`item-description-${index}`" v-model="item.description" type="text" maxlength="2000" required :disabled="isCancelled" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                                <InputError class="mt-1" :message="itemError(index, 'description')" />
                            </div>
                            <div>
                                <label :for="`item-amount-${index}`" class="block text-sm font-medium text-[#16201d]">Amount</label>
                                <input :id="`item-amount-${index}`" v-model="item.amount" type="text" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required :disabled="isCancelled" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-right text-sm tabular-nums focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                                <InputError class="mt-1" :message="itemError(index, 'amount')" />
                            </div>
                            <div>
                                <label :for="`item-type-${index}`" class="block text-sm font-medium text-[#16201d]">Line type</label>
                                <select :id="`item-type-${index}`" v-model="item.line_type" :disabled="isCancelled" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-3 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d] disabled:bg-[#f3f7f3]">
                                    <option value="expense">Expense</option>
                                    <option value="deduction">Deduction</option>
                                </select>
                                <InputError class="mt-1" :message="itemError(index, 'line_type')" />
                            </div>
                            <button
                                v-if="!isCancelled"
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-[#8c4135] hover:bg-[#f7ecea] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8c4135] disabled:opacity-50"
                                :disabled="form.items.length === 1"
                                :aria-label="`Remove particular ${index + 1}`"
                                @click="removeItem(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 border-t border-[#e1e9e3] bg-[#f8faf8] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <p class="text-xs text-[#63716c]">Preview only. The saved total is recalculated by the server.</p>
                        <p class="text-lg font-semibold tabular-nums text-[#16201d]">Preview total: {{ totalPreview }}</p>
                    </div>
                    <InputError class="px-5 pb-4 sm:px-7" :message="form.errors.items" />
                </section>

                <section v-if="needsEditReason && !isCancelled" class="panel p-5 sm:p-7" aria-labelledby="edit-reason-heading">
                    <h2 id="edit-reason-heading" class="text-lg font-semibold text-[#16201d]">Reason for this post-print edit</h2>
                    <p class="mt-1 text-sm text-[#63716c]">A new revision will be created for fresh signatures.</p>
                    <label for="edit-reason" class="mt-5 block text-sm font-medium text-[#16201d]">Reason</label>
                    <textarea id="edit-reason" v-model="form.reason" rows="3" maxlength="2000" required class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]" />
                    <InputError class="mt-1" :message="form.errors.reason" />
                </section>

                <div v-if="!isCancelled" class="flex flex-col-reverse justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#16483d] px-6 py-3 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Save voucher' }}
                        </button>
                        <Link :href="route('vouchers.index')" class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold text-[#63716c] hover:bg-[#f3f7f3] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]">
                            Cancel editing
                        </Link>
                        <InputError :message="form.errors.record_version" />
                    </div>
                    <Link v-if="voucher" :href="route('vouchers.history', voucher.id)" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]">
                        View activity history
                    </Link>
                </div>
            </form>

            <section v-if="canCancel" class="mt-8 rounded-2xl border border-[#ead4d0] bg-white p-5 sm:p-7" aria-labelledby="cancel-voucher-heading">
                <div class="max-w-2xl">
                    <h2 id="cancel-voucher-heading" class="text-lg font-semibold text-[#78382f]">Cancel this voucher</h2>
                    <p class="mt-1 text-sm leading-6 text-[#63716c]">The voucher and its number will remain in the history. Cancellation cannot be undone.</p>
                    <form class="mt-5" @submit.prevent="cancelVoucher">
                        <label for="cancellation-reason" class="block text-sm font-medium text-[#16201d]">Reason for cancellation</label>
                        <textarea id="cancellation-reason" v-model="cancelForm.reason" rows="3" maxlength="2000" required class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#8c4135] focus:ring-[#8c4135]" />
                        <InputError class="mt-1" :message="cancelForm.errors.reason" />
                        <button type="submit" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#8c4135] px-5 py-3 text-sm font-semibold text-white hover:bg-[#74372d] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8c4135] disabled:opacity-50" :disabled="cancelForm.processing">
                            {{ cancelForm.processing ? 'Cancelling…' : 'Cancel voucher' }}
                        </button>
                    </form>
                </div>
            </section>

            <div v-if="isCancelled" class="mt-8 rounded-xl border border-[#ead4d0] bg-[#fdf5f3] px-5 py-4 text-sm font-medium text-[#78382f]" role="status">
                This voucher is cancelled and can no longer be edited.
                <Link v-if="voucher" :href="route('vouchers.history', voucher.id)" class="ml-1 underline underline-offset-2">View history</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
