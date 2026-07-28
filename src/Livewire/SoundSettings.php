<?php

namespace Blemli\AudioFeedback\Livewire;

use Blemli\AudioFeedback\AudioFeedbackPlugin;
use Blemli\AudioFeedback\Models\AudioFeedbackSetting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;
use Livewire\Attributes\On;

class SoundSettings extends MyProfileComponent
{
    protected string $view = 'audiofeedback::livewire.sound-settings';

    public static $sort = 40;

    public ?array $data = [];

    // Must match the alias registered in the service provider — subsequent
    // Livewire requests resolve the component class by this name.
    public function getName(): string
    {
        return 'audiofeedback.sound-settings';
    }

    public static function canView(): bool
    {
        return AudioFeedbackPlugin::get()->shouldShowBreezyProfileSection();
    }

    public function mount(): void
    {
        $settings = AudioFeedbackSetting::for(Filament::auth()->id()) ?? [];

        $overrides = [];

        foreach ($settings['overrides'] ?? [] as $event => $sound) {
            $overrides[static::formKey($event)] = $sound;
        }

        $this->form->fill([
            'muted' => $settings['muted'] ?? false,
            'volume' => $settings['volume'] ?? $this->getDefaultVolume(),
            'overrides' => $overrides,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getMutedComponent(),
                $this->getVolumeComponent(),
                Grid::make(['default' => 1, 'md' => 2, '2xl' => 3])
                    ->schema($this->getSoundComponents()),
            ])
            ->statePath('data');
    }

    protected function getMutedComponent(): Toggle
    {
        return Toggle::make('muted')
            ->label(__('audiofeedback::audiofeedback.profile.muted'))
            ->live()
            ->afterStateUpdated(function (bool $state): void {
                $this->persist();

                if (! $state) {
                    $this->dispatch('audiofeedback-preview', sound: 'toggle');
                }
            });
    }

    protected function getVolumeComponent(): Slider
    {
        $default = $this->getDefaultVolume();

        return Slider::make('volume')
            ->label(__('audiofeedback::audiofeedback.profile.volume'))
            ->required(false) // Slider::setUp() marks itself required
            ->range(0, 100)
            ->step(5)
            ->fillTrack()
            ->pips(PipsMode::Values)
            ->pipsValues([$default])
            ->live()
            ->disabled(fn (Get $get): bool => (bool) $get('muted'))
            ->afterStateUpdated(function (): void {
                $this->persist();
                $this->dispatch('audiofeedback-preview', sound: 'chime');
            })
            ->hintAction(
                Action::make('resetVolume')
                    ->label(__('audiofeedback::audiofeedback.profile.reset'))
                    ->link()
                    ->visible(fn (Get $get): bool => ! $get('muted') && (int) $get('volume') !== $default)
                    ->action(function (Set $set): void {
                        $set('volume', $this->getDefaultVolume());
                        $this->persist();
                        $this->dispatch('audiofeedback-preview', sound: 'chime');
                    }),
            );
    }

    /**
     * @return array<Select>
     */
    protected function getSoundComponents(): array
    {
        $components = [];

        foreach (AudioFeedbackPlugin::get()->getSounds() as $event => $default) {
            if ($default === false) {
                continue;
            }

            $components[] = Select::make('overrides.' . static::formKey($event))
                ->label(static::eventLabel($event))
                ->placeholder(__('audiofeedback::audiofeedback.profile.default', ['sound' => $default]))
                ->options([
                    'off' => __('audiofeedback::audiofeedback.profile.off'),
                    ...array_combine(AudioFeedbackPlugin::SOUNDS, AudioFeedbackPlugin::SOUNDS),
                ])
                ->live()
                ->disabled(fn (Get $get): bool => (bool) $get('muted'))
                ->afterStateUpdated(function (?string $state) use ($default): void {
                    $this->persist();

                    $audible = $state === 'off' ? null : ($state ?: $default);

                    if ($audible) {
                        $this->dispatch('audiofeedback-preview', sound: $audible);
                    }
                })
                // Icon-only with a tooltip: inline hint text would stretch
                // grid cells unevenly.
                ->hintColor('warning')
                ->hintIcon(
                    fn (): ?Heroicon => $this->getDuplicateHint($event) ? Heroicon::ExclamationTriangle : null,
                    tooltip: fn (): ?string => $this->getDuplicateHint($event),
                );
        }

        return $components;
    }

    /**
     * Warns when two events resolve to the same audible tune.
     */
    public function getDuplicateHint(string $event): ?string
    {
        $sounds = $this->getEffectiveSounds();
        $sound = $sounds[$event] ?? null;

        if (! $sound) {
            return null;
        }

        $duplicates = collect($sounds)
            ->forget($event)
            ->filter(fn (string $other): bool => $other === $sound)
            ->keys()
            ->map(fn (string $other): string => static::eventLabel($other));

        return $duplicates->isEmpty()
            ? null
            : __('audiofeedback::audiofeedback.profile.duplicate', ['events' => $duplicates->join(', ')]);
    }

    /**
     * @return array<string, string>
     */
    protected function getEffectiveSounds(): array
    {
        $sounds = [];

        foreach (AudioFeedbackPlugin::get()->getSounds() as $event => $default) {
            if ($default === false) {
                continue;
            }

            $value = $this->data['overrides'][static::formKey($event)] ?? null;
            $sound = $value === 'off' ? null : ($value ?: $default);

            if ($sound) {
                $sounds[$event] = $sound;
            }
        }

        return $sounds;
    }

    // Reads raw state instead of getState(): disabled fields (everything
    // while muted) are not dehydrated, and validation is not needed here.
    protected function persist(): void
    {
        $overrides = [];

        foreach (AudioFeedbackPlugin::get()->getSounds() as $event => $default) {
            if ($default === false) {
                continue;
            }

            $value = $this->data['overrides'][static::formKey($event)] ?? null;

            if (filled($value)) {
                $overrides[$event] = $value;
            }
        }

        $settings = [
            'muted' => (bool) ($this->data['muted'] ?? false),
            'volume' => (int) ($this->data['volume'] ?? $this->getDefaultVolume()),
            'overrides' => $overrides,
        ];

        AudioFeedbackSetting::query()->updateOrCreate(
            ['user_id' => (string) Filament::auth()->id()],
            ['settings' => $settings],
        );

        $this->dispatch('audiofeedback-settings-updated', settings: $settings);
    }

    // The topbar mute button reports its state through this event so the
    // form's toggle stays in sync without a reload.
    #[On('audiofeedback-muted-changed')]
    public function syncMuted(bool $muted): void
    {
        $this->data['muted'] = $muted;
    }

    protected function getDefaultVolume(): int
    {
        return (int) round(AudioFeedbackPlugin::get()->getVolume() * 100);
    }

    // Event names contain dots ('notification.success'), which would nest
    // in the form state path, so they are flattened for the field names.
    protected static function formKey(string $event): string
    {
        return str_replace('.', '__', $event);
    }

    protected static function eventLabel(string $event): string
    {
        $labels = __('audiofeedback::audiofeedback.events');

        return (is_array($labels) ? $labels : [])[$event] ?? Str::headline($event);
    }
}
