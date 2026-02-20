<?php

namespace App\Services;

class QualityThresholdService
{
    public const CRITICAL_DEFECT_RATE = 10.0;

    public const WARNING_DEFECT_RATE = 5.0;

    public const CRITICAL_PPM = 10_000;

    public const WARNING_PPM = 5_000;

    public const LOT_AT_RISK_RATE = 8.0;

    /**
     * Return default threshold values.
     */
    public static function defaults(): array
    {
        return [
            'critical_defect_rate' => self::CRITICAL_DEFECT_RATE,
            'warning_defect_rate' => self::WARNING_DEFECT_RATE,
            'critical_ppm' => self::CRITICAL_PPM,
            'warning_ppm' => self::WARNING_PPM,
            'lot_at_risk_rate' => self::LOT_AT_RISK_RATE,
        ];
    }

    /**
     * Determine severity for a given defect rate.
     */
    public static function severityForDefectRate(float $defectRate): ?string
    {
        if ($defectRate > self::CRITICAL_DEFECT_RATE) {
            return 'critical';
        }

        if ($defectRate > self::WARNING_DEFECT_RATE) {
            return 'warning';
        }

        return null;
    }

    /**
     * Determine severity for a given PPM value.
     */
    public static function severityForPpm(int $ppm): ?string
    {
        if ($ppm > self::CRITICAL_PPM) {
            return 'critical';
        }

        if ($ppm > self::WARNING_PPM) {
            return 'warning';
        }

        return null;
    }

    /**
     * Get recommended actions based on severity and type.
     */
    public static function recommendedActions(string $severity, string $type): array
    {
        if ($severity === 'critical') {
            return match ($type) {
                'part' => [
                    'Contener producción inmediatamente',
                    'Inspeccionar lotes recientes',
                    'Revisar parámetros del proceso',
                    'Notificar a ingeniería de calidad',
                ],
                'lot' => [
                    'Contener producción inmediatamente',
                    'Poner en cuarentena el lote afectado',
                    'Revisar lote del proveedor',
                    'Iniciar análisis de causa raíz',
                ],
                default => [
                    'Contener producción',
                    'Revisar datos de inspección',
                    'Escalar al gerente de calidad',
                ],
            };
        }

        return match ($type) {
            'part' => [
                'Monitorear de cerca',
                'Revisar últimos resultados de inspección',
            ],
            'lot' => [
                'Monitorear de cerca',
                'Verificar trazabilidad del lote',
            ],
            default => [
                'Monitorear de cerca',
                'Revisar datos recientes',
            ],
        };
    }
}
