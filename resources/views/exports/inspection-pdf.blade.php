<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inspección {{ $inspection->reference_code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin-bottom: 3px; }
        .header .company { font-size: 12px; color: #666; }
        .header .ref { font-size: 14px; font-weight: bold; margin-top: 5px; }
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 3px 8px; width: 25%; }
        .info-cell .label { font-weight: bold; font-size: 8px; text-transform: uppercase; color: #666; }
        .info-cell .value { font-size: 10px; }
        .part-header { background: #f0f0f0; padding: 6px 8px; font-weight: bold; font-size: 11px; margin-top: 12px; border: 1px solid #ccc; }
        .part-comment { padding: 4px 8px; font-size: 9px; color: #666; border-left: 1px solid #ccc; border-right: 1px solid #ccc; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.items th { background: #e8e8e8; font-size: 9px; text-transform: uppercase; padding: 4px 6px; border: 1px solid #ccc; text-align: left; }
        table.items td { padding: 3px 6px; border: 1px solid #ddd; font-size: 10px; }
        table.items td.num { text-align: right; font-family: monospace; }
        .part-totals { background: #f8f8f8; padding: 4px 8px; font-size: 10px; border: 1px solid #ccc; border-top: none; text-align: right; }
        .part-totals strong { margin-left: 15px; }
        .totals-section { margin-top: 15px; padding: 10px; border: 2px solid #333; background: #f5f5f5; }
        .totals-section h3 { font-size: 12px; margin-bottom: 5px; }
        .totals-grid { display: table; width: 100%; }
        .totals-grid .cell { display: table-cell; text-align: center; padding: 5px; }
        .totals-grid .cell .num { font-size: 18px; font-weight: bold; }
        .totals-grid .cell .lbl { font-size: 8px; text-transform: uppercase; color: #666; }
        .comment-section { margin-top: 15px; padding: 8px; border: 1px solid #ccc; }
        .comment-section h4 { font-size: 10px; font-weight: bold; margin-bottom: 4px; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #ccc; padding-top: 5px; }
        .defect-highlight { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Inspección - Sorteo y Retrabajo</h1>
        <div class="company">{{ $company->name }}</div>
        <div class="ref">{{ $inspection->reference_code }}</div>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-cell"><div class="label">Empresa</div><div class="value">{{ $company->name }}</div></div>
            <div class="info-cell"><div class="label">Fecha</div><div class="value">{{ $inspection->date->format('d/m/Y') }}</div></div>
            <div class="info-cell"><div class="label">Turno</div><div class="value">{{ $inspection->shift ?? 'N/A' }}</div></div>
            <div class="info-cell"><div class="label">Estatus</div><div class="value">{{ ucfirst($inspection->status) }}</div></div>
        </div>
        <div class="info-row">
            <div class="info-cell"><div class="label">Proyecto</div><div class="value">{{ $inspection->project ?? 'N/A' }}</div></div>
            <div class="info-cell"><div class="label">Área / Línea</div><div class="value">{{ $inspection->area_line ?? 'N/A' }}</div></div>
            <div class="info-cell"><div class="label">Hora Inicio</div><div class="value">{{ $inspection->start_time ?? 'N/A' }}</div></div>
            <div class="info-cell"><div class="label">Hora Fin</div><div class="value">{{ $inspection->end_time ?? 'N/A' }}</div></div>
        </div>
        <div class="info-row">
            <div class="info-cell"><div class="label">Programado por</div><div class="value">{{ $inspection->scheduledByUser?->name ?? 'N/A' }}</div></div>
            <div class="info-cell"><div class="label">Inspector</div><div class="value">{{ $inspection->assignedInspector?->name ?? 'N/A' }}</div></div>
            <div class="info-cell" colspan="2"></div>
        </div>
    </div>

    @foreach ($inspection->parts as $part)
        <div class="part-header">
            Parte: {{ $part->part_number }} (Orden: {{ $part->order }})
        </div>
        @if ($part->comment_part)
            <div class="part-comment">{{ $part->comment_part }}</div>
        @endif
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>S/N</th>
                    <th>Lote / Fecha</th>
                    <th style="width: 70px; text-align: right;">Buenas</th>
                    <th style="width: 70px; text-align: right;">Malas</th>
                    <th style="width: 70px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($part->items as $idx => $item)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $item->serial_number ?? '-' }}</td>
                        <td>{{ $item->lot_date ?? '-' }}</td>
                        <td class="num">{{ number_format($item->good_qty) }}</td>
                        <td class="num defect-highlight">{{ number_format($item->defects_qty) }}</td>
                        <td class="num" style="font-weight: bold;">{{ number_format($item->total_qty) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="part-totals">
            <strong>Buenas: {{ number_format($part->part_total_good) }}</strong>
            <strong class="defect-highlight">Malas: {{ number_format($part->part_total_defects) }}</strong>
            <strong>Total: {{ number_format($part->part_total) }}</strong>
            <strong>% Def: {{ $part->part_defect_rate }}%</strong>
        </div>
    @endforeach

    <div class="totals-section">
        <h3>Totales Generales</h3>
        <div class="totals-grid">
            <div class="cell">
                <div class="num">{{ number_format($inspection->total_good) }}</div>
                <div class="lbl">Piezas Buenas</div>
            </div>
            <div class="cell">
                <div class="num defect-highlight">{{ number_format($inspection->total_defects) }}</div>
                <div class="lbl">Piezas Malas</div>
            </div>
            <div class="cell">
                <div class="num">{{ number_format($inspection->total) }}</div>
                <div class="lbl">Total Piezas</div>
            </div>
            <div class="cell">
                <div class="num">{{ $inspection->defect_rate }}%</div>
                <div class="lbl">% Defectos</div>
            </div>
        </div>
    </div>

    @if ($inspection->comment_general)
        <div class="comment-section">
            <h4>Comentario General</h4>
            <p>{{ $inspection->comment_general }}</p>
        </div>
    @endif

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} | {{ $company->name }} | {{ $inspection->reference_code }}
    </div>
</body>
</html>
