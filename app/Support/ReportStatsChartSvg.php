<?php

namespace App\Support;

final class ReportStatsChartSvg
{
    /**
     * Camembert — répartition par statut.
     *
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $labels
     */
    public static function statusPie(array $counts, array $labels): string
    {
        $segments = [
            ['termine', $labels['status_ok'] ?? 'Terminé', '#1a7a42'],
            ['en_cours', $labels['status_ip'] ?? 'En cours', '#c8521a'],
            ['non_demarre', $labels['status_nd'] ?? 'Non démarré', '#9a9285'],
            ['annule', $labels['status_cancel'] ?? 'Annulée', '#c01a1a'],
        ];
        $data = [];
        foreach ($segments as [$key, $label, $color]) {
            $v = (int) ($counts[$key] ?? 0);
            if ($v > 0) {
                $data[] = ['value' => $v, 'label' => $label, 'color' => $color];
            }
        }
        if ($data === []) {
            return '';
        }

        $total = array_sum(array_column($data, 'value'));
        $cx = 90;
        $cy = 90;
        $r = 72;
        $w = 200;
        $h = 200;
        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 '.$w.' '.$h.'" style="display:block">',
        ];
        $start = -M_PI / 2;
        foreach ($data as $seg) {
            $angle = ($seg['value'] / $total) * 2 * M_PI;
            $end = $start + $angle;
            $x1 = $cx + $r * cos($start);
            $y1 = $cy + $r * sin($start);
            $x2 = $cx + $r * cos($end);
            $y2 = $cy + $r * sin($end);
            $large = $angle > M_PI ? 1 : 0;
            $parts[] = sprintf(
                '<path d="M %s %s A %s %s 0 %d 1 %s %s L %s %s Z" fill="%s"/>',
                round($cx, 2),
                round($cy, 2),
                $r,
                $r,
                $large,
                round($x2, 2),
                round($y2, 2),
                round($cx, 2),
                round($cy, 2),
                $seg['color']
            );
            $start = $end;
        }
        $ly = 168;
        $lx = 8;
        foreach ($data as $seg) {
            $parts[] = sprintf(
                '<rect x="%d" y="%d" width="10" height="10" fill="%s"/>',
                $lx,
                $ly - 9,
                $seg['color']
            );
            $parts[] = sprintf(
                '<text x="%d" y="%d" font-family="Tahoma,DejaVu Sans,Verdana,sans-serif" font-size="8" fill="#1a1814">%s (%d)</text>',
                $lx + 14,
                $ly,
                self::xml($seg['label']),
                $seg['value']
            );
            $ly += 14;
        }
        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * Histogramme vertical — avancement par phase (%).
     *
     * @param  list<array{phase: string, progress: int}>  $phases
     */
    public static function phaseColumn(array $phases): string
    {
        if ($phases === []) {
            return '';
        }

        $n = count($phases);
        $padL = 28;
        $padB = 52;
        $padT = 12;
        $chartH = 130;
        $totalW = 360;
        $barAreaW = $totalW - $padL - 16;
        $barW = max(8, (int) floor($barAreaW / $n) - 6);
        $gap = max(4, (int) floor(($barAreaW - $barW * $n) / max(1, $n)));
        $totalH = $padT + $chartH + $padB;

        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 '.$totalW.' '.$totalH.'" style="display:block">',
        ];
        for ($t = 0; $t <= 4; $t++) {
            $pct = $t * 25;
            $y = $padT + $chartH - ($pct / 100) * $chartH;
            $parts[] = sprintf(
                '<line x1="%d" y1="%s" x2="%d" y2="%s" stroke="#e0ddd5" stroke-width="0.5"/>',
                $padL,
                round($y, 1),
                $totalW - 8,
                round($y, 1)
            );
            $parts[] = sprintf(
                '<text x="2" y="%s" font-family="Tahoma,DejaVu Sans,Verdana,sans-serif" font-size="7" fill="#6b6358">%d%%</text>',
                round($y + 3, 1),
                $pct
            );
        }
        $x = $padL + $gap / 2;
        foreach ($phases as $p) {
            $pct = min(100, max(0, (int) ($p['progress'] ?? 0)));
            $bh = ($pct / 100) * $chartH;
            $y = $padT + $chartH - $bh;
            $color = $pct >= 100 ? '#1a7a42' : ($pct > 0 ? '#c8521a' : '#d5cfc2');
            $parts[] = sprintf(
                '<rect x="%s" y="%s" width="%d" height="%s" fill="%s" rx="2"/>',
                round($x, 1),
                round($y, 1),
                $barW,
                max(0.5, round($bh, 1)),
                $color
            );
            $label = self::truncateLabel((string) ($p['phase'] ?? ''), 14);
            $parts[] = sprintf(
                '<text x="%s" y="%d" font-family="Tahoma,DejaVu Sans,Verdana,sans-serif" font-size="7" fill="#1a1814" transform="rotate(-35 %s %d)">%s</text>',
                round($x + $barW / 2, 1),
                $padT + $chartH + 14,
                round($x + $barW / 2, 1),
                $padT + $chartH + 14,
                self::xml($label)
            );
            $x += $barW + $gap;
        }
        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * Barres horizontales — sous-phases (progression moyenne).
     *
     * @param  list<array{phase: string, subphase: string, avg_progress: int, task_count?: int}>  $subphases
     */
    public static function subphaseBars(array $subphases): string
    {
        $rows = [];
        foreach ($subphases as $s) {
            $pct = (int) ($s['avg_progress'] ?? 0);
            $rows[] = [
                'label' => (string) ($s['subphase'] ?? ''),
                'value' => $pct,
                'color' => self::progressColor($pct),
            ];
        }

        return self::horizontalBars($rows);
    }

    /**
     * Barres horizontales — activités.
     *
     * @param  list<array{subphase: string, activity: string, progress: int, status?: string}>  $activities
     */
    public static function activityBars(array $activities): string
    {
        $rows = [];
        foreach ($activities as $a) {
            $pct = (int) ($a['progress'] ?? 0);
            $rows[] = [
                'label' => trim(((string) ($a['subphase'] ?? '')).' — '.((string) ($a['activity'] ?? ''))),
                'value' => $pct,
                'color' => self::activityColor($a),
            ];
        }

        return self::horizontalBars($rows);
    }

    /**
     * @param  list<array{label: string, value: int, color: string}>  $rows
     */
    private static function horizontalBars(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $rowH = 14;
        $pad = 6;
        $labelW = 210;
        $chartW = 340;
        $totalW = $labelW + $chartW + 44;
        $h = count($rows) * $rowH + $pad * 2;

        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 '.$totalW.' '.$h.'" style="display:block">',
        ];

        foreach ($rows as $i => $row) {
            $y = $pad + $i * $rowH;
            $pct = min(100, max(0, (int) $row['value']));
            $bw = ($pct / 100) * $chartW;
            $color = $row['color'];
            $label = self::truncateLabel((string) $row['label'], 38);

            $parts[] = sprintf(
                '<text x="0" y="%d" font-family="Tahoma,DejaVu Sans,Verdana,sans-serif" font-size="8" fill="#1a1814">%s</text>',
                $y + 10,
                self::xml($label)
            );
            $parts[] = sprintf(
                '<rect x="%d" y="%d" width="%d" height="10" fill="#e0e0e0" rx="2"/>',
                $labelW,
                $y + 2,
                $chartW
            );
            if ($bw > 0) {
                $parts[] = sprintf(
                    '<rect x="%d" y="%d" width="%d" height="10" fill="%s" rx="2"/>',
                    $labelW,
                    $y + 2,
                    max(1, (int) round($bw)),
                    $color
                );
            }
            $parts[] = sprintf(
                '<text x="%d" y="%d" font-family="Tahoma,DejaVu Sans,Verdana,sans-serif" font-size="8" font-weight="bold" fill="#1a1814">%d%%</text>',
                $labelW + $chartW + 6,
                $y + 10,
                $pct
            );
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    private static function progressColor(int $pct): string
    {
        if ($pct >= 100) {
            return '#1a7a42';
        }
        if ($pct > 0) {
            return '#c8521a';
        }

        return '#d5cfc2';
    }

    /**
     * @param  array{progress?: int, status?: string}  $a
     */
    private static function activityColor(array $a): string
    {
        if (($a['status'] ?? '') === 'annule') {
            return '#c01a1a';
        }
        $pct = (int) ($a['progress'] ?? 0);
        if ($pct >= 100 || ($a['status'] ?? '') === 'termine') {
            return '#1a7a42';
        }
        if ($pct > 0 || ($a['status'] ?? '') === 'en_cours') {
            return '#c8521a';
        }

        return '#d5cfc2';
    }

    private static function truncateLabel(string $label, int $max): string
    {
        if (mb_strlen($label) <= $max) {
            return $label;
        }

        return mb_substr($label, 0, $max - 1).'…';
    }

    private static function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
