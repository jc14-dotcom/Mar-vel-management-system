<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface PrintTemplateSettings {
    company_name: string;
    form_number: string;
    effective_date: string;
    template_revision: string;
    procedural_notices: string;
}

const props = defineProps<{ settings: PrintTemplateSettings }>();
const form = useForm({ ...props.settings });

const submit = () => {
    form.patch(route('admin.settings.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Print settings" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl">
            <PageHeader title="Print settings" description="Manage the shared form details used by future voucher printouts." />

            <div v-if="$page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]" role="status">
                {{ $page.props.flash.success }}
            </div>

            <form class="panel mt-8 space-y-5 p-6 sm:p-8" @submit.prevent="submit">
                <div>
                    <label for="company-name" class="text-sm font-medium text-[#16201d]">Company name</label>
                    <input id="company-name" v-model="form.company_name" aria-describedby="company-name-error" :aria-invalid="Boolean(form.errors.company_name)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" maxlength="255">
                    <InputError id="company-name-error" class="mt-1" :message="form.errors.company_name" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="form-number" class="text-sm font-medium text-[#16201d]">Form number</label>
                        <input id="form-number" v-model="form.form_number" aria-describedby="form-number-error" :aria-invalid="Boolean(form.errors.form_number)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" maxlength="100" required>
                        <InputError id="form-number-error" class="mt-1" :message="form.errors.form_number" />
                    </div>
                    <div>
                        <label for="effective-date" class="text-sm font-medium text-[#16201d]">Effective date</label>
                        <input id="effective-date" v-model="form.effective_date" type="date" aria-describedby="effective-date-error" :aria-invalid="Boolean(form.errors.effective_date)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]">
                        <InputError id="effective-date-error" class="mt-1" :message="form.errors.effective_date" />
                    </div>
                </div>

                <div>
                    <label for="template-revision" class="text-sm font-medium text-[#16201d]">Form template revision</label>
                    <input id="template-revision" v-model="form.template_revision" aria-describedby="template-revision-error" :aria-invalid="Boolean(form.errors.template_revision)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" maxlength="40" required>
                    <InputError id="template-revision-error" class="mt-1" :message="form.errors.template_revision" />
                </div>

                <div>
                    <label for="procedural-notices" class="text-sm font-medium text-[#16201d]">Procedural notices</label>
                    <textarea id="procedural-notices" v-model="form.procedural_notices" rows="5" maxlength="10000" aria-describedby="procedural-notices-error" :aria-invalid="Boolean(form.errors.procedural_notices)" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]"></textarea>
                    <InputError id="procedural-notices-error" class="mt-1" :message="form.errors.procedural_notices" />
                </div>

                <div class="flex justify-end border-t border-[#e1e9e3] pt-5">
                    <button class="min-h-11 rounded-xl bg-[#16483d] px-5 py-3 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50" :disabled="form.processing">
                        Save print settings
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
