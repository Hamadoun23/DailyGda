<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportPhotos extends Command
{
    protected $signature = 'photos:import {category} {source} {--project=} {--user=}';

    protected $description = 'Importe en masse un dossier de photos (FTP/Gestionnaire de fichiers) vers un projet, avec compression automatique';

    /** @var list<string> */
    private const CATEGORIES = ['avant', 'pendant', 'apres', 'securite', 'qualite'];

    /** @var list<string> */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function handle(): int
    {
        $category = (string) $this->argument('category');
        if (! in_array($category, self::CATEGORIES, true)) {
            $this->error('Catégorie invalide. Utilisez : '.implode(', ', self::CATEGORIES));

            return self::FAILURE;
        }

        $source = rtrim((string) $this->argument('source'), '/\\');
        if (! is_dir($source)) {
            $this->error("Dossier introuvable : {$source}");

            return self::FAILURE;
        }

        $project = $this->option('project')
            ? Project::find((int) $this->option('project'))
            : Project::orderBy('id')->first();
        if (! $project) {
            $this->error('Projet introuvable. Utilise --project=ID pour préciser lequel.');

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::where('username', $this->option('user'))->first()
            : User::where('role', 'admin')->orderBy('id')->first();
        if (! $user) {
            $this->error('Utilisateur introuvable. Utilise --user=nomutilisateur pour préciser lequel.');

            return self::FAILURE;
        }

        $files = collect(scandir($source) ?: [])
            ->reject(fn (string $f) => in_array($f, ['.', '..'], true))
            ->filter(fn (string $f) => is_file($source.DIRECTORY_SEPARATOR.$f))
            ->filter(fn (string $f) => in_array(strtolower((string) pathinfo($f, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values();

        if ($files->isEmpty()) {
            $this->warn('Aucune image trouvée dans ce dossier.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Import de %d photo(s) — projet "%s", catégorie "%s".',
            $files->count(),
            $project->name,
            $category
        ));

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        $imported = 0;
        $failed = 0;

        foreach ($files as $filename) {
            $sourcePath = $source.DIRECTORY_SEPARATOR.$filename;
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            $directory = 'photos/'.$category;
            Storage::disk('public')->makeDirectory($directory);
            $relative = $directory.'/'.Str::uuid()->toString().'.'.$ext;
            $destination = Storage::disk('public')->path($relative);

            if (! @copy($sourcePath, $destination)) {
                $failed++;
                $bar->advance();

                continue;
            }

            ImageOptimizer::optimizeInPlace($destination);

            Photo::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'category' => $category,
                'path' => $relative,
                'original_name' => $filename,
                'file_size' => is_file($destination) ? filesize($destination) : null,
                'taken_at' => date('Y-m-d', filemtime($sourcePath) ?: time()),
            ]);

            @unlink($sourcePath);
            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$imported} photo(s) importée(s), {$failed} échec(s).");

        return self::SUCCESS;
    }
}
