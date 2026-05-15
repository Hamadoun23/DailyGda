@php
    $chartImages = $chartImages ?? [];
    $overall = (int) ($statistics['overall_progress'] ?? $report->overall_progress ?? 0);
    $hasCharts = ! empty($chartImages['status'])
        || ! empty($chartImages['phase'])
        || ! empty($chartImages['sub'])
        || ! empty($chartImages['act']);
@endphp

<div class="rp-stats-section">
    <div class="rp-stats-main-title">{{ $statsCopy['section_title'] }}</div>
    <div class="rp-stats-project">{{ $projectTitle }} — {{ $statsCopy['overall'] }} : <strong>{{ $overall }}%</strong></div>

    @if ($hasCharts)
        <table class="rp-pdf-charts-grid" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">
            <tr>
                <td class="rp-pdf-chart-cell" style="width:50%;vertical-align:top;padding-right:8px">
                    <div class="rp-stats-block-title">{{ $statsCopy['chart_status'] }}</div>
                    @if (! empty($chartImages['status']))
                        <img src="{{ $chartImages['status'] }}" alt="" class="rp-pdf-chart-img rp-pdf-chart-img--pie">
                    @endif
                </td>
                <td class="rp-pdf-chart-cell" style="width:50%;vertical-align:top;padding-left:8px">
                    <div class="rp-stats-block-title">{{ $statsCopy['chart_phase'] }}</div>
                    @if (! empty($chartImages['phase']))
                        <img src="{{ $chartImages['phase'] }}" alt="" class="rp-pdf-chart-img rp-pdf-chart-img--phase">
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top:12px">
                    <div class="rp-stats-block-title">{{ $statsCopy['chart_sub'] }}</div>
                    @if (! empty($chartImages['sub']))
                        <img src="{{ $chartImages['sub'] }}" alt="" class="rp-pdf-chart-img rp-pdf-chart-img--sub">
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top:12px">
                    <div class="rp-stats-block-title">{{ $statsCopy['chart_act'] }}</div>
                    @if (! empty($chartImages['act']))
                        <img src="{{ $chartImages['act'] }}" alt="" class="rp-pdf-chart-img rp-pdf-chart-img--act">
                    @endif
                </td>
            </tr>
        </table>
    @else
        <p class="rp-stats-missing-charts">
            {{ ($presentation->locale() ?? 'fr') === 'en'
                ? 'Charts are not available for this export. Please generate the PDF again from the report page.'
                : 'Graphiques indisponibles pour cet export. Regénérez le PDF depuis la page Rapport.' }}
        </p>
    @endif
</div>
