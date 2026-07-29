<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('removes published files and drops the settings table', function () {
    $this->artisan('migrate');

    File::put(config_path('audiofeedback.php'), '<?php return [];');
    File::ensureDirectoryExists(resource_path('views/vendor/audiofeedback'));
    File::put(resource_path('views/vendor/audiofeedback/mute-toggle.blade.php'), '');
    File::ensureDirectoryExists(lang_path('vendor/audiofeedback/en'));
    File::put(lang_path('vendor/audiofeedback/en/audiofeedback.php'), '<?php return [];');
    File::ensureDirectoryExists(public_path('js/blemli/filament-audiofeedback'));
    File::put(public_path('js/blemli/filament-audiofeedback/audiofeedback.js'), '');
    File::put(database_path('migrations/2026_01_01_000000_create_audiofeedback_settings_table.php'), '<?php');

    expect(Schema::hasTable('audiofeedback_settings'))->toBeTrue();

    $this->artisan('audiofeedback:uninstall', ['--force' => true])->assertSuccessful();

    expect(File::exists(config_path('audiofeedback.php')))->toBeFalse()
        ->and(File::isDirectory(resource_path('views/vendor/audiofeedback')))->toBeFalse()
        ->and(File::isDirectory(lang_path('vendor/audiofeedback')))->toBeFalse()
        ->and(File::isDirectory(public_path('js/blemli/filament-audiofeedback')))->toBeFalse()
        ->and(File::glob(database_path('migrations/*_create_audiofeedback_settings_table.php')))->toBeEmpty()
        ->and(Schema::hasTable('audiofeedback_settings'))->toBeFalse();
});

it('keeps the settings table when the drop is declined', function () {
    $this->artisan('migrate');

    $this->artisan('audiofeedback:uninstall')
        ->expectsConfirmation('Drop the audiofeedback_settings table? All per-user sound settings will be lost.', 'no')
        ->assertSuccessful();

    expect(Schema::hasTable('audiofeedback_settings'))->toBeTrue();
});

it('reminds about the manual cleanup steps', function () {
    Artisan::call('audiofeedback:uninstall', ['--force' => true]);

    expect(Artisan::output())
        ->toContain('Remove AudioFeedbackPlugin::make() from your panel provider(s)')
        ->toContain('composer remove blemli/filament-audiofeedback')
        ->toContain('optimize:clear');
});
