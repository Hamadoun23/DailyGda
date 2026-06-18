<!DOCTYPE html>
<html lang="{{ ($presentation->locale() ?? 'fr') === 'en' ? 'en' : 'fr' }}">
<head>
    <meta charset="UTF-8">
    <title>Rapport chantier GDA</title>
    <style>
        @page { margin: 8mm 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #2a2620;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* Bandeau de section */
        .rp-page-banner {
            background-color: #1a3a5c;
            color: #ffffff;
            padding: 14px 18px;
            margin-bottom: 16px;
            border-left: 5px solid #c8521a;
        }
        .rp-page-banner__title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin-bottom: 5px;
        }
        .rp-page-banner__meta {
            font-size: 9px;
            color: #c8d8e8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            line-height: 1.4;
        }

        /* KPI */
        .rp-kpi-table { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 16px; }
        .rp-kpi-cell { width: 20%; vertical-align: top; padding: 0; }
        .rp-kpi-card {
            background: #ffffff;
            border: 1px solid #e4dfd6;
            border-top: 3px solid #1a3a5c;
            border-radius: 8px;
            padding: 12px 8px 10px;
            text-align: center;
        }
        .rp-kpi-val { font-size: 22px; font-weight: bold; color: #1a3a5c; line-height: 1; }
        .rp-kpi-lbl { font-size: 8px; color: #6b6358; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: bold; }
        .rp-kpi-card--overall { background: #f0f5fa; border-color: #c8d8e8; border-top-color: #c8521a; }
        .rp-kpi-card--overall .rp-kpi-val { color: #c8521a; font-size: 24px; }
        .rp-kpi-card--ok { border-top-color: #1a7a42; }
        .rp-kpi-card--ok .rp-kpi-val { color: #1a7a42; }
        .rp-kpi-card--warn { border-top-color: #c8521a; }
        .rp-kpi-card--warn .rp-kpi-val { color: #c8521a; }
        .rp-kpi-card--danger { border-top-color: #c01a1a; }
        .rp-kpi-card--danger .rp-kpi-val { color: #c01a1a; }

        /* Graphiques — une seule coupure avant la section données (évite page blanche DomPDF) */
        .rp-stats-page { page-break-after: always; }
        .rp-charts-layout { width: 100%; border-collapse: collapse; }
        .rp-chart-slot { vertical-align: top; padding: 0 0 12px 0; }
        .rp-chart-slot--half { width: 50%; }
        .rp-chart-slot--half:first-child { padding-right: 8px; }
        .rp-chart-slot--half:last-child { padding-left: 8px; }
        .rp-chart-card {
            background: #ffffff;
            border: 1px solid #e4dfd6;
            border-radius: 10px;
            padding: 12px 14px 14px;
        }
        .rp-chart-card__title {
            font-size: 9px;
            font-weight: bold;
            color: #8b2c1c;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0ebe3;
        }
        .rp-chart-img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .rp-stats-missing-charts { font-size: 10px; color: #8a8070; margin: 12px 0; padding: 12px; background: #f7f4ef; border-radius: 6px; }

        /* Page données */
        .rp-report-data-section { margin-top: 0; }
        .rp-report-hero {
            text-align: center;
            padding: 14px 16px 16px;
            margin-bottom: 14px;
            border: 1px solid #e4dfd6;
            border-radius: 8px;
            background: #faf8f4;
        }
        .rp-report-hero__project {
            font-size: 11px;
            font-weight: bold;
            color: #1a3a5c;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.35;
            margin-bottom: 6px;
        }
        .rp-report-hero__progress {
            font-size: 11px;
            color: #6b6358;
            margin-bottom: 4px;
        }
        .rp-report-hero__title {
            font-size: 22px;
            font-weight: bold;
            color: #c8521a;
            margin: 4px 0 12px;
        }
        .rp-meta-table { width: auto; margin: 0 auto; border-collapse: collapse; }
        .rp-meta-table td { padding: 3px 14px; font-size: 10px; }
        .rp-meta-k { color: #1a3a5c; font-weight: bold; text-align: right; }
        .rp-meta-v { color: #c8521a; font-weight: bold; }

        /* Tableau tâches */
        table.rp-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.rp-table thead { display: table-header-group; }
        table.rp-table th {
            background: #381419;
            color: #fff;
            padding: 9px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: bold;
        }
        table.rp-table td {
            border-bottom: 1px solid #e8e4dc;
            padding: 7px 8px;
            vertical-align: middle;
        }
        table.rp-table tbody tr:nth-child(even) td { background: #faf8f4; }
        table.rp-table tr { page-break-inside: avoid; }
        .rp-phase-cell {
            font-weight: bold;
            color: #1a3a5c;
            font-size: 11px;
            background: #eef3f8 !important;
            border-right: 2px solid #c8d8e8 !important;
        }
        .rp-subphase-cell { font-weight: bold; color: #1a3a5c; font-size: 10px; }
        .rp-progress-cell { text-align: center; white-space: nowrap; }
        .bar-bg {
            display: inline-block;
            width: 72px;
            height: 9px;
            background: #e8e4dc;
            border-radius: 4px;
            overflow: hidden;
            vertical-align: middle;
            margin-right: 4px;
        }
        .bar-fill { height: 100%; background: #c8521a; }
        .bar-fill.done { background: #1a7a42; }
        .st-termine { color: #1a7a42; font-weight: bold; }
        .st-encours { color: #c8521a; font-weight: bold; }
        .st-nondemarre { color: #8a8070; font-weight: bold; }
        .st-annule { color: #c01a1a; font-weight: bold; }
        .status-note { display: block; font-size: 8px; font-weight: normal; margin-top: 2px; color: #8a8070; }
        .rp-row-hidden td { background: #fff8f0 !important; }

        /* Pied de page progression */
        .rp-footer-wrap {
            margin-top: 14px;
            border: 1px solid #e4dfd6;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
        }
        .footer-band {
            background: #1a3a5c;
            color: #ffffff;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .footer-pct {
            font-size: 36px;
            font-weight: bold;
            color: #c8521a;
            padding: 10px 12px 14px;
            background: #faf8f4;
        }

        /* Photos */
        .photo-page { page-break-before: always; padding-top: 4px; }
        .photo-head {
            background-color: #1a3a5c;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-left: 5px solid #c8521a;
        }
        .photo-eyebrow {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #adc8e0;
            margin-bottom: 3px;
        }
        .photo-title {
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
        }
        .photo-count {
            font-size: 9px;
            color: #c8d8e8;
            margin-top: 4px;
        }
        table.photo-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 10px;
        }
        table.photo-grid td {
            width: 50%;
            padding: 0;
            vertical-align: top;
        }
        table.photo-grid img {
            width: 100%;
            height: auto;
            display: block;
            border: 1px solid #d5cfc2;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="rp-stats-page">
        @include('reports.partials.stats')
    </div>

    <div class="rp-report-data-section">
        <div class="rp-page-banner">
            <div class="rp-page-banner__title">{{ $statsCopy['data_section'] ?? 'Données du rapport' }}</div>
            <div class="rp-page-banner__meta">{{ $projectTitle }}</div>
        </div>

        <div class="rp-report-hero">
            <div class="rp-report-hero__project">{{ $projectTitle }}</div>
            <div class="rp-report-hero__progress">{{ $copy['progress_title'] }} : <strong>{{ $display_overall_progress ?? $statistics['overall_progress'] ?? $report->overall_progress }}%</strong></div>
            <div class="rp-report-hero__title">{{ $copy['report_title'] }}</div>
            <table class="rp-meta-table">
                <tr>
                    <td class="rp-meta-k">{{ $copy['date'] }}</td>
                    <td class="rp-meta-v">{{ $report->report_date->format('d/m/Y') }}</td>
                    <td class="rp-meta-k">{{ $copy['temperature'] }}</td>
                    <td class="rp-meta-v">{{ $report->temperature !== null ? $report->temperature.'°C' : '—' }}</td>
                    <td class="rp-meta-k">{{ $copy['weather'] }}</td>
                    <td class="rp-meta-v">{{ $report->weather ? $presentation->translate($report->weather, 'weather') : '—' }}</td>
                </tr>
            </table>
        </div>

        <table class="rp-table">
            <thead>
                <tr>
                    <th style="width:14%">{{ $copy['cols']['phase'] }}</th>
                    <th style="width:14%">{{ $copy['cols']['subphase'] }}</th>
                    <th style="width:24%">{{ $copy['cols']['activity'] }}</th>
                    <th style="width:10%;text-align:center">{{ $copy['cols']['start'] }}</th>
                    <th style="width:16%;text-align:center">{{ $copy['cols']['progress'] }}</th>
                    <th style="width:8%;text-align:center">{{ $copy['cols']['duration'] }}</th>
                    <th style="width:14%;text-align:center">{{ $copy['cols']['status'] }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($pdf_rows as $t)
                @php
                    $partnerHiddenRow = ! empty($show_admin_partner_markers) && ! empty($t['partner_hidden']);
                    $partnerMark = function (string $level, bool $when = true) use ($partnerHiddenRow, $t, $copy): string {
                        if (! $partnerHiddenRow || ! $when || empty($t[$level])) {
                            return '';
                        }

                        return ' <span style="font-size:8px;color:#8b4513;">['.e($copy['partner_hidden'] ?? 'Masqué partenaire').']</span>';
                    };
                @endphp
                <tr @if ($partnerHiddenRow) class="rp-row-hidden" @endif>
                    <td class="rp-phase-cell">{!! e($t['phase']).$partnerMark('phase_hidden_from_partner') !!}</td>
                    <td class="rp-subphase-cell">{!! e($t['subphase']).$partnerMark('subphase_hidden_from_partner', empty($t['phase_hidden_from_partner'])) !!}</td>
                    <td>{!! e($t['activity']).$partnerMark('hidden_from_partner', empty($t['phase_hidden_from_partner']) && empty($t['subphase_hidden_from_partner'])) !!}</td>
                    <td style="text-align:center;color:#6b6358">{{ $t['start_label'] }}</td>
                    <td class="rp-progress-cell">
                        <span class="bar-bg"><span class="bar-fill {{ ($t['progress'] ?? 0) >= 100 ? 'done' : '' }}" style="width: {{ min(100, (int) ($t['progress'] ?? 0)) }}%; display: block;"></span></span>
                        <strong>{{ (int) ($t['progress'] ?? 0) }}%</strong>
                    </td>
                    <td style="text-align:center;color:#6b6358">{{ $t['duration_label'] }}</td>
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

        <div class="rp-footer-wrap">
            <div class="footer-band">{{ $copy['overall_progress'] }}</div>
            <div class="footer-pct">{{ $display_overall_progress ?? $statistics['overall_progress'] ?? $report->overall_progress }}%</div>
        </div>
    </div>

    @foreach ($pdfPhotoSections as $section)
    <div class="photo-page">
        <div class="photo-head">
            <div class="photo-eyebrow">{{ $copy['photos_prefix'] }}</div>
            <div class="photo-title">{{ $section['title'] }}</div>
            <div class="photo-count">{{ $section['count'] }} photo{{ $section['count'] > 1 ? 's' : '' }}</div>
        </div>
        <table class="photo-grid">
            @foreach (array_chunk($section['images'], 2) as $chunk)
            <tr>
                @foreach ($chunk as $src)
                <td><img src="{{ $src }}" alt=""></td>
                @endforeach
                @if (count($chunk) === 1)
                <td></td>
                @endif
            </tr>
            @endforeach
        </table>
    </div>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(720, 560, '{{ $pageLabel ?? $copy['page'] ?? 'Page' }} {PAGE_NUM} / {PAGE_COUNT}', $font, 9, [0.42, 0.39, 0.35]);
        }
    </script>
</body>
</html>
