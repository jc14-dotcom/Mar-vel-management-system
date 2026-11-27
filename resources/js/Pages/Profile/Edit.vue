<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl">
            <PageHeader title="Profile settings" description="Keep your identity and sign-in details current for the shared office workspace.">
                <template #actions>
                    <div class="hidden items-center gap-2 text-sm font-medium text-[#63716c] sm:flex">
                        <span class="h-2 w-2 rounded-full bg-[#2e8a5c]" />
                        Account secured
                    </div>
                </template>
            </PageHeader>

            <div v-if="$page.props.flash.warning" class="mt-6 rounded-xl border border-[#e8c978] bg-[#fff8e8] px-4 py-3 text-sm font-medium text-[#785617]" aria-live="polite">
                {{ $page.props.flash.warning }}
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-start">
                <div class="space-y-6">
                    <section class="panel p-6 sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                            class="max-w-2xl"
                        />
                    </section>

                    <section class="panel p-6 sm:p-8">
                        <UpdatePasswordForm class="max-w-2xl" />
                    </section>

                    <section class="panel border-[#ead8d4] p-6 sm:p-8">
                        <DeleteUserForm class="max-w-2xl" />
                    </section>
                </div>

                <aside class="space-y-6 lg:sticky lg:top-28">
                    <section class="panel overflow-hidden">
                        <div class="bg-[#16483d] px-6 py-6 text-white">
                            <p class="text-xs font-semibold uppercase tracking-[.16em] text-[#9fc4a7]">Signed in as</p>
                            <p class="mt-3 text-lg font-semibold">{{ $page.props.auth.user?.name }}</p>
                            <p class="mt-1 break-words text-sm text-[#c4ddd0]">{{ $page.props.auth.user?.email }}</p>
                        </div>
                        <div class="space-y-4 px-6 py-5 text-sm">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 h-2 w-2 rounded-full bg-[#2e8a5c]" />
                                <div>
                                    <p class="font-semibold text-[#16201d]">Individual account</p>
                                    <p class="mt-1 leading-5 text-[#63716c]">Your activity is attributed to your account.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 h-2 w-2 rounded-full bg-[#e8b44e]" />
                                <div>
                                    <p class="font-semibold text-[#16201d]">Permission-aware</p>
                                    <p class="mt-1 leading-5 text-[#63716c]">Access is enforced by the server, not the interface.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-[#d7e2da] bg-[#edf4ee] p-5">
                        <p class="text-sm font-semibold text-[#16483d]">Good account hygiene</p>
                        <p class="mt-2 text-sm leading-5 text-[#63716c]">Use a unique password and keep your email address ready for account recovery.</p>
                    </section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
