<?php

namespace Blemli\AudioFeedback;

use Blemli\AudioFeedback\Http\SaveSettingsController;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AudioFeedbackServiceProvider extends PackageServiceProvider
{
    public static string $name = 'audiofeedback';

    protected const CUE_COOKIE = 'audiofeedback_cue';

    protected const NOTIFICATION_SOUNDS_COOKIE = 'audiofeedback_notification_sounds';

    /** @var array<string, string> */
    protected static array $notificationSounds = [];

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews('audiofeedback')
            ->hasTranslations()
            ->hasMigration('create_audiofeedback_settings_table')
            ->runsMigrations()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('blemli/filament-audiofeedback');
            });
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('audiofeedback', __DIR__ . '/../resources/dist/audiofeedback.js'),
        ], 'blemli/filament-audiofeedback');

        if (! ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            Route::post('audiofeedback/settings', SaveSettingsController::class)
                ->middleware('web')
                ->name('audiofeedback.settings');
        }

        // The cues below travel to the script as short-lived cookies, so they
        // must arrive unencrypted for JS to read them. They only ever contain
        // an event name or a map of notification ids to sound names.
        EncryptCookies::except([
            static::CUE_COOKIE,
            static::NOTIFICATION_SOUNDS_COOKIE,
        ]);

        static::registerNotificationMacros();
        static::registerDeleteCues();
        $this->registerAuthCues();
    }

    /**
     * Delete (and force-delete) actions swap their success notification's
     * sound for the 'delete' event, so deletions get their own cue while
     * still honoring config, fluent and per-user overrides.
     */
    protected static function registerDeleteCues(): void
    {
        $actionClasses = [
            DeleteAction::class,
            DeleteBulkAction::class,
            ForceDeleteAction::class,
            ForceDeleteBulkAction::class,
        ];

        foreach ($actionClasses as $actionClass) {
            $actionClass::configureUsing(fn (Action $action): Action => $action->successNotification(
                function (Notification $notification): Notification {
                    static::queueNotificationSound($notification->getId(), 'event:delete');

                    return $notification;
                },
            ));
        }
    }

    /**
     * Notification::make()->sound('sparkle') overrides the status-based sound,
     * Notification::make()->silent() suppresses it. The override rides along
     * as a cookie keyed by notification id, which works for both Livewire
     * requests and redirects without touching any Filament view.
     */
    protected static function registerNotificationMacros(): void
    {
        Notification::macro('sound', Closure::bind(function (string | false $sound): Notification {
            AudioFeedbackServiceProvider::queueNotificationSound($this->getId(), $sound === false ? 'off' : $sound);

            return $this;
        }, null, Notification::class));

        Notification::macro('silent', Closure::bind(function (): Notification {
            AudioFeedbackServiceProvider::queueNotificationSound($this->getId(), 'off');

            return $this;
        }, null, Notification::class));

        // Plays a configured event's sound (respecting overrides) instead of
        // a hardcoded one, e.g. ->soundEvent('delete').
        Notification::macro('soundEvent', Closure::bind(function (string $event): Notification {
            AudioFeedbackServiceProvider::queueNotificationSound($this->getId(), 'event:' . $event);

            return $this;
        }, null, Notification::class));
    }

    /**
     * Login/logout happen right before a redirect (and logout invalidates the
     * session), so these cues also travel as a cookie that the script
     * consumes on the next page load.
     */
    protected function registerAuthCues(): void
    {
        Event::listen(Login::class, fn () => static::queueCue('login'));
        Event::listen(Logout::class, fn () => static::queueCue('logout'));
    }

    public static function queueCue(string $event): void
    {
        Cookie::queue(Cookie::make(
            name: static::CUE_COOKIE,
            value: $event,
            minutes: 1,
            httpOnly: false,
        ));
    }

    public static function queueNotificationSound(string $notificationId, string $sound): void
    {
        static::$notificationSounds[$notificationId] = $sound;

        Cookie::queue(Cookie::make(
            name: static::NOTIFICATION_SOUNDS_COOKIE,
            value: (string) json_encode(static::$notificationSounds),
            minutes: 1,
            httpOnly: false,
        ));
    }
}
