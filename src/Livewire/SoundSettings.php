<?php

namespace Blemli\AudioFeedback\Livewire;

use Blemli\AudioFeedback\AudioFeedbackPlugin;
use Illuminate\Support\Str;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class SoundSettings extends MyProfileComponent
{
    protected string $view = 'audiofeedback::livewire.sound-settings';

    public static $sort = 40;

    /** @var array<int, array{event: string, label: string, default: string}> */
    public array $rows = [];

    public function mount(): void
    {
        $labels = __('audiofeedback::audiofeedback.events');
        $labels = is_array($labels) ? $labels : [];

        foreach (AudioFeedbackPlugin::get()->getSounds() as $event => $sound) {
            if ($sound === false) {
                continue;
            }

            $this->rows[] = [
                'event' => $event,
                'label' => $labels[$event] ?? Str::headline($event),
                'default' => $sound,
            ];
        }
    }
}
