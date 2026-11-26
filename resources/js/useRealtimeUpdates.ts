import type { ConnectionStatus } from 'laravel-echo';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, readonly, ref } from 'vue';
import echo from './echo';

export type RealtimeConnectionStatus = 'connecting' | 'connected' | 'reconnecting' | 'unavailable';

export interface VoucherChangedPayload {
    voucher_id: number;
    record_version: number;
    change_type: 'created' | 'updated' | 'cancelled';
}

export interface ProjectChangedPayload {
    project_id: number;
    change_type: 'created' | 'renamed' | 'merged';
}

const connectionStatus = ref<RealtimeConnectionStatus>(
    echo ? toRealtimeConnectionStatus(echo.connectionStatus()) : 'unavailable',
);
const reconnectHandlers = new Set<() => void>();
let hasConnected = connectionStatus.value === 'connected';

function toRealtimeConnectionStatus(status: ConnectionStatus): RealtimeConnectionStatus {
    if (status === 'connected' || status === 'connecting' || status === 'reconnecting') {
        return status;
    }

    return 'unavailable';
}

if (echo) {
    echo.connector.onConnectionChange((status) => {
        const previousStatus = connectionStatus.value;
        connectionStatus.value = toRealtimeConnectionStatus(status);

        if (status === 'connected') {
            if (hasConnected && previousStatus !== 'connected') {
                reconnectHandlers.forEach((handler) => handler());
            }

            hasConnected = true;
        }
    });
}

export function useRealtimeUpdates() {
    const onVoucherChanged = (handler: (event: VoucherChangedPayload) => void): void => {
        if (!echo) {
            return;
        }

        const channel = echo.private('vouchers');
        const listener = (event: VoucherChangedPayload) => {
            router.flushAll();
            handler(event);
        };

        channel.listen('.voucher.changed', listener);
        onBeforeUnmount(() => channel.stopListening('.voucher.changed', listener));
    };

    const onProjectChanged = (handler: (event: ProjectChangedPayload) => void): void => {
        if (!echo) {
            return;
        }

        const channel = echo.private('projects');
        const listener = (event: ProjectChangedPayload) => {
            router.flushAll();
            handler(event);
        };

        channel.listen('.project.changed', listener);
        onBeforeUnmount(() => channel.stopListening('.project.changed', listener));
    };

    const onReconnect = (handler: () => void): void => {
        const listener = () => {
            router.flushAll();
            handler();
        };

        reconnectHandlers.add(listener);
        onBeforeUnmount(() => reconnectHandlers.delete(listener));
    };

    return {
        status: readonly(connectionStatus),
        onVoucherChanged,
        onProjectChanged,
        onReconnect,
    };
}
