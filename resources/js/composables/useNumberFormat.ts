/**
 * Safe number formatting utilities for industrial quality metrics.
 * Handles NaN, Infinity, null, undefined gracefully.
 * Uses Intl.NumberFormat for locale-safe formatting.
 */

const FALLBACK = '--';

function isSafe(n: unknown): n is number {
    return typeof n === 'number' && Number.isFinite(n);
}

const intFormatter = new Intl.NumberFormat('es-MX', {
    maximumFractionDigits: 0,
    useGrouping: true,
});

const compactFormatter = new Intl.NumberFormat('es-MX', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
    useGrouping: true,
});

/**
 * Format integer with thousand separators, no decimals.
 * Example: 1245882 → "1,245,882"
 */
export function formatInteger(n: unknown): string {
    if (!isSafe(n as number)) return FALLBACK;
    return intFormatter.format(n as number);
}

/**
 * Format percentage with at most `maxDecimals` decimal places.
 * Example: 96.432 → "96.4%"
 */
export function formatPercent(n: unknown, maxDecimals = 1): string {
    if (!isSafe(n as number)) return FALLBACK;
    const val = n as number;
    const formatted = val.toFixed(maxDecimals);
    // Remove trailing zeros after decimal point but keep at least one decimal
    const cleaned = maxDecimals > 0
        ? parseFloat(formatted).toFixed(maxDecimals)
        : String(Math.round(val));
    return `${cleaned}%`;
}

/**
 * Format PPM value.
 * - > 100: integer, no decimals
 * - <= 100: 1 decimal
 * - null/NaN/0-div → "--"
 */
export function formatPPM(n: unknown): string {
    if (!isSafe(n as number)) return FALLBACK;
    const val = n as number;
    if (val > 100) {
        return `${intFormatter.format(Math.round(val))} PPM`;
    }
    return `${val.toFixed(1)} PPM`;
}

/**
 * Format currency value (USD).
 * Example: 12400 → "$12,400"
 */
export function formatCurrency(n: unknown): string {
    if (!isSafe(n as number)) return FALLBACK;
    return currencyFormatter.format(n as number);
}

/**
 * Format large numbers compactly.
 * Example: 1200000 → "1.2M"
 */
export function formatCompact(n: unknown): string {
    if (!isSafe(n as number)) return FALLBACK;
    return compactFormatter.format(n as number);
}

/**
 * Safely compute PPM from defects and total.
 * Returns null if total is 0 or inputs invalid.
 */
export function computePPM(defects: number, total: number): number | null {
    if (!isSafe(defects) || !isSafe(total) || total === 0) return null;
    return Math.round((defects / total) * 1_000_000);
}

/**
 * Safely compute FPY (First Pass Yield) percentage.
 * Returns null if total is 0 or inputs invalid.
 */
export function computeFPY(good: number, total: number): number | null {
    if (!isSafe(good) || !isSafe(total) || total === 0) return null;
    return Math.round(((good / total) * 100) * 10) / 10;
}

/**
 * Get severity color class based on value and thresholds.
 */
export function severityColor(value: number | null, thresholds: { green: number; amber: number }, higherIsBetter = true): 'green' | 'amber' | 'red' {
    if (value === null) return 'amber';
    if (higherIsBetter) {
        if (value >= thresholds.green) return 'green';
        if (value >= thresholds.amber) return 'amber';
        return 'red';
    }
    if (value <= thresholds.green) return 'green';
    if (value <= thresholds.amber) return 'amber';
    return 'red';
}
