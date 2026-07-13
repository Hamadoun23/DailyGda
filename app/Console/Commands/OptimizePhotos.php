<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizePhotos extends Command
{
    protected $signature = 'photos:optimize';

    protected $description = 'Compresse sur disque toutes les photos JPEG déjà stockées (rétroactif)';

    private const MIN_BYTES = 400 * 1024;

    public function handle(): int
    {
        // Ne rescanne que les photos jamais traitées ou encore lourdes, pour rester
        // léger sur une exécution planifiée toutes les 15 minutes.
        $photos = Photo::query()
            ->whereNotNull('path')
            ->where(function ($q) {
                $q->where('file_size', '>', self::MIN_BYTES)->orWhereNull('file_size');
            })
            ->get();
        $processed = 0;
        $savedBytes = 0;

        $bar = $this->output->createProgressBar($photos->count());
        $bar->start();

        foreach ($photos as $photo) {
            $absolute = Storage::disk('public')->path($photo->path);
            $saved = ImageOptimizer::optimizeInPlace($absolute);
            if ($saved > 0) {
                $processed++;
                $savedBytes += $saved;
            }
            if (is_file($absolute)) {
                $photo->update(['file_size' => filesize($absolute)]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf(
            '%d photo(s) optimisée(s) sur %d — %s Ko économisés.',
            $processed,
            $photos->count(),
            number_format($savedBytes / 1024, 0, ',', ' ')
        ));

        return self::SUCCESS;
    }
}
