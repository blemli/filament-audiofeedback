# Changelog

All notable changes to `Filament AudioFeedback` will be documented in this file.

## v1.2.0 - 2026-07-28

### What's new

- **Native Filament profile section** 🎛️ — the Breezy "Sounds" section is now a fully native Filament form: `Toggle`, `Slider` (with a pip marking the panel default and a Reset hint action) and per-event `Select`s in a responsive grid, saving server-side on every change with instant sound previews.
- **Duplicate-tune warnings** — selects show a warning icon (message in a tooltip) when two events resolve to the same tune.
- **Per-user guard** — `->breezyProfileSection(fn (?User $user) => ...)` gates the section per user via Breezy's `canView()`.
- **Reduced motion** ♿ — users with the OS-level "reduce motion" preference start muted; an explicit unmute or saved setting wins, and `->ignoreReducedMotion()` disables the hint.
- **Translations** 🌍 — German, French, Italian and Spanish ship alongside English.
- CI now runs the supported Laravel 12/13 matrix and is fully green.

**Full changelog**: https://github.com/blemli/filament-audiofeedback/compare/v1.1.0...v1.2.0

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
