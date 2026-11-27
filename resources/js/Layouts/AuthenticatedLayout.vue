<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Icon, { type IconName } from '@/Components/Icon.vue';
import LogoutConfirmation from '@/Components/LogoutConfirmation.vue';
import NavLink from '@/Components/NavLink.vue';
import RealtimeStatus from '@/Components/RealtimeStatus.vue';
import type { PageProps } from '@/types';
import { useRealtimeUpdates } from '@/useRealtimeUpdates';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

type NavigationLink = {
    label: string;
    href: string;
    active: boolean;
    icon: IconName;
};

const page = usePage<PageProps>();
const showingNavigationDropdown = ref(false);
const showingLogoutConfirmation = ref(false);
const shouldShowLogoutConfirmationAfterNavigationClose = ref(false);
const mobileNavigationDrawer = ref<HTMLElement | null>(null);
const navigationToggle = ref<HTMLButtonElement | null>(null);
const previousBodyOverflow = ref('');
const previousDocumentOverflow = ref('');
const previousBodyPosition = ref('');
const previousBodyTop = ref('');
const previousBodyWidth = ref('');
const previousScrollY = ref(0);
const isNavigating = ref(false);
const { status: realtimeStatus } = useRealtimeUpdates();

const navigationSections = computed<{ label: string; links: NavigationLink[] }[]>(() => {
    const can = page.props.auth.can;

    return [
        {
            label: 'Workspace',
            links: [
                {
                    label: 'Overview',
                    href: route('dashboard'),
                    active: route().current('dashboard'),
                    icon: 'grid' as IconName,
                },
                ...(can.viewVouchers || can.manageProjects ? [{
                    label: 'Projects',
                    href: route('projects.index'),
                    active: route().current('projects.*'),
                    icon: 'folder' as IconName,
                }] : []),
                ...(can.viewVouchers ? [{
                    label: 'Vouchers',
                    href: route('vouchers.index'),
                    active: route().current('vouchers.*'),
                    icon: 'file-text' as IconName,
                }] : []),
            ],
        },
        {
            label: 'Administration',
            links: [
                {
                    label: 'Accounts & access',
                    href: route('admin.accounts.index'),
                    active: route().current('admin.accounts.*'),
                    icon: 'users' as IconName,
                },
                {
                    label: 'Permission sets',
                    href: route('admin.permissions.index'),
                    active: route().current('admin.permissions.*'),
                    icon: 'shield' as IconName,
                },
                {
                    label: 'Print settings',
                    href: route('admin.settings.edit'),
                    active: route().current('admin.settings.*'),
                    icon: 'sliders' as IconName,
                },
            ].filter((link, index) => [can.manageAccounts, can.managePermissions, can.manageSettings][index]),
        },
    ].filter((section) => section.links.length > 0);
});

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? 'Account';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

const closeMobileNavigation = () => {
    showingNavigationDropdown.value = false;
};

const openLogoutConfirmationAfterNavigationClose = () => {
    shouldShowLogoutConfirmationAfterNavigationClose.value = true;
    closeMobileNavigation();
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && showingNavigationDropdown.value) {
        closeMobileNavigation();
        return;
    }

    if (event.key !== 'Tab' || !showingNavigationDropdown.value || !mobileNavigationDrawer.value) {
        return;
    }

    const focusableElements = mobileNavigationDrawer.value.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );
    const firstFocusableElement = focusableElements[0];
    const lastFocusableElement = focusableElements[focusableElements.length - 1];

    if (!firstFocusableElement || !lastFocusableElement) {
        return;
    }

    if (event.shiftKey && document.activeElement === firstFocusableElement) {
        event.preventDefault();
        lastFocusableElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastFocusableElement) {
        event.preventDefault();
        firstFocusableElement.focus();
    }
};

const closeNavigationOnDesktop = () => {
    if (window.innerWidth >= 1024) {
        closeMobileNavigation();
    }
};

const restorePageScroll = (restoreFocus = true) => {
    if (showingNavigationDropdown.value) {
        return;
    }

    document.body.style.overflow = previousBodyOverflow.value;
    document.documentElement.style.overflow = previousDocumentOverflow.value;
    document.body.style.position = previousBodyPosition.value;
    document.body.style.top = previousBodyTop.value;
    document.body.style.width = previousBodyWidth.value;
    window.scrollTo(0, previousScrollY.value);

    if (restoreFocus && window.innerWidth < 1024) {
        navigationToggle.value?.focus();
    }
};

const handleMobileNavigationAfterLeave = () => {
    restorePageScroll();

    if (shouldShowLogoutConfirmationAfterNavigationClose.value) {
        shouldShowLogoutConfirmationAfterNavigationClose.value = false;
        showingLogoutConfirmation.value = true;
    }
};

watch(showingNavigationDropdown, (isOpen) => {
    if (isOpen) {
        previousBodyOverflow.value = document.body.style.overflow;
        previousDocumentOverflow.value = document.documentElement.style.overflow;
        previousBodyPosition.value = document.body.style.position;
        previousBodyTop.value = document.body.style.top;
        previousBodyWidth.value = document.body.style.width;
        previousScrollY.value = window.scrollY;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${previousScrollY.value}px`;
        document.body.style.width = '100%';

        void nextTick(() => {
            if (showingNavigationDropdown.value) {
                mobileNavigationDrawer.value?.querySelector<HTMLElement>('button, a')?.focus();
            }
        });
        return;
    }

    if (!mobileNavigationDrawer.value) {
        restorePageScroll();
    }
});

const removeNavigationStartListener = router.on('start', () => {
    isNavigating.value = true;
});
const removeNavigationFinishListener = router.on('finish', (event) => {
    isNavigating.value = false;

    if (event.detail.visit.method !== 'get') {
        router.flushAll();
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', closeNavigationOnDesktop);
});

onBeforeUnmount(() => {
    const wasNavigationOpen = showingNavigationDropdown.value;

    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', closeNavigationOnDesktop);
    removeNavigationStartListener();
    removeNavigationFinishListener();

    if (wasNavigationOpen) {
        restorePageScroll(false);
    }
});
</script>

<template>
    <div class="app-shell">
        <div
            class="flex min-h-screen items-start"
            :aria-hidden="showingNavigationDropdown ? 'true' : undefined"
            :inert="showingNavigationDropdown"
        >
            <aside class="side-rail sticky top-0 hidden h-screen w-72 shrink-0 self-start flex-col overflow-y-auto px-5 py-6 lg:flex">
                <Link :href="route('dashboard')" class="flex items-center gap-3 px-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#e8b44e] text-[#16483d] shadow-lg shadow-black/10">
                        <ApplicationLogo class="h-7 w-7 fill-current" />
                    </span>
                    <span>
                        <span class="block text-lg font-semibold tracking-[-.02em] text-white">Mar-vel OMS</span>
                        <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-[.2em] text-[#9fc4a7]">Office workspace</span>
                    </span>
                </Link>

                <nav class="mt-12 space-y-8" aria-label="Primary navigation">
                    <div v-for="section in navigationSections" :key="section.label">
                        <p class="px-3 text-[10px] font-bold uppercase tracking-[.2em] text-[#8fb8a0]">{{ section.label }}</p>
                        <div class="mt-3 space-y-1">
                            <NavLink
                                v-for="link in section.links"
                                :key="link.label"
                                :href="link.href"
                                :active="link.active"
                                variant="sidebar"
                                :prefetch="['hover', 'click']"
                                :cache-for="['10s', '30s']"
                            >
                                <Icon :name="link.icon" :size="19" />
                                <span>{{ link.label }}</span>
                            </NavLink>
                        </div>
                    </div>
                </nav>

                <div class="mt-auto">
                    <button
                        type="button"
                        class="group flex min-h-11 w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] px-3 py-2.5 text-start text-sm text-[#c4ddd0] transition hover:border-white/20 hover:bg-white/10 hover:text-white focus-visible:outline-none active:scale-[.99]"
                        @click="showingLogoutConfirmation = true"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/8 text-[#e8b44e] transition group-hover:bg-white/12">
                            <Icon name="logout" :size="18" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold leading-5">Log out</span>
                            <span class="block text-xs font-normal leading-5 text-[#9fc4a7]">End this session</span>
                        </span>
                    </button>
                </div>
            </aside>

            <div class="min-h-screen min-w-0 flex-1">
                <header class="sticky top-0 z-30 border-b border-[#e1e9e3] bg-white/90 backdrop-blur-xl">
                    <div v-if="isNavigating" class="navigation-progress" aria-hidden="true" />
                    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#d7e2da] text-[#16483d] hover:bg-[#edf4ee] active:scale-[.97] lg:hidden"
                                :aria-expanded="showingNavigationDropdown"
                                aria-controls="mobile-navigation"
                                aria-label="Toggle navigation"
                                ref="navigationToggle"
                                @click.stop="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <Icon :name="showingNavigationDropdown ? 'close' : 'menu'" />
                            </button>

                            <Link :href="route('dashboard')" class="flex items-center gap-2 lg:hidden">
                                <ApplicationLogo class="h-8 w-8 fill-current text-[#16483d]" />
                                <span class="hidden text-sm font-semibold text-[#16483d] sm:inline">Mar-vel OMS</span>
                            </Link>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                            <RealtimeStatus :status="realtimeStatus" />
                            <Dropdown align="right" width="64" content-classes="border border-[#e1e9e3] bg-white p-1 shadow-xl shadow-[#16483d]/10">
                            <template #trigger>
                                <button type="button" class="flex max-w-[13rem] items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-[#f3f7f3] focus-visible:outline-none">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#dce9df] text-xs font-bold text-[#16483d]">{{ userInitials }}</span>
                                    <span class="hidden min-w-0 sm:block">
                                        <span class="block truncate text-sm font-semibold text-[#16201d]">{{ $page.props.auth.user?.name ?? 'Account' }}</span>
                                        <span class="block truncate text-xs text-[#63716c]">Account settings</span>
                                    </span>
                                    <Icon name="chevron-down" :size="16" class="shrink-0 text-[#63716c]" />
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[#16201d] hover:bg-[#edf4ee]">
                                    <Icon name="user" :size="17" class="text-[#16483d]" />
                                    Profile settings
                                </DropdownLink>
                            </template>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <main class="px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
                    <slot />
                </main>
            </div>
        </div>

        <Transition name="mobile-navigation-backdrop">
            <div
                v-if="showingNavigationDropdown"
                class="mobile-navigation-backdrop fixed inset-0 z-40 bg-[#102f29]/45 lg:hidden"
                aria-hidden="true"
                @click="closeMobileNavigation"
            />
        </Transition>

        <Transition name="mobile-navigation-drawer" @after-leave="handleMobileNavigationAfterLeave">
            <aside
                v-if="showingNavigationDropdown"
                id="mobile-navigation"
                ref="mobileNavigationDrawer"
                class="mobile-navigation-drawer fixed inset-y-0 left-0 z-50 flex w-[min(19rem,calc(100vw-1rem))] flex-col overflow-y-auto bg-[#16483d] px-5 py-6 text-white shadow-2xl shadow-[#102f29]/30 lg:hidden"
                role="dialog"
                aria-modal="true"
                aria-label="Mobile navigation"
                aria-labelledby="mobile-navigation-title"
                @click.stop
            >
                <div class="flex items-start justify-between gap-4">
                    <Link :href="route('dashboard')" class="flex items-center gap-3" @click="closeMobileNavigation">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#e8b44e] text-[#16483d] shadow-lg shadow-black/10">
                            <ApplicationLogo class="h-7 w-7 fill-current" />
                        </span>
                        <span>
                            <span id="mobile-navigation-title" class="block text-lg font-semibold tracking-[-.02em] text-white">Mar-vel OMS</span>
                            <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-[.2em] text-[#9fc4a7]">Office workspace</span>
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/15 text-[#d6e9da] hover:bg-white/10 hover:text-white active:scale-[.97]"
                        aria-label="Close navigation"
                        @click="closeMobileNavigation"
                    >
                        <Icon name="close" />
                    </button>
                </div>

                <nav class="mt-10 space-y-8" aria-label="Mobile navigation">
                    <div v-for="section in navigationSections" :key="section.label">
                        <p class="px-3 text-[10px] font-bold uppercase tracking-[.2em] text-[#8fb8a0]">{{ section.label }}</p>
                        <div class="mt-3 space-y-1">
                            <Link
                                v-for="link in section.links"
                                :key="link.label"
                                :href="link.href"
                                class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium active:scale-[.99]"
                                :class="link.active ? 'bg-white/12 font-semibold text-white' : 'text-[#c4ddd0] hover:bg-white/8 hover:text-white'"
                                :aria-current="link.active ? 'page' : undefined"
                                :prefetch="['hover', 'click']"
                                :cache-for="['10s', '30s']"
                                @click="closeMobileNavigation"
                            >
                                <Icon :name="link.icon" :size="19" />
                                {{ link.label }}
                            </Link>
                        </div>
                    </div>
                </nav>

                <div class="mt-auto space-y-2 pt-10">
                    <Link :href="route('profile.edit')" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-[#c4ddd0] hover:bg-white/8 hover:text-white active:scale-[.99]" @click="closeMobileNavigation">
                        <Icon name="user" :size="19" />
                        Profile settings
                    </Link>
                    <button
                        type="button"
                        class="group flex min-h-11 w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] px-3 py-2.5 text-start text-sm text-[#c4ddd0] transition hover:border-white/20 hover:bg-white/10 hover:text-white active:scale-[.99]"
                        @click="openLogoutConfirmationAfterNavigationClose"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/8 text-[#e8b44e]">
                            <Icon name="logout" :size="18" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold leading-5">Log out</span>
                            <span class="block text-xs font-normal leading-5 text-[#9fc4a7]">End this session</span>
                        </span>
                    </button>
                </div>
            </aside>
        </Transition>
        <LogoutConfirmation v-model:show="showingLogoutConfirmation" />
    </div>
</template>
