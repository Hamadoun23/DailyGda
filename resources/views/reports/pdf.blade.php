<!DOCTYPE html>
<html lang="{{ ($presentation->locale() ?? 'fr') === 'en' ? 'en' : 'fr' }}">
<head>
    <meta charset="UTF-8">
    <title>Rapport chantier GDA</title>
    <style>
        @page { margin: 8mm; size: A4 landscape; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1814; margin: 0; padding: 10px; }
        .rp-header { display: table; width: 100%; border-bottom: 3px solid #1a3a5c; padding-bottom: 12px; margin-bottom: 12px; }
        .rp-header-left { display: table-cell; width: 72%; vertical-align: top; text-align: center; }
        .rp-header-right { display: table-cell; width: 28%; vertical-align: top; border-left: 2px solid #1a3a5c; padding-left: 14px; font-size: 12px; line-height: 1.6; }
        .rp-brand { font-size: 14px; font-weight: bold; color: #1a3a5c; }
        .rp-progress-line { font-size: 14px; font-weight: bold; color: #1a3a5c; margin-top: 4px; }
        .rp-title-orange { font-size: 20px; font-weight: bold; color: #c8521a; margin-top: 6px; }
        .rp-meta-k { color: #1a3a5c; font-weight: bold; }
        .rp-meta-v { color: #c8521a; font-weight: bold; }
        table.rp-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.rp-table thead { display: table-header-group; }
        table.rp-table th { background: #8b2c1c; color: #fff; padding: 9px 10px; text-align: left; font-size: 12px; }
        table.rp-table td { border: 0.5px solid #d5cfc2; padding: 8px 10px; vertical-align: middle; font-size: 12px; }
        table.rp-table tr { page-break-inside: avoid; }
        table.rp-table tr:nth-child(even) td { background: #faf8f4; }
        .rp-phase-cell { font-weight: bold; color: #1a3a5c; font-size: 13px; }
        .bar-bg { display: inline-block; width: 64px; height: 8px; background: #e0e0e0; border-radius: 2px; overflow: hidden; vertical-align: middle; }
        .bar-fill { height: 100%; border-radius: 2px; background: #c8521a; }
        .bar-fill.done { background: #1a7a42; }
        .footer-band { text-align: center; background: #f4f1eb; padding: 10px; margin-top: 10px; font-weight: bold; font-size: 13px; color: #1a3a5c; }
        .footer-pct { text-align: center; font-size: 32px; font-weight: bold; color: #c8521a; margin: 8px 0; }
        .photo-page {
            page-break-before: always;
            padding: 12px 10px 8px;
        }

        /* En-tête de section photos : deux cellules simulant le dégradé bleu → orange */
        .photo-head-wrap {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border-radius: 8px;
        }
        .photo-head-left {
            display: table-cell;
            width: 76%;
            background-color: #1a3a5c;
            padding: 14px 20px;
            vertical-align: middle;
            border-radius: 8px 0 0 8px;
        }
        .photo-head-right {
            display: table-cell;
            width: 24%;
            background-color: #c8521a;
            padding: 14px 16px;
            vertical-align: middle;
            text-align: right;
            border-radius: 0 8px 8px 0;
        }
        .photo-eyebrow {
            display: block;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #adc8e0;
            margin-bottom: 4px;
        }
        .photo-title {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
        }
        .photo-count-text {
            font-size: 28px;
            font-weight: bold;
            color: #ffffff;
            display: block;
        }
        .photo-count-label {
            font-size: 10px;
            color: rgba(255,255,255,.8);
            display: block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Grille photos */
        table.photo-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 6px;
        }
        table.photo-grid td {
            width: 33.33%;
            padding: 0;
            vertical-align: top;
        }
        table.photo-grid img {
            width: 100%;
            height: 175px;
            border: 1px solid #d5cfc2;
            border-radius: 6px;
            display: block;
        }
        .st-termine { color: #1a7a42; font-weight: bold; }
        .st-encours { color: #c8521a; font-weight: bold; }
        .st-nondemarre { color: #8a8070; font-weight: bold; }
        .st-annule { color: #c01a1a; font-weight: bold; }
        .status-note { display: block; font-size: 10px; font-weight: normal; margin-top: 3px; color: #8a8070; }
    </style>
</head>
<body>
    <div class="rp-header">
        <div class="rp-header-left">
            <div class="rp-brand">{{ $projectTitle }}</div>
            <div class="rp-progress-line">{{ $copy['progress_title'] }} ({{ $report->overall_progress }}%)</div>
            <div class="rp-title-orange">{{ $copy['report_title'] }}</div>
        </div>
        <div class="rp-header-right">
            <div style="margin-bottom: 4px;"><span class="rp-meta-k">{{ $copy['date'] }} :</span> <span class="rp-meta-v">{{ $report->report_date->format('d/m/Y') }}</span></div>
            <div style="margin-bottom: 4px;"><span class="rp-meta-k">{{ $copy['temperature'] }} :</span> <span class="rp-meta-v">{{ $report->temperature !== null ? $report->temperature.'°C' : '—' }}</span></div>
            <div style="margin-bottom: 4px;"><span class="rp-meta-k">{{ $copy['weather'] }} :</span> <span class="rp-meta-v">{{ $report->weather ? $presentation->translate($report->weather, 'weather') : '—' }}</span></div>
        </div>
    </div>

    <table class="rp-table">
        <thead>
            <tr>
                <th>{{ $copy['cols']['phase'] }}</th>
                <th>{{ $copy['cols']['subphase'] }}</th>
                <th>{{ $copy['cols']['activity'] }}</th>
                <th style="text-align:center">{{ $copy['cols']['start'] }}</th>
                <th style="text-align:center">{{ $copy['cols']['progress'] }}</th>
                <th style="text-align:center">{{ $copy['cols']['duration'] }}</th>
                <th style="text-align:center">{{ $copy['cols']['status'] }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($pdf_rows as $t)
            <tr>
                <td class="rp-phase-cell" style="border-right: 2px solid #d5cfc2;">{{ $t['phase'] }}</td>
                <td style="font-weight: bold; color: #1a3a5c; font-size: 12px;">{{ $t['subphase'] }}</td>
                <td>{{ $t['activity'] }}</td>
                <td style="text-align:center">{{ $t['start_label'] }}</td>
                <td style="text-align:center">
                    <span class="bar-bg"><span class="bar-fill {{ ($t['progress'] ?? 0) >= 100 ? 'done' : '' }}" style="width: {{ min(100, (int) ($t['progress'] ?? 0)) }}%; display: block;"></span></span>
                    <strong>{{ (int) ($t['progress'] ?? 0) }}%</strong>
                </td>
                <td style="text-align:center">{{ $t['duration_label'] }}</td>
                @php
                    $cls = match ($t['status']) {
                        'termine' => 'st-termine',
                        'en_cours' => 'st-encours',
                        'annule' => 'st-annule',
                        default => 'st-nondemarre',
                    };
                @endphp
                <td style="text-align:center" class="{{ $cls }}">
                    {{ $t['status_label'] }}
                    @if (! empty($t['status_comment']))
                        <span class="status-note">{{ $t['status_comment'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="footer-band">{{ $copy['overall_progress'] }}</div>
    <div class="footer-pct">{{ $report->overall_progress }}%</div>

    @foreach ($pdfPhotoSections as $section)
    <div class="photo-page">
        <div class="photo-head-wrap">
            <div class="photo-head-left">
                <span class="photo-eyebrow">{{ $copy['photos_prefix'] }}</span>
                <div class="photo-title">{{ $section['title'] }}</div>
            </div>
            <div class="photo-head-right">
                <span class="photo-count-text">{{ $section['count'] }}</span>
                <span class="photo-count-label">photo{{ $section['count'] > 1 ? 's' : '' }}</span>
            </div>
        </div>
        <table class="photo-grid">
            @foreach (array_chunk($section['images'], 3) as $chunk)
            <tr>
                @foreach ($chunk as $src)
                <td><img src="{{ $src }}" alt=""></td>
                @endforeach
                @for ($k = count($chunk); $k < 3; $k++)
                <td></td>
                @endfor
            </tr>
            @endforeach
        </table>
    </div>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'bold');
            $pdf->page_text(650, 42, '{{ $copy['page'] }} : {PAGE_NUM} / {PAGE_COUNT}', $font, 11, [0.784, 0.322, 0.102]);
        }
    </script>
</body>
</html>
