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
