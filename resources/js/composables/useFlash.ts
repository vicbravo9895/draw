import { ref, type Ref } from 'vue';

export type FlashMessage = {
    type: 'success' | 'error';
    message: string;
};

const flashMessage: Ref<FlashMessage | null> = ref(null);

/**
 * Composable para mostrar mensajes de feedback (éxito/error).
 * Usar en formularios: onError: () => useFlash().show({ error: '...' })
 */
export function useFlash() {
    return {
        message: flashMessage,
        show(payload: { success?: string; error?: string }) {
            if (payload.success) {
                flashMessage.value = { type: 'success', message: payload.success };
            }
            if (payload.error) {
                flashMessage.value = { type: 'error', message: payload.error };
            }
        },
        clear() {
            flashMessage.value = null;
        },
    };
}
