<?php

namespace Blemli\AudioFeedback;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AudioFeedbackPlugin implements Plugin
{
    // The 14 Cuelume cues — https://cuelume-site.pages.dev
    public const SOUNDS = [
        'chime', 'sparkle', 'droplet', 'bloom', 'whisper', 'tick', 'press',
        'release', 'toggle', 'success', 'error', 'page', 'loading', 'ready',
    ];

    /** @var array<Closure> */
    protected static array $configurators = [];

    protected ?bool $enabled = null;

    protected ?bool $muteToggle = null;

    protected MuteTogglePosition | string | null $muteTogglePosition = null;

    protected int | float | null $volume = null;

    protected ?bool $breezyProfileSection = null;

    /** @var array<string, string | false> */
    protected array $sounds = [];

    public function getId(): string
    {
        return 'audiofeedback';
    }

    public static function make(): static
    {
        $plugin = app(static::class);

        foreach (static::$configurators as $configurator) {
            $configurator($plugin);
        }

        return $plugin;
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Tweak the plugin globally, e.g. from your AppServiceProvider:
     *
     *     AudioFeedbackPlugin::configureUsing(
     *         fn (AudioFeedbackPlugin $plugin) => $plugin->sound('toggle', 'tick'),
     *     );
     */
    public static function configureUsing(Closure $configurator): void
    {
        static::$configurators[] = $configurator;
    }

    public static function flushConfigurators(): void
    {
        static::$configurators = [];
    }

    public function enabled(bool $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * The mute button is shown by default — pass false to opt out, or a
     * position to move it: 'topbar-start', 'topbar-end', 'user-menu-before'
     * (default, next to the avatar) or 'user-menu' (inside the dropdown).
     */
    public function muteToggle(bool $visible = true, MuteTogglePosition | string | null $position = null): static
    {
        $this->muteToggle = $visible;
        $this->muteTogglePosition = $position ?? $this->muteTogglePosition;

        return $this;
    }

    public function muteTogglePosition(MuteTogglePosition | string $position): static
    {
        $this->muteTogglePosition = $position;

        return $this;
    }

    /**
     * Master volume, 0–100 (integers) or 0.0–1.0 (fractions).
     */
    public function volume(int | float $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    /**
     * Opt in to a "Sounds" section on Filament Breezy's my-profile page,
     * where each user can pick, mute or re-map every sound.
     */
    public function breezyProfileSection(bool $condition = true): static
    {
        $this->breezyProfileSection = $condition;

        return $this;
    }

    /**
     * Override the sound for one event, or pass false to silence it.
     */
    public function sound(string $event, string | false $sound): static
    {
        $this->sounds[$event] = $sound;

        return $this;
    }

    /**
     * @param  array<string, string | false>  $sounds
     */
    public function sounds(array $sounds): static
    {
        $this->sounds = [...$this->sounds, ...$sounds];

        return $this;
    }

    public function disable(string ...$events): static
    {
        foreach ($events as $event) {
            $this->sounds[$event] = false;
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled ?? (bool) config('audiofeedback.enabled', true);
    }

    public function hasMuteToggle(): bool
    {
        return $this->muteToggle ?? (bool) config('audiofeedback.mute_toggle', true);
    }

    public function getMuteTogglePosition(): MuteTogglePosition
    {
        $position = $this->muteTogglePosition
            ?? config('audiofeedback.mute_toggle_position', MuteTogglePosition::UserMenuBefore);

        return $position instanceof MuteTogglePosition ? $position : MuteTogglePosition::from($position);
    }

    /**
     * Normalized to a 0.0–1.0 fraction.
     */
    public function getVolume(): float
    {
        $volume = $this->volume ?? config('audiofeedback.volume', 100);

        return min(1.0, max(0.0, $volume <= 1 ? (float) $volume : $volume / 100));
    }

    public function hasBreezyProfileSection(): bool
    {
        return $this->breezyProfileSection ?? (bool) config('audiofeedback.breezy_profile_section', false);
    }

    /**
     * @return array<string, string | false>
     */
    public function getSounds(): array
    {
        return [...config('audiofeedback.sounds', []), ...$this->sounds];
    }

    public function register(Panel $panel): void
    {
        // Panels flush their render hooks before plugins boot, so this must
        // happen here; the checks run lazily so fluent/config changes count.
        foreach (MuteTogglePosition::cases() as $position) {
            $panel->renderHook(
                $position->getRenderHook(),
                fn (): string => ($this->isEnabled() && $this->hasMuteToggle() && $this->getMuteTogglePosition() === $position)
                    ? Blade::render(
                        '<x-audiofeedback::mute-toggle :menu-item="$menuItem" />',
                        ['menuItem' => $position->isMenuItem()],
                    )
                    : '',
            );
        }

        // Plugins boot before the session middleware runs, so the logged-in
        // user's saved settings have to be injected at render time instead.
        $panel->renderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): string => $this->isEnabled() ? $this->renderUserSettingsScript() : '',
        );
    }

    protected function renderUserSettingsScript(): string
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return '';
        }

        $payload = json_encode([
            // rescue() covers apps that haven't run the migration (yet).
            'user' => rescue(
                fn (): ?array => Models\AudioFeedbackSetting::for($user->getAuthIdentifier()),
                report: false,
            ),
            'endpoint' => route('audiofeedback.settings'),
        ]);

        return "<script>Object.assign(((window.filamentData ??= {}).audiofeedback ??= {}), {$payload})</script>";
    }

    public function boot(Panel $panel): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        FilamentAsset::registerScriptData([
            'audiofeedback' => [
                'sounds' => array_filter($this->getSounds()),
                'volume' => $this->getVolume(),
            ],
        ], 'blemli/filament-audiofeedback');

        $this->registerBreezyProfileSection($panel);
    }

    // Adds a "Sounds" section to Filament Breezy's my-profile page — opt in
    // via ->breezyProfileSection() or the breezy_profile_section config key.
    protected function registerBreezyProfileSection(Panel $panel): void
    {
        if (! $this->hasBreezyProfileSection()) {
            return;
        }

        if (! class_exists(BreezyCore::class)) {
            return;
        }

        if (! $panel->hasPlugin('filament-breezy')) {
            return;
        }

        $breezy = $panel->getPlugin('filament-breezy');

        if ($breezy instanceof BreezyCore) {
            $breezy->myProfileComponents(['audiofeedback' => Livewire\SoundSettings::class]);
        }
    }
}
