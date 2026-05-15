<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class DashboardExcelChartFactory
{
    private static function quotedRange(string $sheetTitle, string $range): string
    {
        $escaped = str_replace("'", "''", $sheetTitle);

        return "'{$escaped}'!{$range}";
    }

    /**
     * @param  array<string, string>  $options
     *   sheet_summary, sheet_phases, sheet_subphases, sheet_activities,
     *   chart_legend_count, chart_axis_percent, chart_status_title, chart_phase_title,
     *   chart_sub_title, chart_act_title, chart_series_progress_pct, chart_series_avg_progress_pct
     */
    public static function addCharts(
        Spreadsheet $spreadsheet,
        int $statusHeaderRow,
        int $statusLastDataRow,
        int $phasesLastDataRow,
        int $subphasesLastDataRow,
        int $activitiesLastDataRow,
        array $options = [],
    ): void {
        $o = array_merge([
            'sheet_summary' => 'Synthèse',
            'sheet_phases' => 'Phases',
            'sheet_subphases' => 'Sous-phases',
            'sheet_activities' => 'Activités',
            'chart_legend_count' => 'Nombre',
            'chart_axis_percent' => 'Pourcentage',
            'chart_status_title' => 'Répartition par statut',
            'chart_phase_title' => 'Avancement par phase (%)',
            'chart_sub_title' => 'Sous-phases — progression moyenne',
            'chart_act_title' => 'Activités — progression',
            'chart_series_progress_pct' => 'Progression (%)',
            'chart_series_avg_progress_pct' => 'Progression moy. (%)',
        ], $options);

        $summary = $spreadsheet->getSheetByName($o['sheet_summary']);
        $phasesSheet = $spreadsheet->getSheetByName($o['sheet_phases']);
        $subSheet = $spreadsheet->getSheetByName($o['sheet_subphases']);
        $actSheet = $spreadsheet->getSheetByName($o['sheet_activities']);

        if ($summary instanceof Worksheet) {
            self::addStatusPieChart($summary, $statusHeaderRow, $statusLastDataRow, $o);
        }

        if ($phasesSheet instanceof Worksheet && $phasesLastDataRow >= 2) {
            self::addPhaseColumnChart($phasesSheet, $phasesLastDataRow, $o);
        }

        if ($subSheet instanceof Worksheet && $subphasesLastDataRow >= 2) {
            self::addSubphaseBarChart($subSheet, $subphasesLastDataRow, $o);
        }

        if ($actSheet instanceof Worksheet && $activitiesLastDataRow >= 2) {
            self::addActivityBarChart($actSheet, $activitiesLastDataRow, $o);
        }
    }

    /**
     * @param  array<string, string>  $o
     */
    private static function addStatusPieChart(Worksheet $sheet, int $headerRow, int $lastDataRow, array $o): void
    {
        $title = $sheet->getTitle();
        $first = $headerRow + 1;
        $n = max(1, $lastDataRow - $headerRow);
        $catRange = self::quotedRange($title, '$A$'.$first.':$A$'.$lastDataRow);
        $valRange = self::quotedRange($title, '$B$'.$first.':$B$'.$lastDataRow);

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$o['chart_legend_count']]),
        ];
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRange, null, $n),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRange, null, $n),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            null,
            [0],
            $labels,
            $categories,
            $values
        );

        $plot = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chart = new Chart(
            'chart_statuts',
            new Title($o['chart_status_title']),
            $legend,
            $plot,
            true,
            DataSeries::EMPTY_AS_GAP,
        );
        $chart->setTopLeftPosition('D1');
        $chart->setBottomRightPosition('M16');
        $sheet->addChart($chart);
    }

    /**
     * @param  array<string, string>  $o
     */
    private static function addPhaseColumnChart(Worksheet $sheet, int $lastDataRow, array $o): void
    {
        $title = $sheet->getTitle();
        $n = $lastDataRow - 1;
        $catRange = self::quotedRange($title, '$A$2:$A$'.$lastDataRow);
        $valRange = self::quotedRange($title, '$B$2:$B$'.$lastDataRow);

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$o['chart_series_progress_pct']]),
        ];
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRange, null, $n),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRange, null, $n),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plot = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chart = new Chart(
            'chart_phases',
            new Title($o['chart_phase_title']),
            $legend,
            $plot,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            new Title($o['chart_axis_percent']),
        );
        $chart->setTopLeftPosition('E1');
        $chart->setBottomRightPosition('P22');
        $sheet->addChart($chart);
    }

    /**
     * @param  array<string, string>  $o
     */
    private static function addSubphaseBarChart(Worksheet $sheet, int $lastDataRow, array $o): void
    {
        $title = $sheet->getTitle();
        $n = $lastDataRow - 1;
        $catRange = self::quotedRange($title, '$B$2:$B$'.$lastDataRow);
        $valRange = self::quotedRange($title, '$C$2:$C$'.$lastDataRow);

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$o['chart_series_avg_progress_pct']]),
        ];
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRange, null, $n),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRange, null, $n),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_BAR);

        $plot = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chart = new Chart(
            'chart_sous_phases',
            new Title($o['chart_sub_title']),
            $legend,
            $plot,
            true,
            DataSeries::EMPTY_AS_GAP,
            new Title($o['chart_axis_percent']),
            null,
        );
        $chart->setTopLeftPosition('F1');
        $chart->setBottomRightPosition('AD'.min(36, max(18, 4 + $n)));
        $sheet->addChart($chart);
    }

    /**
     * @param  array<string, string>  $o
     */
    private static function addActivityBarChart(Worksheet $sheet, int $lastDataRow, array $o): void
    {
        $title = $sheet->getTitle();
        $n = $lastDataRow - 1;
        $catRange = self::quotedRange($title, '$G$2:$G$'.$lastDataRow);
        $valRange = self::quotedRange($title, '$D$2:$D$'.$lastDataRow);

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$o['chart_series_progress_pct']]),
        ];
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRange, null, $n),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRange, null, $n),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_BAR);

        $plot = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chartBottomRow = min(80, max(24, 8 + $n));
        $chart = new Chart(
            'chart_activites',
            new Title($o['chart_act_title']),
            $legend,
            $plot,
            true,
            DataSeries::EMPTY_AS_GAP,
            new Title($o['chart_axis_percent']),
            null,
        );
        $chart->setTopLeftPosition('H1');
        $chart->setBottomRightPosition('AG'.$chartBottomRow);
        $sheet->addChart($chart);
    }
}
