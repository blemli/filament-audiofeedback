{{-- blade-formatter-disable --}}
<style>
    .audiofeedback-sound-grid {
        display: grid;
        gap: 1.25rem 1.5rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @media (max-width: 40rem) {
        .audiofeedback-sound-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (min-width: 96rem) {
        .audiofeedback-sound-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
</style>
{{-- blade-formatter-enable --}}

<x-filament::section
    :aside="true"
    :heading="__('audiofeedback::audiofeedback.profile.heading')"
    :description="__('audiofeedback::audiofeedback.profile.description')"
>
    <div
        x-data="{
            muted: window.audiofeedback?.isMuted() ?? false,
            volume: window.audiofeedback?.getVolume() ?? 100,
            defaultVolume: window.audiofeedback?.defaultVolume() ?? 100,
            overrides: window.audiofeedback?.getOverrides() ?? {},
            toggleMuted() {
                window.audiofeedback?.setMuted(this.muted)

                if (! this.muted) {
                    window.audiofeedback?.cue('toggle')
                }
            },
            changeVolume() {
                window.audiofeedback?.setVolume(Number(this.volume))
            },
            previewVolume() {
                window.audiofeedback?.preview('chime')
            },
            resetVolume() {
                this.volume = this.defaultVolume
                this.changeVolume()
                this.previewVolume()
            },
            setSound(event, sound) {
                sound === '' ? delete this.overrides[event] : (this.overrides[event] = sound)
                window.audiofeedback?.setOverride(event, sound === '' ? null : sound)

                const audible = sound === 'off' ? null : (sound || window.audiofeedback?.defaults()[event])

                if (audible) {
                    window.audiofeedback?.preview(audible)
                }
            },
        }"
        x-on:audiofeedback:muted.window="muted = $event.detail.muted"
        style="display: grid; gap: 1.5rem;"
    >
        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
            <x-filament::input.checkbox x-model="muted" x-on:change="toggleMuted()" />

            <span class="fi-fo-field-label">
                {{ __('audiofeedback::audiofeedback.profile.muted') }}
            </span>
        </label>

        <div style="display: grid; gap: 0.25rem;" x-bind:style="{ opacity: muted ? 0.5 : 1 }">
            <div style="display: flex; align-items: baseline; justify-content: space-between; max-width: 24rem;">
                <label class="fi-fo-field-label" for="audiofeedback-volume">
                    {{ __('audiofeedback::audiofeedback.profile.volume') }}
                    (<span x-text="volume"></span>%)
                </label>

                <button
                    type="button"
                    class="fi-link fi-size-sm"
                    x-show="Number(volume) !== defaultVolume"
                    x-cloak
                    x-on:click="resetVolume()"
                    x-bind:disabled="muted"
                >
                    {{ __('audiofeedback::audiofeedback.profile.reset') }}
                </button>
            </div>

            <div style="position: relative; max-width: 24rem;">
                <input
                    id="audiofeedback-volume"
                    type="range"
                    min="0"
                    max="100"
                    step="5"
                    x-model="volume"
                    x-on:input="changeVolume()"
                    x-on:change="previewVolume()"
                    x-bind:disabled="muted"
                    style="display: block; width: 100%; accent-color: var(--primary-500, currentColor);"
                />

                {{-- A small line marking the panel's default volume. --}}
                <span
                    style="position: absolute; top: 100%; width: 2px; height: 0.375rem; margin-top: 1px; background: currentColor; opacity: 0.4; transform: translateX(-50%); pointer-events: none;"
                    x-bind:style="{ left: defaultVolume + '%' }"
                ></span>
            </div>
        </div>

        <div class="audiofeedback-sound-grid" x-bind:style="{ opacity: muted ? 0.5 : 1 }">
            @foreach ($rows as $row)
                <div style="display: grid; gap: 0.25rem; align-content: start;">
                    <label class="fi-fo-field-label" for="audiofeedback-{{ \Illuminate\Support\Str::slug($row['event']) }}">
                        {{ $row['label'] }}
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            id="audiofeedback-{{ \Illuminate\Support\Str::slug($row['event']) }}"
                            x-init="$el.value = overrides[{{ \Illuminate\Support\Js::from($row['event']) }}] ?? ''"
                            x-on:change="setSound({{ \Illuminate\Support\Js::from($row['event']) }}, $event.target.value)"
                            x-bind:disabled="muted"
                        >
                            <option value="">
                                {{ __('audiofeedback::audiofeedback.profile.default', ['sound' => $row['default']]) }}
                            </option>
                            <option value="off">
                                {{ __('audiofeedback::audiofeedback.profile.off') }}
                            </option>
                            @foreach (\Blemli\AudioFeedback\AudioFeedbackPlugin::SOUNDS as $sound)
                                <option value="{{ $sound }}">{{ $sound }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endforeach
        </div>
    </div>
</x-filament::section>
