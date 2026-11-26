<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useRealtimeUpdates } from '@/useRealtimeUpdates';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Project {
    id: number;
    name: string;
    merged_into_id: number | null;
    merged_into: { id: number; name: string } | null;
}

interface ProjectOption {
    id: number;
    name: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProjects {
    data: Project[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    projects: PaginatedProjects;
    projectOptions: ProjectOption[];
    activeProjectCount: number;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const selectedProjectId = ref('');
const selectedSourceId = ref('');
const selectedTargetId = ref('');

const addForm = useForm({ name: '' });
const renameForm = useForm({ name: '' });
const mergeForm = useForm({ target_project_id: 0 });

const activeProjects = computed(() => props.projectOptions);
const existingMatch = computed(() => {
    const name = addForm.name.trim().replace(/\s+/g, ' ').toLowerCase();

    return activeProjects.value.find((project) => project.name.toLowerCase() === name);
});

const paginationSummary = computed(() => {
    if (props.projects.total === 0) {
        return 'No projects found';
    }

    return `Showing ${props.projects.from}–${props.projects.to} of ${props.projects.total}`;
});

const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Previous')
    .replace('Next &raquo;', 'Next');

const applySearch = () => {
    router.get(route('projects.index'), {
        search: search.value.trim() || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const selectRenameProject = () => {
    const selectedProject = activeProjects.value.find((project) => project.id === Number(selectedProjectId.value));
    renameForm.name = selectedProject?.name ?? '';
    renameForm.clearErrors();
};

const selectMergeSource = () => {
    if (selectedTargetId.value === selectedSourceId.value) {
        selectedTargetId.value = '';
    }

    mergeForm.clearErrors();
};

const submitProject = () => {
    addForm.post(route('projects.store'), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
};

const renameProject = () => {
    if (!selectedProjectId.value) {
        return;
    }

    renameForm.patch(route('admin.projects.update', selectedProjectId.value), {
        preserveScroll: true,
        onSuccess: () => {
            selectedProjectId.value = '';
            renameForm.reset();
        },
    });
};

const mergeProjects = () => {
    if (!selectedSourceId.value || !selectedTargetId.value) {
        return;
    }

    mergeForm.target_project_id = Number(selectedTargetId.value);
    mergeForm.post(route('admin.projects.merge', selectedSourceId.value), {
        preserveScroll: true,
        onSuccess: () => {
            selectedSourceId.value = '';
            selectedTargetId.value = '';
            mergeForm.reset();
        },
    });
};

const refreshProjects = () => {
    router.reload({
        only: ['projects', 'projectOptions', 'activeProjectCount'],
    });
};

const { onProjectChanged, onReconnect } = useRealtimeUpdates();
onProjectChanged(refreshProjects);
onReconnect(refreshProjects);
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div>
                    <p class="eyebrow">Shared records</p>
                    <h1 class="page-title">Projects</h1>
                    <p class="page-lede">Find a project or add a new name. Existing voucher names will remain unchanged.</p>
                </div>
                <div class="metric-chip">
                    <span class="h-2 w-2 rounded-full bg-[#16483d]" />
                    {{ activeProjectCount }} active projects
                </div>
            </header>

            <div v-if="$page.props.flash.success" class="mt-6 rounded-xl border border-[#b8d5bf] bg-[#edf7ef] px-4 py-3 text-sm font-medium text-[#16483d]" role="status">
                {{ $page.props.flash.success }}
            </div>

            <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <section class="panel overflow-hidden" aria-labelledby="project-list-heading">
                    <div class="flex flex-col gap-5 border-b border-[#e1e9e3] px-6 py-6 sm:px-7">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#edf4ee] text-[#16483d]"><Icon name="folder" :size="17" /></span>
                                    <h2 id="project-list-heading" class="font-semibold text-[#16201d]">Project directory</h2>
                                </div>
                                <p class="mt-2 text-sm text-[#63716c]">Search the shared project names.</p>
                            </div>
                            <p class="text-xs font-semibold uppercase tracking-[.12em] text-[#8a9790]">{{ paginationSummary }}</p>
                        </div>

                        <form class="flex flex-col gap-2 sm:flex-row" role="search" @submit.prevent="applySearch">
                            <label for="project-search" class="sr-only">Search projects</label>
                            <div class="relative min-w-0 flex-1">
                                <Icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#8a9790]" />
                                <input id="project-search" v-model="search" type="search" placeholder="Search project names" class="min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] pl-10 pr-4 text-sm focus:border-[#16483d] focus:ring-[#16483d]" autocomplete="off">
                            </div>
                            <button class="min-h-11 rounded-xl bg-[#16483d] px-5 py-2 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e]" type="submit">
                                Search
                            </button>
                        </form>
                    </div>

                    <div v-if="projects.data.length" class="divide-y divide-[#eaf0eb]">
                        <div v-for="project in projects.data" :key="project.id" class="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f3f7f3] text-[#16483d]">
                                    <Icon name="folder" :size="17" />
                                </span>
                                <div>
                                    <p class="font-semibold text-[#16201d]">{{ project.name }}</p>
                                    <p v-if="project.merged_into" class="mt-1 text-xs text-[#8a9790]">Historical name</p>
                                </div>
                            </div>
                            <span v-if="project.merged_into" class="text-sm text-[#63716c]">Merged into {{ project.merged_into.name }}</span>
                            <span v-else class="status-chip">Active</span>
                        </div>
                    </div>
                    <div v-else class="px-6 py-16 text-center sm:px-7">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#edf4ee] text-[#16483d]"><Icon name="search" /></span>
                        <h3 class="mt-4 font-semibold text-[#16201d]">No matching projects</h3>
                        <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-[#63716c]">Try a different search term or add a new shared project from the panel beside this directory.</p>
                    </div>

                    <div v-if="projects.last_page > 1" class="flex flex-col gap-4 border-t border-[#e1e9e3] px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <p class="text-sm text-[#63716c]">{{ paginationSummary }}</p>
                        <nav class="flex items-center gap-1" aria-label="Project pages">
                            <Link
                                v-for="link in projects.links"
                                :key="link.label"
                                :href="link.url ?? '#'"
                                :aria-current="link.active ? 'page' : undefined"
                                :aria-label="paginationLabel(link.label)"
                                :prefetch="link.url ? ['hover', 'click'] : false"
                                :cache-for="link.url ? ['10s', '30s'] : undefined"
                                class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-2 text-xs font-semibold transition-colors"
                                :class="[
                                    link.active ? 'bg-[#16483d] text-white' : link.url ? 'text-[#63716c] hover:bg-[#edf4ee] hover:text-[#16483d]' : 'cursor-not-allowed text-[#c1cec5]',
                                ]"
                                :preserve-scroll="true"
                            >
                                {{ paginationLabel(link.label) }}
                            </Link>
                        </nav>
                    </div>
                </section>

                <div class="space-y-6">
                    <form v-if="$page.props.auth.can.createVouchers || $page.props.auth.can.manageProjects" class="panel p-6" @submit.prevent="submitProject">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#e8b44e]/25 text-[#16483d]"><Icon name="folder" :size="17" /></span>
                            <div>
                                <h2 class="font-semibold text-[#16201d]">Add a project</h2>
                                <p class="mt-1 text-sm leading-5 text-[#63716c]">Names are shared. Extra spaces and letter case do not create duplicates.</p>
                            </div>
                        </div>
                        <label for="new-project-name" class="mt-6 block text-sm font-medium text-[#16201d]">Project name</label>
                        <input id="new-project-name" v-model="addForm.name" list="project-suggestions" aria-describedby="new-project-name-error" :aria-invalid="Boolean(addForm.errors.name)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" maxlength="255" autocomplete="off" required>
                        <datalist id="project-suggestions">
                            <option v-for="project in projectOptions" :key="project.id" :value="project.name" />
                        </datalist>
                        <InputError id="new-project-name-error" class="mt-1" :message="addForm.errors.name" />
                        <p v-if="existingMatch" class="mt-2 text-sm text-[#63716c]" role="status">Already listed as {{ existingMatch.name }}.</p>
                        <button class="mt-5 min-h-11 w-full rounded-xl bg-[#16483d] px-5 py-3 text-sm font-semibold text-white hover:bg-[#0f382f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50" :disabled="addForm.processing || Boolean(existingMatch)">
                            Add project
                        </button>
                    </form>

                    <section v-if="$page.props.auth.can.manageProjects" class="panel p-6" aria-labelledby="project-maintenance-heading">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f3f7f3] text-[#16483d]"><Icon name="sliders" :size="17" /></span>
                            <div>
                                <h2 id="project-maintenance-heading" class="font-semibold text-[#16201d]">Project maintenance</h2>
                                <p class="mt-1 text-sm leading-5 text-[#63716c]">Rename active records or merge duplicates. Merged records stay in history.</p>
                            </div>
                        </div>

                        <form class="mt-6 border-t border-[#e1e9e3] pt-5" @submit.prevent="renameProject">
                            <label for="rename-project" class="text-sm font-medium text-[#16201d]">Rename project</label>
                            <select id="rename-project" v-model="selectedProjectId" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" @change="selectRenameProject">
                                <option value="">Choose a project</option>
                                <option v-for="project in activeProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                            </select>
                            <label for="renamed-project-name" class="mt-4 block text-sm font-medium text-[#16201d]">New name</label>
                            <input id="renamed-project-name" v-model="renameForm.name" aria-describedby="renamed-project-name-error" :aria-invalid="Boolean(renameForm.errors.name)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" maxlength="255" required>
                            <InputError id="renamed-project-name-error" class="mt-1" :message="renameForm.errors.name" />
                            <button class="mt-4 min-h-11 w-full rounded-xl border border-[#16483d] px-5 py-3 text-sm font-semibold text-[#16483d] hover:bg-[#edf4ee] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50" :disabled="renameForm.processing || !selectedProjectId">
                                Save project name
                            </button>
                        </form>

                        <form class="mt-6 border-t border-[#e1e9e3] pt-5" @submit.prevent="mergeProjects">
                            <label for="merge-source" class="text-sm font-medium text-[#16201d]">Duplicate project</label>
                            <select id="merge-source" v-model="selectedSourceId" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" required @change="selectMergeSource">
                                <option value="">Choose a project to merge</option>
                                <option v-for="project in activeProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                            </select>
                            <label for="merge-target" class="mt-4 block text-sm font-medium text-[#16201d]">Keep this project</label>
                            <select id="merge-target" v-model="selectedTargetId" aria-describedby="merge-target-error" :aria-invalid="Boolean(mergeForm.errors.target_project_id)" class="mt-2 min-h-11 w-full rounded-xl border-[#d7e2da] bg-[#f8faf8] px-4 py-2 text-sm focus:border-[#16483d] focus:ring-[#16483d]" required>
                                <option value="">Choose the project to keep</option>
                                <option v-for="project in activeProjects.filter((item) => String(item.id) !== selectedSourceId)" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                            </select>
                            <InputError id="merge-target-error" class="mt-1" :message="mergeForm.errors.target_project_id" />
                            <button class="mt-4 min-h-11 w-full rounded-xl bg-[#8c4135] px-5 py-3 text-sm font-semibold text-white hover:bg-[#74372d] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e8b44e] disabled:opacity-50" :disabled="mergeForm.processing || !selectedSourceId || !selectedTargetId">
                                Merge projects
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
