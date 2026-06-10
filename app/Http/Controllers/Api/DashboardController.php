<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\DailyUpdate;
use App\Models\Project;
use App\Models\Task;
use App\Support\DashboardExcelChartFactory;
use App\Support\GdaLocale;
use App\Support\GdaStatus;
use App\Support\ProjectStatistics;
use App\Support\ReportPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    use ResolvesProject;

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);
        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));

        return response()->json($this->dashboardPayload($project, $presentation, $request->user()));
    }

    public function export(Request $request): StreamedResponse
    {
        $project = $this->resolveProject($request);
        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));
        $payload = $this->dashboardPayload($project, $presentation, $request->user());
        $spreadsheet = $this->buildDashboardSpreadsheet($project, $payload, $presentation);

        $slug = Str::slug($project->name);
        $suffix = $presentation->locale() === 'en' ? '-data-' : '-donnees-';
        $filename = $slug.$suffix.now()->format('Y-m-d-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(Project $project, ReportPresentation $presentation, $user = null): array
    {
        return ProjectStatistics::build($project, $presentation, $user);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildDashboardSpreadsheet(Project $project, array $payload, ReportPresentation $presentation): Spreadsheet
    {
        $xl = $presentation->dashboardExcelLabels();
        $loc = $presentation->locale();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('GD&A')
            ->setTitle(($loc === 'en' ? 'Dashboard — ' : 'Tableau de bord — ').$project->name);

        $headerFont = ['bold' => true, 'color' => ['rgb' => 'FFFFFF']];

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle($xl['sheet_summary']);
        $r = 1;
        $summary->setCellValue("A{$r}", $xl['summary_project']);
        $summary->setCellValue("B{$r}", $project->name);
        $r++;
        $summary->setCellValue("A{$r}", $xl['summary_export']);
        $summary->setCellValue("B{$r}", now()->format('d/m/Y H:i'));
        $r += 2;
        $summary->setCellValue("A{$r}", $xl['summary_overall']);
        $summary->setCellValue("B{$r}", $payload['overall_progress']);
        $r++;
        $stats = $payload['stats'];
        $summary->setCellValue("A{$r}", $xl['summary_total_tasks']);
        $summary->setCellValue("B{$r}", $stats['total']);
        $r++;
        $summary->setCellValue("A{$r}", $xl['summary_done']);
        $summary->setCellValue("B{$r}", $stats['done']);
        $r++;
        $summary->setCellValue("A{$r}", $xl['summary_in_progress']);
        $summary->setCellValue("B{$r}", $stats['in_progress']);
        $r++;
        $summary->setCellValue("A{$r}", $xl['summary_cancelled']);
        $summary->setCellValue("B{$r}", $stats['cancelled']);
        $r += 2;

        $statusHeaderRow = $r;
        $summary->setCellValue("A{$r}", $xl['summary_status']);
        $summary->setCellValue("B{$r}", $xl['summary_count']);
        $summary->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8521A');
        $summary->getStyle("A{$r}:B{$r}")->getFont()->applyFromArray($headerFont);
        $r++;
        $sc = $payload['charts']['status_counts'];
        $statusLabels = [
            'non_demarre' => $xl['status_nd'],
            'en_cours' => $xl['status_ip'],
            'termine' => $xl['status_ok'],
            'annule' => $xl['status_cancel'],
        ];
        foreach ($statusLabels as $key => $label) {
            $summary->setCellValue("A{$r}", $label);
            $summary->setCellValue("B{$r}", (int) ($sc[$key] ?? 0));
            $r++;
        }
        $statusLastDataRow = $r - 1;
        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);

        $phasesSheet = $spreadsheet->createSheet();
        $phasesSheet->setTitle($xl['sheet_phases']);
        $phasesSheet->fromArray([$xl['phases_col_phase'], $xl['phases_col_progress'], $xl['phases_col_count']], null, 'A1');
        $phasesSheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8521A');
        $phasesSheet->getStyle('A1:C1')->getFont()->applyFromArray($headerFont);
        $row = 2;
        foreach ($payload['progress_by_phase'] as $p) {
            $phasesSheet->setCellValue("A{$row}", $p['phase']);
            $phasesSheet->setCellValue("B{$row}", $p['progress']);
            $phasesSheet->setCellValue("C{$row}", $p['task_count']);
            $row++;
        }
        foreach (['A', 'B', 'C'] as $col) {
            $phasesSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $phasesLastDataRow = $row - 1;

        $subSheet = $spreadsheet->createSheet();
        $subSheet->setTitle($xl['sheet_subphases']);
        $subSheet->fromArray([$xl['sub_col_phase'], $xl['sub_col_sub'], $xl['sub_col_avg'], $xl['sub_col_count']], null, 'A1');
        $subSheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8521A');
        $subSheet->getStyle('A1:D1')->getFont()->applyFromArray($headerFont);
        $row = 2;
        foreach ($payload['charts']['subphases'] as $s) {
            $subSheet->setCellValue("A{$row}", $s['phase']);
            $subSheet->setCellValue("B{$row}", $s['subphase']);
            $subSheet->setCellValue("C{$row}", $s['avg_progress']);
            $subSheet->setCellValue("D{$row}", $s['task_count']);
            $row++;
        }
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $subSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $subphasesLastDataRow = $row - 1;

        $actSheet = $spreadsheet->createSheet();
        $actSheet->setTitle($xl['sheet_activities']);
        $actSheet->fromArray([
            $xl['act_col_phase'],
            $xl['act_col_sub'],
            $xl['act_col_activity'],
            $xl['act_col_progress'],
            $xl['act_col_status'],
            '',
            $xl['act_col_chart_label'],
        ], null, 'A1');
        $actSheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8521A');
        $actSheet->getStyle('A1:G1')->getFont()->applyFromArray($headerFont);
        $row = 2;
        foreach ($payload['charts']['activities'] as $a) {
            $actSheet->setCellValue("A{$row}", $a['phase']);
            $actSheet->setCellValue("B{$row}", $a['subphase']);
            $actSheet->setCellValue("C{$row}", $a['activity']);
            $actSheet->setCellValue("D{$row}", $a['progress']);
            $actSheet->setCellValue("E{$row}", $a['status_label'] ?? GdaStatus::label($a['status'] ?? 'non_demarre', $loc));
            $actSheet->setCellValue("G{$row}", $a['subphase'].' — '.$a['activity']);
            $row++;
        }
        foreach (['A', 'B', 'C', 'D', 'E', 'G'] as $col) {
            $actSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $actLast = $row - 1;
        if ($actLast >= 2) {
            $actSheet->getStyle('C2:G'.$actLast)->getAlignment()->setWrapText(true);
        }
        $activitiesLastDataRow = $actLast;

        $recentSheet = $spreadsheet->createSheet();
        $recentSheet->setTitle($xl['sheet_recent']);
        $recentSheet->fromArray([
            $xl['recent_col_time'],
            $xl['recent_col_task'],
            $xl['recent_col_detail'],
            $xl['recent_col_progress'],
            $xl['recent_col_user'],
            $xl['recent_col_status'],
        ], null, 'A1');
        $recentSheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8521A');
        $recentSheet->getStyle('A1:F1')->getFont()->applyFromArray($headerFont);
        $row = 2;
        foreach ($payload['recent_activity'] as $ra) {
            $recentSheet->setCellValue("A{$row}", $ra['time'] ?? '');
            $recentSheet->setCellValue("B{$row}", $ra['task_name'] ?? '');
            $recentSheet->setCellValue("C{$row}", $ra['action'] ?? '');
            $recentSheet->setCellValue("D{$row}", $ra['progress'] ?? 0);
            $recentSheet->setCellValue("E{$row}", $ra['user'] ?? '');
            $recentSheet->setCellValue("F{$row}", $ra['status_label'] ?? '');
            $row++;
        }
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $recentSheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($row > 2) {
            $recentSheet->getStyle('B2:C'.($row - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $chartOpts = [
            'sheet_summary' => $xl['sheet_summary'],
            'sheet_phases' => $xl['sheet_phases'],
            'sheet_subphases' => $xl['sheet_subphases'],
            'sheet_activities' => $xl['sheet_activities'],
            'chart_legend_count' => $xl['chart_legend_count'],
            'chart_axis_percent' => $xl['chart_axis_percent'],
            'chart_status_title' => $xl['chart_status_title'],
            'chart_phase_title' => $xl['chart_phase_title'],
            'chart_sub_title' => $xl['chart_sub_title'],
            'chart_act_title' => $xl['chart_act_title'],
            'chart_series_progress_pct' => $xl['chart_series_progress_pct'],
            'chart_series_avg_progress_pct' => $xl['chart_series_avg_progress_pct'],
        ];

        DashboardExcelChartFactory::addCharts(
            $spreadsheet,
            $statusHeaderRow,
            $statusLastDataRow,
            $phasesLastDataRow,
            $subphasesLastDataRow,
            $activitiesLastDataRow,
            $chartOpts,
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
