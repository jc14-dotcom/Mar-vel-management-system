import type { ConnectionStatus } from 'laravel-echo';
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
        channel.listen('.voucher.changed', handler);
        onBeforeUnmount(() => channel.stopListening('.voucher.changed', handler));
    };

    const onProjectChanged = (handler: (event: ProjectChangedPayload) => void): void => {
        if (!echo) {
            return;
        }

        const channel = echo.private('projects');
        channel.listen('.project.changed', handler);
        onBeforeUnmount(() => channel.stopListening('.project.changed', handler));
    };

    const onReconnect = (handler: () => void): void => {
        reconnectHandlers.add(handler);
        onBeforeUnmount(() => reconnectHandlers.delete(handler));
    };

    return {
        status: readonly(connectionStatus),
        onVoucherChanged,
        onProjectChanged,
        onReconnect,
    };
}
