<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';

defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    'update:show': [show: boolean];
}>();

const close = () => {
    emit('update:show', false);
};

const logout = () => {
    router.post(route('logout'), {}, {
        onFinish: close,
    });
};
</script>

<template>
    <Modal
        :show="show"
        max-width="md"
        aria-label="Log out confirmation"
        aria-labelledby="logout-dialog-title"
        aria-describedby="logout-dialog-description"
        @close="close"
    >
        <div class="p-6 sm:p-7">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#fbeeea] text-[#8c4135] ring-1 ring-inset ring-[#e3beb6]">
                    <Icon name="logout" :size="20" />
                </span>
                <div class="min-w-0">
                    <h2 id="logout-dialog-title" class="text-xl font-semibold tracking-[-.025em] text-[#16201d]">
                        Log out of Mar-vel OMS?
                    </h2>
                    <p id="logout-dialog-description" class="mt-2 text-sm leading-6 text-[#63716c]">
                        You will need to sign in again to continue.
                    </p>
                </div>
            </div>

            <div class="mt-7 grid gap-3 border-t border-[#eaf0eb] pt-5 min-[360px]:grid-cols-2">
                <SecondaryButton class="w-full" @click="close">
                    Cancel
                </SecondaryButton>
                <DangerButton class="w-full" @click="logout">
                    Log out
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
