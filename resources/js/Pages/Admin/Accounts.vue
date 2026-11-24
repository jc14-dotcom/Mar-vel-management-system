<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Account {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    roles: { name: string; label: string }[];
}

defineProps<{
    accounts: Account[];
    roles: { name: string; label: string }[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'employee',
});

const submit = () => {
    form.post(route('admin.accounts.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const toggle = (account: Account) => {
    useForm({ is_active: !account.is_active }).patch(route('admin.accounts.status', account.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Accounts & access" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-medium text-[#63716c]">Administration</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-.03em] text-[#16201d]">Accounts & access</h1>
                    <p class="mt-2 text-sm text-[#63716c]">Create individual accounts and keep access visible.</p>
                </div>
                <div class="rounded-full bg-[#dce9df] px-4 py-2 text-sm font-semibold text-[#16483d]">{{ accounts.length }} accounts</div>
            </div>

            <div v-if="$page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]">
                {{ $page.props.flash.success }}
            </div>

            <div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
                <div class="panel overflow-hidden">
                    <div class="border-b border-[#e1e9e3] px-6 py-5"><h2 class="font-semibold text-[#16201d]">People with access</h2><p class="mt-1 text-sm text-[#63716c]">Disabled accounts cannot sign in.</p></div>
                    <div class="divide-y divide-[#eaf0eb]">
                        <div v-for="account in accounts" :key="account.id" class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-3"><p class="font-semibold text-[#16201d]">{{ account.name }}</p><span :class="account.is_active ? 'bg-[#dce9df] text-[#16483d]' : 'bg-[#f3e5e3] text-[#9b4439]'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ account.is_active ? 'Active' : 'Disabled' }}</span></div>
                                <p class="mt-1 text-sm text-[#63716c]">{{ account.email }}</p>
                                <p class="mt-2 text-xs font-medium uppercase tracking-[.12em] text-[#8a9790]">{{ account.roles.map((role) => role.label).join(' · ') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-[#d7e2da] px-3 py-2 text-sm font-semibold text-[#16483d] hover:border-[#16483d]" @click="toggle(account)">{{ account.is_active ? 'Disable access' : 'Restore access' }}</button>
                        </div>
                        <div v-if="accounts.length === 0" class="px-6 py-12 text-center text-sm text-[#63716c]">No accounts yet. Create the first employee account beside this list.</div>
                    </div>
                </div>

                <form class="panel p-6" @submit.prevent="submit">
                    <h2 class="font-semibold text-[#16201d]">Add an account</h2>
                    <p class="mt-1 text-sm leading-5 text-[#63716c]">Set a temporary password of at least 12 characters. The new user must replace it before continuing.</p>
                    <div class="mt-6 space-y-4">
                        <div><label for="name" class="text-sm font-medium text-[#16201d]">Full name</label><input id="name" v-model="form.name" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]" required><InputError class="mt-1" :message="form.errors.name" /></div>
                        <div><label for="email" class="text-sm font-medium text-[#16201d]">Work email</label><input id="email" v-model="form.email" type="email" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]" required><InputError class="mt-1" :message="form.errors.email" /></div>
                        <div><label for="role" class="text-sm font-medium text-[#16201d]">Role</label><select id="role" v-model="form.role" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]"><option v-for="role in roles" :key="role.name" :value="role.name">{{ role.label }}</option></select></div>
                        <div><label for="password" class="text-sm font-medium text-[#16201d]">Temporary password</label><input id="password" v-model="form.password" type="password" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]" minlength="12" required><InputError class="mt-1" :message="form.errors.password" /></div>
                        <div><label for="password_confirmation" class="text-sm font-medium text-[#16201d]">Confirm password</label><input id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-2 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-3 text-sm focus:border-[#16483d] focus:ring-[#16483d]" minlength="12" required></div>
                    </div>
                    <button class="mt-6 w-full rounded-xl bg-[#16483d] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-[#16483d]/15 hover:bg-[#0f382f] disabled:opacity-50" :disabled="form.processing">Create account</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
