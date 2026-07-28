<?php

// Every sound is one of the 14 Cuelume cues, synthesized in the browser:
// chime, sparkle, droplet, bloom, whisper, tick, press, release, toggle,
// success, error, page, loading, ready — https://cuelume-site.pages.dev
return [

    // Master switch. When false, no script data or UI is emitted at all.
    'enabled' => env('AUDIOFEEDBACK_ENABLED', true),

    // Master volume, 0–100. Users can pick their own volume per browser
    // (Breezy profile section), which then wins over this default.
    'volume' => 50,

    // The mute button is shown by default (opt-out: set to false). People
    // muting themselves is persisted per browser in localStorage.
    'mute_toggle' => true,

    // Where the mute button lives: 'topbar-start', 'topbar-end',
    // 'user-menu-before' (next to the avatar) or 'user-menu' (an item
    // inside the user dropdown).
    'mute_toggle_position' => 'user-menu-before',

    // Opt in to a "Sounds" section on Filament Breezy's my-profile page.
    // Per-user choices are stored in the audiofeedback_settings table
    // (migration ships with the package) with a localStorage fallback.
    'breezy_profile_section' => false,

    // Map each event to a Cuelume sound, or set it to false to silence it.
    'sounds' => [

        // Filament notifications, keyed by status.
        'notification.success' => 'success',
        'notification.danger' => 'error',
        'notification.warning' => 'chime',
        'notification.info' => 'whisper',

        // Toggles, switches and tabs (anything with role="switch"/"tab").
        'toggle' => 'toggle',

        // Toggle-button groups (radio/checkbox button fields).
        'toggle-buttons' => 'tick',

        // Releasing (or keyboard-stepping) a slider handle.
        'slider' => 'tick',

        // Hovering a sidebar or topbar navigation item.
        'nav.hover' => 'whisper',

        // A Livewire form being submitted (login, create, edit, modals, ...).
        'form.submit' => 'loading',

        // Auth events, played on the page following the redirect.
        'login' => 'ready',
        'logout' => 'droplet',

        // Grabbing and dropping a sortable item (reorderable tables,
        // repeaters, builders, ...).
        'drag' => 'press',
        'drop' => 'release',

        // A record being deleted (delete & force-delete actions, incl.
        // bulk); replaces the success sound of their notification.
        'delete' => 'droplet',

    ],

];
