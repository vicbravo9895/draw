import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type { InspectionEvent, QualityAlertEvent } from '@/composables/useCompanyChannel';

export interface KpiData {
    total_inspected: number;
    total_good: number;
    total_defects: number;
    fpy: number | null;
    ppm: number | null;
    defect_rate: number;
    inspection_count: number;
}

export interface PartQuality {
    part_number: string;
    quality: number | null;
    ppm: number | null;
    total_inspected: number;
    total_defects: number;
    total_good: number;
}

export interface TopOffender {
    type: 'part' | 'lot' | 'shift' | 'area';
    identifier: string;
    defects: number;
    total: number;
    defect_rate: number;
    pct_of_total: number;
}

export interface TrendDay {
    date: string;
    quality: number | null;
    total: number;
    good: number;
    defects: number;
}

export interface AlertItem {
    severity: 'critical' | 'warning';
    type: string;
    identifier: string;
    defect_rate: number;
    ppm: number;
    defects: number;
    total: number;
    message: string;
    recommended_actions: string[];
    timestamp: string;
    inspection_id?: number;
    reference_code?: string;
}

export interface RecentInspection {
    id: number;
    reference_code: string;
    date: string;
    shift: string;
    project: string;
    area_line: string;
    status: string;
    inspector: string | null;
    total_good: number;
    total_defects: number;
    total: number;
    defect_rate: number;
    parts: Array<{
        part_number: string;
        total_good: number;
        total_defects: number;
        total: number;
        defect_rate: number;
        items: Array<{
            serial_number: string;
            lot_date: string;
            good_qty: number;
            defects_qty: number;
        }>;
    }>;
}

export const useDashboardStore = defineStore('dashboard', () => {
    const kpis = ref<{ month: KpiData; week: KpiData; today: KpiData }>({
        month: emptyKpi(),
        week: emptyKpi(),
        today: emptyKpi(),
    });

    const lotsAtRisk = ref<number>(0);
    const qualityByPart = ref<PartQuality[]>([]);
    const topOffenders = ref<TopOffender[]>([]);
    const trend = ref<{ daily: TrendDay[]; direction: string }>({ daily: [], direction: 'stable' });
    const alerts = ref<AlertItem[]>([]);
    const recentInspections = ref<RecentInspection[]>([]);

    let consistencyTimer: ReturnType<typeof setInterval> | null = null;

    function emptyKpi(): KpiData {
        return {
            total_inspected: 0,
            total_good: 0,
            total_defects: 0,
            fpy: null,
            ppm: null,
            defect_rate: 0,
            inspection_count: 0,
        };
    }

    /**
     * Initialize store from Inertia page props (server-rendered).
     */
    function initFromProps(data: {
        kpis: { month: KpiData; week: KpiData; today: KpiData };
        lotsAtRisk: number;
        qualityByPart: PartQuality[];
        topOffenders: TopOffender[];
        trend: { daily: TrendDay[]; direction: string };
        alerts: AlertItem[];
        recentInspections: RecentInspection[];
    }) {
        kpis.value = data.kpis;
        lotsAtRisk.value = data.lotsAtRisk;
        qualityByPart.value = data.qualityByPart;
        topOffenders.value = data.topOffenders;
        trend.value = data.trend;
        alerts.value = data.alerts;
        recentInspections.value = data.recentInspections;

        startConsistencyReload();
    }

    /**
     * Handle socket InspectionUpdated / InspectionCompleted events.
     * Applies incremental updates to KPIs and quality-by-part.
     */
    function handleInspectionEvent(data: InspectionEvent) {
        if (!data.parts) {
            lazyReload();
            return;
        }

        // Update quality-by-part from event data
        for (const part of data.parts) {
            const idx = qualityByPart.value.findIndex(
                (p) => p.part_number === part.part_number,
            );
            const total = part.total;
            const defects = part.total_defects;
            const good = part.total_good;
            const quality = total > 0 ? Math.round(((good / total) * 100) * 10) / 10 : null;
            const ppm = total > 0 ? Math.round((defects / total) * 1_000_000) : null;

            if (idx >= 0) {
                qualityByPart.value[idx] = {
                    part_number: part.part_number,
                    quality,
                    ppm,
                    total_inspected: total,
                    total_defects: defects,
                    total_good: good,
                };
            }
        }

        // Trigger a lazy server reload for full consistency on all aggregated data
        lazyReload();
    }

    /**
     * Handle quality alert from socket.
     */
    function handleQualityAlert(data: QualityAlertEvent) {
        const alert: AlertItem = {
            severity: data.severity,
            type: data.type,
            identifier: data.identifier,
            defect_rate: data.defect_rate,
            ppm: data.ppm,
            defects: data.defects,
            total: data.total,
            message: data.message,
            recommended_actions: data.recommended_actions,
            timestamp: data.timestamp,
            inspection_id: data.inspection_id,
            reference_code: data.reference_code,
        };

        // Prepend and cap at 10
        alerts.value = [alert, ...alerts.value].slice(0, 10);
    }

    const RELOAD_KEYS = [
        'kpis',
        'lotsAtRisk',
        'qualityByPart',
        'topOffenders',
        'trend',
        'alerts',
        'recentInspections',
    ] as const;

    let reloadTimeout: ReturnType<typeof setTimeout> | null = null;

    /**
     * Debounced Inertia partial reload for full data consistency.
     */
    function lazyReload() {
        if (reloadTimeout) clearTimeout(reloadTimeout);
        reloadTimeout = setTimeout(() => {
            router.reload({
                only: [...RELOAD_KEYS],
                onSuccess: (page) => {
                    const props = page.props as any;
                    if (props.kpis) kpis.value = props.kpis;
                    if (props.lotsAtRisk !== undefined) lotsAtRisk.value = props.lotsAtRisk;
                    if (props.qualityByPart) qualityByPart.value = props.qualityByPart;
                    if (props.topOffenders) topOffenders.value = props.topOffenders;
                    if (props.trend) trend.value = props.trend;
                    if (props.alerts) alerts.value = props.alerts;
                    if (props.recentInspections) recentInspections.value = props.recentInspections;
                },
            });
        }, 800);
    }

    /**
     * Periodic consistency reload every 60s.
     */
    function startConsistencyReload() {
        if (consistencyTimer) clearInterval(consistencyTimer);
        consistencyTimer = setInterval(() => {
            lazyReload();
        }, 60_000);
    }

    function stopConsistencyReload() {
        if (consistencyTimer) {
            clearInterval(consistencyTimer);
            consistencyTimer = null;
        }
    }

    // Computed helpers for the UI
    const monthFPY = computed(() => kpis.value.month.fpy);
    const monthPPM = computed(() => kpis.value.month.ppm);
    const monthTotalInspected = computed(() => kpis.value.month.total_inspected);
    const monthTotalDefects = computed(() => kpis.value.month.total_defects);
    return {
        kpis,
        lotsAtRisk,
        qualityByPart,
        topOffenders,
        trend,
        alerts,
        recentInspections,
        monthFPY,
        monthPPM,
        monthTotalInspected,
        monthTotalDefects,
        initFromProps,
        handleInspectionEvent,
        handleQualityAlert,
        lazyReload,
        stopConsistencyReload,
    };
});
