<x-filament::section
    :aside="true"
    :heading="__('audiofeedback::audiofeedback.profile.heading')"
    :description="__('audiofeedback::audiofeedback.profile.description')"
>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>
</x-filament::section>
