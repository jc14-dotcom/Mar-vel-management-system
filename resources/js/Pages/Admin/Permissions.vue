<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Permission {
    id: number;
    name: string;
    label: string;
}

interface Role {
    id: number;
    name: string;
    label: string;
    permissions: Permission[];
}

const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
}>();

const form = useForm<{ permissions: number[] }>({ permissions: [] });
const selectedRoleId = ref<number | null>(null);

const selectRole = (role: Role) => {
    selectedRoleId.value = role.id;
    form.permissions = role.permissions.map((permission) => permission.id);
    form.clearErrors();
};

const togglePermission = (permissionId: number) => {
    form.permissions = form.permissions.includes(permissionId)
        ? form.permissions.filter((id) => id !== permissionId)
        : [...form.permissions, permissionId];
};

const save = () => {
    if (selectedRoleId.value === null) return;

    form.patch(route('admin.permissions.roles.update', selectedRoleId.value), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Permissions" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <PageHeader title="Permission sets" description="Choose what each role can do. Changes apply to every account with that role.">
                <template #actions>
                    <div class="rounded-full bg-[#dce9df] px-4 py-2 text-sm font-semibold text-[#16483d]">{{ permissions.length }} permissions</div>
                </template>
            </PageHeader>

            <div v-if="$page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]" aria-live="polite">
                {{ $page.props.flash.success }}
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[280px_1fr]">
                <section class="panel p-4" aria-labelledby="role-list-heading">
                    <div class="px-2 py-2">
                        <h2 id="role-list-heading" class="font-semibold text-[#16201d]">Roles</h2>
                        <p class="mt-1 text-sm text-[#63716c]">Select a role to edit.</p>
                    </div>
                    <div class="mt-3 space-y-2">
                        <button
                            v-for="role in props.roles"
                            :key="role.id"
                            type="button"
                            class="flex min-h-11 w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]"
                            :class="selectedRoleId === role.id ? 'bg-[#16483d] text-white' : 'text-[#16483d] hover:bg-[#edf4ee]'"
                            @click="selectRole(role)"
                        >
                            <span>{{ role.label }}</span>
                            <span class="text-xs font-medium opacity-75">{{ role.permissions.length }}</span>
                        </button>
                    </div>
                </section>

                <section class="panel p-6 sm:p-8" aria-labelledby="permission-list-heading">
                    <template v-if="selectedRoleId !== null">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <p class="text-sm font-medium text-[#63716c]">Editing role</p>
                                <h2 id="permission-list-heading" class="mt-1 text-2xl font-semibold tracking-[-.02em] text-[#16201d]">
                                    {{ props.roles.find((role) => role.id === selectedRoleId)?.label }}
                                </h2>
                            </div>
                            <button type="button" class="min-h-11 rounded-xl bg-[#16483d] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-[#16483d]/15 hover:bg-[#0f382f] disabled:opacity-50" :disabled="form.processing" @click="save">
                                Save permissions
                            </button>
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
                            <label v-for="permission in props.permissions" :key="permission.id" class="flex min-h-16 cursor-pointer items-start gap-3 rounded-xl border border-[#e1e9e3] p-4 transition-colors hover:border-[#16483d] has-[:checked]:border-[#16483d] has-[:checked]:bg-[#f2f8f3]">
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-5 w-5 rounded border-[#b9c9bd] text-[#16483d] focus:ring-[#e8b44e]"
                                    :checked="form.permissions.includes(permission.id)"
                                    @change="togglePermission(permission.id)"
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-[#16201d]">{{ permission.label }}</span>
                                    <span class="mt-1 block text-xs text-[#63716c]">{{ permission.name }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError class="mt-4" :message="form.errors.permissions" />
                    </template>
                    <div v-else class="flex min-h-64 items-center justify-center text-center">
                        <div>
                            <h2 id="permission-list-heading" class="text-lg font-semibold text-[#16201d]">Choose a role</h2>
                            <p class="mt-2 max-w-sm text-sm leading-6 text-[#63716c]">Role permissions stay visible here before you save a change.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
