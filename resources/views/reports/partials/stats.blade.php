@php
    $chartImages = $chartImages ?? [];
    $overall = (int) ($statistics['overall_progress'] ?? $report->overall_progress ?? 0);
    $stats = $statistics['stats'] ?? [];
    $hasCharts = ! empty($chartImages['status'])
        || ! empty($chartImages['phase'])
        || ! empty($chartImages['sub'])
        || ! empty($chartImages['act']);
@endphp

<div class="rp-page-banner">
    <div class="rp-page-banner__title">{{ $statsCopy['section_title'] }}</div>
    <div class="rp-page-banner__meta">{{ $projectTitle }}</div>
</div>

<div class="rp-kpi-row">
    <table class="rp-kpi-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="rp-kpi-cell">
                <div class="rp-kpi-card rp-kpi-card--overall">
                    <div class="rp-kpi-val">{{ $overall }}%</div>
                    <div class="rp-kpi-lbl">{{ $statsCopy['overall'] }}</div>
                </div>
            </td>
            <td class="rp-kpi-cell">
                <div class="rp-kpi-card">
                    <div class="rp-kpi-val">{{ (int) ($stats['total'] ?? 0) }}</div>
                    <div class="rp-kpi-lbl">{{ $statsCopy['kpi_total'] }}</div>
                </div>
            </td>
            <td class="rp-kpi-cell">
                <div class="rp-kpi-card rp-kpi-card--ok">
                    <div class="rp-kpi-val">{{ (int) ($stats['done'] ?? 0) }}</div>
                    <div class="rp-kpi-lbl">{{ $statsCopy['kpi_done'] }}</div>
                </div>
            </td>
            <td class="rp-kpi-cell">
                <div class="rp-kpi-card rp-kpi-card--warn">
                    <div class="rp-kpi-val">{{ (int) ($stats['in_progress'] ?? 0) }}</div>
                    <div class="rp-kpi-lbl">{{ $statsCopy['kpi_in_progress'] }}</div>
                </div>
            </td>
            <td class="rp-kpi-cell">
                <div class="rp-kpi-card rp-kpi-card--danger">
                    <div class="rp-kpi-val">{{ (int) ($stats['cancelled'] ?? 0) }}</div>
                    <div class="rp-kpi-lbl">{{ $statsCopy['kpi_cancelled'] }}</div>
                </div>
            </td>
        </tr>
    </table>
</div>

@if ($hasCharts)
    <table class="rp-charts-layout" cellpadding="0" cellspacing="0">
        <tr>
            <td class="rp-chart-slot rp-chart-slot--half">
                <div class="rp-chart-card">
                    <div class="rp-chart-card__title">{{ $statsCopy['chart_status'] }}</div>
                    @if (! empty($chartImages['status']))
                        <img src="{{ $chartImages['status'] }}" alt="" class="rp-chart-img">
                    @endif
                </div>
            </td>
            <td class="rp-chart-slot rp-chart-slot--half">
                <div class="rp-chart-card">
                    <div class="rp-chart-card__title">{{ $statsCopy['chart_phase'] }}</div>
                    @if (! empty($chartImages['phase']))
                        <img src="{{ $chartImages['phase'] }}" alt="" class="rp-chart-img">
                    @endif
                </div>
            </td>
        </tr>
        @if (! empty($chartImages['sub']))
        <tr>
            <td colspan="2" class="rp-chart-slot">
                <div class="rp-chart-card">
                    <div class="rp-chart-card__title">{{ $statsCopy['chart_sub'] }}</div>
                    <img src="{{ $chartImages['sub'] }}" alt="" class="rp-chart-img rp-chart-img--wide">
                </div>
            </td>
        </tr>
        @endif
        @if (! empty($chartImages['act']))
        <tr>
            <td colspan="2" class="rp-chart-slot">
                <div class="rp-chart-card">
                    <div class="rp-chart-card__title">{{ $statsCopy['chart_act'] }}</div>
                    <img src="{{ $chartImages['act'] }}" alt="" class="rp-chart-img rp-chart-img--wide">
                </div>
            </td>
        </tr>
        @endif
    </table>
@else
    <p class="rp-stats-missing-charts">
        {{ ($presentation->locale() ?? 'fr') === 'en'
            ? 'Charts are not available for this export. Please generate the PDF again from the report page.'
            : 'Graphiques indisponibles pour cet export. Regénérez le PDF depuis la page Rapport.' }}
    </p>
@endif
