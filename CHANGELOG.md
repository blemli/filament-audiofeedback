# Changelog

All notable changes to `Filament AudioFeedback` will be documented in this file.

## Unreleased

- Users with the OS-level "reduce motion" preference now start muted (an explicit unmute or saved per-user setting still wins); ignore the hint with `->ignoreReducedMotion()` or `'ignore_reduced_motion' => true`
- Fixed CI: test/phpstan matrices now match the supported Laravel 12/13 range, and the changelog workflow targets the `5.x` branch

## 1.1.0 - 2026-07-28

- New `delete` event (default: `droplet`): delete and force-delete actions — including bulk — play it through their success notification instead of the generic success chime
- New `Notification::make()->soundEvent('...')` macro to play a configured event's sound (respecting config, fluent and per-user overrides) on any notification

## 1.0.0 - 2026-07-28

Initial release.

- Automatic sounds for notifications (by status), toggles, toggle buttons, sliders, form submits, login/logout, sortable drag & drop, and navigation hover — all mapped to the [Cuelume](https://cuelume-site.pages.dev) cue designed for that moment, and each remappable or mutable via config or fluent plugin calls
- `Notification::make()->sound('sparkle')` / `->silent()` per-notification overrides
- Master volume (`->volume(0–100)`), configurable per panel
- Opt-out mute button, positionable in the topbar or user menu
- Opt-in per-user "Sounds" section for Filament Breezy's my-profile page, with volume slider (default marker + reset), per-event sound picker with instant preview, and a mute switch synced with the topbar button
- Per-user persistence in the `audiofeedback_settings` table with a `localStorage` fallback for guests
- Works with Livewire events, redirects and SPA mode; no Filament views overridden
