<?php

namespace Database\Seeders;

use App\Models\Phase;
use App\Models\Project;
use App\Models\SubPhase;
use App\Models\Task;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class DailyConstrExcelImporter
{
    /**
     * @return array{
     *   phases:int,
     *   sub_phases:int,
     *   tasks:int
     * }
     */
    public function import(Project $project, ?string $path = null): array
    {
        $path = $path ?? base_path('dailyConstr.xlsx');
        if (! is_file($path)) {
            throw new RuntimeException("Fichier Excel introuvable : {$path}");
        }

        Phase::query()->where('project_id', $project->id)->delete();

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Feuil1') ?? $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);
        if ($matrix === []) {
            throw new RuntimeException('Le fichier Excel est vide.');
        }

        $headers = array_shift($matrix);
        $columns = $this->mapHeaders($headers);

        foreach (['phase', 'subphase', 'activity'] as $required) {
            if (! array_key_exists($required, $columns)) {
                throw new RuntimeException("Colonne obligatoire manquante dans l'Excel : {$required}");
            }
        }

        $phaseModels = [];
        $subPhaseModels = [];
        $phaseSort = [];
        $subPhaseSort = [];
        $taskSort = [];
        $counts = ['phases' => 0, 'sub_phases' => 0, 'tasks' => 0];

        foreach ($matrix as $row) {
            if (! $this->rowHasData($row)) {
                continue;
            }

            $phaseName = $this->cell($row, $columns, 'phase');
            $subPhaseName = $this->cell($row, $columns, 'subphase');
            $activity = $this->cell($row, $columns, 'activity');

            if ($phaseName === '' || $subPhaseName === '' || $activity === '') {
                continue;
            }

            if (! isset($phaseModels[$phaseName])) {
                $phaseModels[$phaseName] = Phase::create([
                    'project_id' => $project->id,
                    'name' => $phaseName,
                    'sort_order' => count($phaseSort),
                ]);
                $phaseSort[$phaseName] = count($phaseSort);
                $counts['phases']++;
            }

            $subKey = $phaseName.'|'.$subPhaseName;
            if (! isset($subPhaseModels[$subKey])) {
                $subPhaseModels[$subKey] = SubPhase::create([
                    'phase_id' => $phaseModels[$phaseName]->id,
                    'name' => $subPhaseName,
                    'sort_order' => $subPhaseSort[$phaseName] ?? 0,
                ]);
                $subPhaseSort[$phaseName] = ($subPhaseSort[$phaseName] ?? 0) + 1;
                $counts['sub_phases']++;
            }

            $taskKey = $subKey;
            $taskSort[$taskKey] = ($taskSort[$taskKey] ?? 0);

            Task::create([
                'sub_phase_id' => $subPhaseModels[$subKey]->id,
                'activity' => $activity,
                'start_day' => $this->intCell($row, $columns, 'start_day', 1),
                'duration_days' => $this->intCell($row, $columns, 'duration_days', 1),
                'sort_order' => $taskSort[$taskKey],
            ]);

            $taskSort[$taskKey]++;
            $counts['tasks']++;
        }

        if ($counts['tasks'] === 0) {
            throw new RuntimeException('Aucune activité importée depuis le fichier Excel.');
        }

        return $counts;
    }

    public function seedProject(?string $path = null): Project
    {
        $path = $path ?? base_path('dailyConstr.xlsx');
        if (! is_file($path)) {
            throw new RuntimeException("Fichier Excel introuvable : {$path}");
        }

        $profile = $this->readProjectProfile(IOFactory::load($path), $path);

        return Project::updateOrCreate(
            ['name' => $profile['name']],
            [
                'description' => $profile['description'],
                'client' => $profile['client'],
                'start_date' => $profile['start_date'],
                'end_date' => $profile['end_date'],
                'status' => $profile['status'],
            ]
        );
    }

    /**
     * @return array{
     *   name:string,
     *   description:?string,
     *   client:?string,
     *   start_date:?string,
     *   end_date:?string,
     *   status:string
     * }
     */
    private function readProjectProfile(Spreadsheet $spreadsheet, string $path): array
    {
        $stem = pathinfo($path, PATHINFO_FILENAME);
        $profile = [
            'name' => $this->humanizeStem($stem),
            'description' => "Structure importée depuis {$stem}.xlsx.",
            'client' => null,
            'start_date' => null,
            'end_date' => null,
            'status' => 'planifie',
        ];

        if (! $spreadsheet->sheetNameExists('Projet')) {
            return $profile;
        }

        $rows = $spreadsheet->getSheetByName('Projet')->toArray(null, true, true, false);
        foreach ($rows as $row) {
            $label = mb_strtolower(trim((string) ($row[0] ?? '')));
            $value = trim((string) ($row[1] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            if (str_contains($label, 'nom')) {
                $profile['name'] = $value;
            } elseif (str_contains($label, 'description')) {
                $profile['description'] = $value;
            } elseif (str_contains($label, 'client')) {
                $profile['client'] = $value;
            } elseif (str_contains($label, 'début') || str_contains($label, 'debut')) {
                $profile['start_date'] = $value;
            } elseif (str_contains($label, 'fin')) {
                $profile['end_date'] = $value;
            } elseif (str_contains($label, 'statut')) {
                $profile['status'] = $value;
            }
        }

        return $profile;
    }

    private function humanizeStem(string $stem): string
    {
        $label = str_replace(['_', '-'], ' ', $stem);
        $label = preg_replace('/\s+/', ' ', $label) ?? $stem;

        return mb_convert_case(trim($label), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $columns = [];

        foreach ($headers as $index => $header) {
            $key = $this->headerKey((string) $header);
            if ($key !== null) {
                $columns[$key] = (int) $index;
            }
        }

        return $columns;
    }

    private function headerKey(string $header): ?string
    {
        $normalized = mb_strtolower(trim($header));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? $ascii;

        return match (true) {
            $ascii === 'phase' => 'phase',
            str_contains($ascii, 'sous') && str_contains($ascii, 'phase') => 'subphase',
            str_contains($ascii, 'activ') => 'activity',
            str_contains($ascii, 'debut') => 'start_day',
            str_contains($ascii, 'duree') => 'duration_days',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     */
    private function rowHasData(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     */
    private function cell(array $row, array $columns, string $key): string
    {
        if (! array_key_exists($key, $columns)) {
            return '';
        }

        return trim((string) ($row[$columns[$key]] ?? ''));
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     */
    private function nullableCell(array $row, array $columns, string $key): ?string
    {
        $value = $this->cell($row, $columns, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     */
    private function intCell(array $row, array $columns, string $key, int $default): int
    {
        if (! array_key_exists($key, $columns)) {
            return $default;
        }

        $raw = $row[$columns[$key]] ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }

        return max(1, (int) round((float) $raw));
    }
}
