<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import { useCompanyChannel } from '@/composables/useCompanyChannel';
import { useDashboardStore } from '@/stores/dashboard';
import type {
    KpiData,
    PartQuality,
    TopOffender,
    TrendDay,
    AlertItem,
    RecentInspection,
} from '@/stores/dashboard';

import KpiStrip from '@/components/portal/KpiStrip.vue';
import QualityHeatmap from '@/components/portal/QualityHeatmap.vue';
import TopOffenders from '@/components/portal/TopOffenders.vue';
import AlertStream from '@/components/portal/AlertStream.vue';
import TrendIndicator from '@/components/portal/TrendIndicator.vue';
import DrillDownTable from '@/components/portal/DrillDownTable.vue';
import { Radio } from 'lucide-vue-next';

defineOptions({ layout: PortalLayout });

const page = usePage();
const companyId = (page.props.auth as { viewer?: { company_id: number } })?.viewer?.company_id;

const props = defineProps<{
    company: { name: string; public_code: string; logo_path: string | null };
    kpis: { month: KpiData; week: KpiData; today: KpiData };
    lotsAtRisk: number;
    qualityByPart: PartQuality[];
    topOffenders: TopOffender[];
    trend: { daily: TrendDay[]; direction: string };
    alerts: AlertItem[];
    recentInspections: RecentInspection[];
}>();

const store = useDashboardStore();

// Initialize store from server-rendered props
onMounted(() => {
    store.initFromProps({
        kpis: props.kpis,
        lotsAtRisk: props.lotsAtRisk,
        qualityByPart: props.qualityByPart,
        topOffenders: props.topOffenders,
        trend: props.trend,
        alerts: props.alerts,
        recentInspections: props.recentInspections,
    });
});

// Sync store when Inertia re-delivers props (lazy reload / navigation)
watch(
    () => [props.kpis, props.lotsAtRisk, props.qualityByPart, props.topOffenders, props.trend, props.alerts, props.recentInspections],
    () => {
        store.initFromProps({
            kpis: props.kpis,
            lotsAtRisk: props.lotsAtRisk,
            qualityByPart: props.qualityByPart,
            topOffenders: props.topOffenders,
            trend: props.trend,
            alerts: props.alerts,
            recentInspections: props.recentInspections,
        });
    },
    { deep: true },
);

// Socket subscriptions
const wsConnected = ref(false);

if (companyId) {
    const { connected, onInspectionUpdated, onInspectionCompleted, onQualityAlert } =
        useCompanyChannel(companyId, 'portal.company');

    wsConnected.value = connected.value;

    watch(connected, (val) => {
        wsConnected.value = val;
    });

    onInspectionUpdated((data) => {
        store.handleInspectionEvent(data);
    });

    onInspectionCompleted((data) => {
        store.handleInspectionEvent(data);
    });

    onQualityAlert((data) => {
        store.handleQualityAlert(data);
    });
}

onUnmounted(() => {
    store.stopConsistencyReload();
});

// Drill-down state
const drillDownOpen = ref(false);
const drillDownPartFilter = ref<string | undefined>(undefined);

function openDrillDown(partNumber?: string) {
    drillDownPartFilter.value = partNumber;
    drillDownOpen.value = true;
}
</script>

<template>
    <Head title="Centro de Control de Calidad" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Centro de Control de Calidad
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ company.name }} &mdash; Inteligencia Operacional
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1"
                    :class="wsConnected
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                        : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'"
                >
                    <Radio class="h-3 w-3" />
                    {{ wsConnected ? 'En Vivo' : 'Conectando...' }}
                </span>
            </div>
        </div>

        <!-- KPI Strip -->
        <KpiStrip />

        <!-- Alert Stream + Top Offenders -->
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <AlertStream />
            </div>
            <div class="lg:col-span-2">
                <TopOffenders />
            </div>
        </div>

        <!-- Quality Heatmap -->
        <QualityHeatmap @drill-down="openDrillDown" />

        <!-- Trend + Recent Summary -->
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <TrendIndicator />
            </div>
            <div class="lg:col-span-3">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-5 py-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-foreground">
                            Inspecciones Recientes
                        </h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">Últimas inspecciones con resumen de calidad</p>
                    </div>
                    <div v-if="store.recentInspections.length === 0" class="flex h-32 items-center justify-center text-sm text-muted-foreground">
                        Sin inspecciones recientes.
                    </div>
                    <div v-else class="divide-y">
                        <div
                            v-for="ins in store.recentInspections.slice(0, 8)"
                            :key="ins.id"
                            class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-accent/30 cursor-pointer"
                            @click="openDrillDown()"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-foreground truncate">
                                    {{ ins.reference_code }}
                                </p>
                                <p class="text-xs text-muted-foreground truncate">
                                    {{ ins.date }}
                                    <span v-if="ins.project"> &middot; {{ ins.project }}</span>
                                    <span v-if="ins.shift"> &middot; {{ ins.shift }}</span>
                                </p>
                            </div>
                            <div class="ml-4 flex items-center gap-3 text-right">
                                <div>
                                    <p class="font-mono text-sm font-semibold tabular-nums" :class="ins.defect_rate > 5 ? 'text-red-600 dark:text-red-400' : ins.defect_rate > 2 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ (100 - ins.defect_rate).toFixed(1) }}%
                                    </p>
                                    <p class="text-[10px] text-muted-foreground">Calidad</p>
                                </div>
                                <span
                                    class="inline-block h-2 w-2 rounded-full"
                                    :class="{
                                        'bg-emerald-500': ins.status === 'completed',
                                        'bg-amber-500': ins.status === 'in_progress',
                                        'bg-zinc-400': ins.status === 'pending',
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Drill-Down Table (Sheet) -->
        <DrillDownTable
            v-model:open="drillDownOpen"
            :filter-part-number="drillDownPartFilter"
        />
    </div>
</template>
