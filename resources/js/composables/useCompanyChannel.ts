import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import echo from '@/echo';

export interface InspectionEvent {
    id: number;
    reference_code: string;
    status: string;
    updated_at?: string;
    completed_at?: string;
    project?: string;
    area_line?: string;
    shift?: string;
    date?: string;
    total_good?: number;
    total_defects?: number;
    total?: number;
    defect_rate?: number;
    parts?: Array<{
        part_number: string;
        total_good: number;
        total_defects: number;
        total: number;
        defect_rate: number;
        items?: Array<{
            lot_date: string;
            good_qty: number;
            defects_qty: number;
        }>;
    }>;
}

export interface QualityAlertEvent {
    severity: 'critical' | 'warning';
    type: 'part' | 'lot' | 'shift' | 'area';
    identifier: string;
    defect_rate: number;
    ppm: number;
    defects: number;
    total: number;
    inspection_id?: number;
    reference_code?: string;
    message: string;
    recommended_actions: string[];
    timestamp: string;
}

export interface InspectionClosedEvent {
    id: number;
    reference_code: string;
    status: string;
    closed_at: string;
}

export function useCompanyChannel(companyId: number, channelPrefix: string = 'company') {
    const lastEvent = ref<InspectionEvent | null>(null);
    const connected = ref(false);
    let channel: ReturnType<typeof echo.private> | null = null;
    let pollingInterval: ReturnType<typeof setInterval> | null = null;

    const listeners: Array<{ event: string; callback: (data: any) => void }> = [];

    function onInspectionUpdated(callback: (data: InspectionEvent) => void) {
        listeners.push({ event: 'InspectionUpdated', callback });
    }

    function onInspectionCompleted(callback: (data: InspectionEvent) => void) {
        listeners.push({ event: 'InspectionCompleted', callback });
    }

    function onInspectionClosed(callback: (data: InspectionClosedEvent) => void) {
        listeners.push({ event: 'InspectionClosed', callback });
    }

    function onQualityAlert(callback: (data: QualityAlertEvent) => void) {
        listeners.push({ event: 'QualityAlertTriggered', callback });
    }

    function connect() {
        if (!companyId) return;
        try {
            const channelName = `${channelPrefix}.${companyId}`;
            channel = echo.private(channelName);

            channel
                .listen('.InspectionUpdated', (data: InspectionEvent) => {
                    lastEvent.value = data;
                    listeners
                        .filter((l) => l.event === 'InspectionUpdated')
                        .forEach((l) => l.callback(data));
                })
                .listen('.InspectionCompleted', (data: InspectionEvent) => {
                    lastEvent.value = data;
                    listeners
                        .filter((l) => l.event === 'InspectionCompleted')
                        .forEach((l) => l.callback(data));
                })
                .listen('.InspectionClosed', (data: InspectionClosedEvent) => {
                    listeners
                        .filter((l) => l.event === 'InspectionClosed')
                        .forEach((l) => l.callback(data));
                })
                .listen('.QualityAlertTriggered', (data: QualityAlertEvent) => {
                    listeners
                        .filter((l) => l.event === 'QualityAlertTriggered')
                        .forEach((l) => l.callback(data));
                });

            connected.value = true;
        } catch {
            connected.value = false;
            startPolling();
        }
    }

    function startPolling() {
        if (pollingInterval) return;
        pollingInterval = setInterval(() => {
            router.reload();
        }, 30000);
    }

    function disconnect() {
        if (channel) {
            echo.leave(`${channelPrefix}.${companyId}`);
            channel = null;
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        connected.value = false;
    }

    onMounted(() => connect());
    onUnmounted(() => disconnect());

    return {
        lastEvent,
        connected,
        onInspectionUpdated,
        onInspectionCompleted,
        onInspectionClosed,
        onQualityAlert,
        disconnect,
    };
}
