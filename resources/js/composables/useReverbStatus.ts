import { ref, onMounted, onUnmounted } from 'vue';
import echo from '@/echo';

type ConnectionState = 'connected' | 'connecting' | 'disconnected' | 'unavailable' | 'failed' | null;

export function useReverbStatus() {
    const isOnline = ref(false);
    const state = ref<ConnectionState>(null);

    function updateFromConnection(connection: { state: string } | undefined) {
        if (!connection) {
            state.value = null;
            isOnline.value = false;
            return;
        }
        const s = connection.state as ConnectionState;
        state.value = s;
        isOnline.value = s === 'connected';
    }

    onMounted(() => {
        const connector = (echo as any).connector;
        const connection = connector?.pusher?.connection;
        if (connection) {
            updateFromConnection(connection);
            connection.bind('state_change', (states: { current: string }) => {
                state.value = states.current as ConnectionState;
                isOnline.value = states.current === 'connected';
            });
        }
    });

    onUnmounted(() => {
        const connection = (echo as any).connector?.pusher?.connection;
        if (connection?.unbind) {
            connection.unbind('state_change');
        }
    });

    return { isOnline, state };
}
