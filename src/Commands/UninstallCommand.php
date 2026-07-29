<?php

namespace Blemli\AudioFeedback\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class UninstallCommand extends Command
{
    protected $signature = 'audiofeedback:uninstall {--force : Skip all confirmation prompts}';

    protected $description = 'Remove every published trace of audiofeedback and list the manual steps that remain';

    public function handle(): int
    {
        $this->removePublishedFiles();
        $this->dropSettingsTable();
        $this->printReminders();

        return self::SUCCESS;
    }

    protected function removePublishedFiles(): void
    {
        $paths = array_values(array_filter([
            config_path('audiofeedback.php'),
            resource_path('views/vendor/audiofeedback'),
            lang_path('vendor/audiofeedback'),
            public_path('js/blemli/filament-audiofeedback'),
            ...File::glob(database_path('migrations/*_create_audiofeedback_settings_table.php')),
        ], fn (string $path): bool => file_exists($path)));

        if ($paths === []) {
            $this->components->info('No published audiofeedback files found.');

            return;
        }

        $this->components->bulletList($paths);

        if (! $this->option('force') && ! $this->confirm('Delete these published files?', true)) {
            $this->components->warn('Kept the published files.');

            return;
        }

        foreach ($paths as $path) {
            is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
            $this->components->twoColumnDetail($path, '<fg=green;options=bold>REMOVED</>');
        }
    }

    protected function dropSettingsTable(): void
    {
        try {
            $hasTable = Schema::hasTable('audiofeedback_settings');
        } catch (Throwable) {
            $this->components->warn('Could not reach the database — drop the audiofeedback_settings table yourself if it exists.');

            return;
        }

        if (! $hasTable) {
            return;
        }

        if (! $this->option('force') && ! $this->confirm('Drop the audiofeedback_settings table? All per-user sound settings will be lost.', true)) {
            $this->components->warn('Kept the audiofeedback_settings table.');

            return;
        }

        Schema::dropIfExists('audiofeedback_settings');
        $this->components->twoColumnDetail('audiofeedback_settings table', '<fg=green;options=bold>DROPPED</>');
    }

    protected function printReminders(): void
    {
        $this->components->warn('audiofeedback cannot edit your code — finish up by hand:');

        $this->components->bulletList(array_values(array_filter([
            'Remove AudioFeedbackPlugin::make() from your panel provider(s)',
            'Remove any AudioFeedbackPlugin::configureUsing() calls and ->sound() / ->silent() / ->soundEvent() notification macros',
            $this->envMentionsPackage() ? 'Remove AUDIOFEEDBACK_ENABLED from your .env files' : null,
            'composer remove blemli/filament-audiofeedback',
            'php artisan optimize:clear — cached config, routes and views may still reference the package',
        ])));

        if (($files = $this->filesStillReferencing()) !== []) {
            $this->components->warn('These files still mention audiofeedback:');
            $this->components->bulletList($files);
        }

        $this->components->info('Sad to hear us go? 👋 composer require blemli/filament-audiofeedback brings the sounds back anytime.');
    }

    protected function envMentionsPackage(): bool
    {
        foreach (['.env', '.env.example'] as $file) {
            $path = base_path($file);

            if (File::exists($path) && str_contains((string) File::get($path), 'AUDIOFEEDBACK_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function filesStillReferencing(): array
    {
        if (! is_dir(app_path())) {
            return [];
        }

        $files = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (stripos((string) File::get($file->getPathname()), 'audiofeedback') !== false) {
                $files[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        return $files;
    }
}
