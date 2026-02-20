import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface RecordPayload {
    part_number: string;
    serial_number: string;
    lot_date: string | null;
    good_qty: number;
    defects_qty: number;
    _inspectionId?: number;
}

export const useCaptureStore = defineStore('capture', () => {
    const lastLot = ref<string | null>(null);
    const rapidMode = ref(false);
    const recordCount = ref(0);
    const useSameLot = ref<boolean | null>(null);
    const pendingRecords = ref<RecordPayload[]>([]);
    const isOnline = ref(navigator.onLine);
    const isSyncing = ref(false);

    function setLastLot(lot: string | null) {
        lastLot.value = lot;
    }

    function toggleRapidMode() {
        rapidMode.value = !rapidMode.value;
    }

    function incrementRecordCount() {
        recordCount.value++;
    }

    function resetSession() {
        lastLot.value = null;
        recordCount.value = 0;
        useSameLot.value = null;
    }

    function addPendingRecord(record: RecordPayload) {
        pendingRecords.value.push({ ...record });
    }

    function removePendingRecord(index: number) {
        pendingRecords.value.splice(index, 1);
    }

    function clearPendingRecords() {
        pendingRecords.value = [];
    }

    /**
     * Attempt to sync all pending records to the server.
     * Records are retried one by one; on first failure, stop and wait for next attempt.
     */
    async function syncPendingRecords() {
        if (isSyncing.value || pendingRecords.value.length === 0) return;
        isSyncing.value = true;

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        while (pendingRecords.value.length > 0) {
            const record = pendingRecords.value[0];
            const inspectionId = record._inspectionId;
            if (!inspectionId) {
                pendingRecords.value.shift();
                continue;
            }

            try {
                const response = await fetch(`/app/inspections/${inspectionId}/items`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        part_number: record.part_number,
                        serial_number: record.serial_number,
                        lot_date: record.lot_date,
                        good_qty: record.good_qty,
                        defects_qty: record.defects_qty,
                    }),
                });

                if (response.ok) {
                    pendingRecords.value.shift();
                } else {
                    break;
                }
            } catch {
                break;
            }
        }

        isSyncing.value = false;
    }

    // Listen for online/offline events
    if (typeof window !== 'undefined') {
        window.addEventListener('online', () => {
            isOnline.value = true;
            syncPendingRecords();
        });
        window.addEventListener('offline', () => {
            isOnline.value = false;
        });
    }

    return {
        lastLot,
        rapidMode,
        recordCount,
        useSameLot,
        pendingRecords,
        isOnline,
        isSyncing,
        setLastLot,
        toggleRapidMode,
        incrementRecordCount,
        resetSession,
        addPendingRecord,
        removePendingRecord,
        clearPendingRecords,
        syncPendingRecords,
    };
});
