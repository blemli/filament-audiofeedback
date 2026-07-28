import { bind, play, setEnabled, sounds as soundNames } from 'cuelume'
// The recipe palette isn't in cuelume's exports map, so reach into the
// bundled dist directly — volume works by scaling each recipe's masterGain.
import { RECIPES } from '../../node_modules/cuelume/dist/sounds/recipes.js'

const MUTED_STORAGE_KEY = 'audiofeedback.muted'
const VOLUME_STORAGE_KEY = 'audiofeedback.volume'
const OVERRIDES_STORAGE_KEY = 'audiofeedback.sounds'
const CUE_COOKIE = 'audiofeedback_cue'
const NOTIFICATION_SOUNDS_COOKIE = 'audiofeedback_notification_sounds'

const data = () => window.filamentData?.audiofeedback ?? {}

const sounds = () => data().sounds ?? {}

// Per-user settings from the database (hydrated server-side). Logged-in
// users read/write these; guests fall back to localStorage only.
const userSettings = () => (typeof data().user === 'object' ? data().user : null)

const baseGains = Object.fromEntries(
    Object.entries(RECIPES).map(([name, recipe]) => [name, recipe.masterGain]),
)

// The panel's configured volume, in percent — also the "reset to" default.
const defaultVolume = () => Math.round((data().volume ?? 1) * 100)

// Volume in percent (0–100): database, then localStorage, then the config.
function getVolume() {
    const user = userSettings()

    if (typeof user?.volume === 'number') {
        return Math.min(100, Math.max(0, user.volume))
    }

    try {
        const stored = localStorage.getItem(VOLUME_STORAGE_KEY)

        if (stored !== null && stored !== '' && ! Number.isNaN(+stored)) {
            return Math.min(100, Math.max(0, +stored))
        }
    } catch {
        //
    }

    return defaultVolume()
}

function applyVolume() {
    const fraction = getVolume() / 100

    for (const [name, recipe] of Object.entries(RECIPES)) {
        recipe.masterGain = baseGains[name] * fraction
    }
}

function setVolume(percent) {
    const volume = Math.min(100, Math.max(0, Math.round(+percent || 0)))

    try {
        localStorage.setItem(VOLUME_STORAGE_KEY, String(volume))
    } catch {
        //
    }

    syncUser({ volume })
    applyVolume()
}

// Per-event user overrides ('off' silences an event, absence means default).
function getOverrides() {
    const user = userSettings()

    if (typeof user?.overrides === 'object' && user.overrides !== null) {
        return { ...user.overrides }
    }

    try {
        return JSON.parse(localStorage.getItem(OVERRIDES_STORAGE_KEY)) ?? {}
    } catch {
        return {}
    }
}

function setOverride(event, sound) {
    const overrides = getOverrides()

    sound === null || sound === '' ? delete overrides[event] : (overrides[event] = sound)

    try {
        Object.keys(overrides).length
            ? localStorage.setItem(OVERRIDES_STORAGE_KEY, JSON.stringify(overrides))
            : localStorage.removeItem(OVERRIDES_STORAGE_KEY)
    } catch {
        //
    }

    syncUser({ overrides })
}

function isMuted() {
    const user = userSettings()

    if (typeof user?.muted === 'boolean') {
        return user.muted
    }

    try {
        return localStorage.getItem(MUTED_STORAGE_KEY) === '1'
    } catch {
        return false
    }
}

function setMuted(muted) {
    try {
        muted
            ? localStorage.setItem(MUTED_STORAGE_KEY, '1')
            : localStorage.removeItem(MUTED_STORAGE_KEY)
    } catch {
        //
    }

    setEnabled(! muted)
    syncUser({ muted })

    // Keeps every mute control (topbar button, profile checkbox) in sync.
    window.dispatchEvent(new CustomEvent('audiofeedback:muted', { detail: { muted } }))
}

// For logged-in users, mirror each change onto filamentData (so later reads
// see it) and persist it to the database, debounced for slider drags.
let persistTimer

function syncUser(patch) {
    const shared = window.filamentData?.audiofeedback

    if (! shared?.endpoint) {
        return
    }

    shared.user = {
        muted: isMuted(),
        volume: getVolume(),
        overrides: getOverrides(),
        ...patch,
    }

    clearTimeout(persistTimer)
    persistTimer = setTimeout(persist, 400)
}

function persist() {
    const shared = window.filamentData?.audiofeedback

    if (! shared?.endpoint || ! shared.user) {
        return
    }

    fetch(shared.endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(shared.user),
    }).catch(() => {})
}

// Cuelume drops sounds until the page has seen a user gesture (browser
// autoplay policy), which is exactly the situation right after the
// login/logout redirect. Queue those cues and flush on the first gesture.
const pending = []

const hasUserActivation = () =>
    navigator.userActivation?.hasBeenActive !== false

function cue(event) {
    const sound = getOverrides()[event] ?? sounds()[event]

    if (sound && sound !== 'off') {
        playSound(sound)
    }
}

// Lets the profile UI demo a sound even while muted.
function preview(sound) {
    setEnabled(true)
    play(sound)
    setEnabled(! isMuted())
}

function playSound(sound) {
    if (isMuted()) {
        return
    }

    if (hasUserActivation()) {
        play(sound)
    } else {
        pending.push(sound)
    }
}

function flushPending() {
    while (pending.length) {
        play(pending.shift())
    }
}

function readCookie(name) {
    const match = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(`${name}=`))

    if (! match) {
        return null
    }

    try {
        return decodeURIComponent(match.slice(name.length + 1))
    } catch {
        return null
    }
}

function deleteCookie(name) {
    document.cookie = `${name}=; Max-Age=0; path=/`
}

// Login/logout cues arrive from the previous request as a cookie.
function consumeCueCookie() {
    const event = readCookie(CUE_COOKIE)

    if (! event) {
        return
    }

    deleteCookie(CUE_COOKIE)
    cue(event)
}

// Notification::make()->sound('sparkle') overrides arrive the same way,
// as a map of notification ids to sound names.
function consumeNotificationSound(notificationId) {
    const raw = readCookie(NOTIFICATION_SOUNDS_COOKIE)

    if (! raw) {
        return undefined
    }

    let overrides

    try {
        overrides = JSON.parse(raw)
    } catch {
        deleteCookie(NOTIFICATION_SOUNDS_COOKIE)

        return undefined
    }

    if (! (notificationId in overrides)) {
        return undefined
    }

    const sound = overrides[notificationId]
    delete overrides[notificationId]

    Object.keys(overrides).length
        ? (document.cookie = `${NOTIFICATION_SOUNDS_COOKIE}=${encodeURIComponent(JSON.stringify(overrides))}; Max-Age=60; path=/`)
        : deleteCookie(NOTIFICATION_SOUNDS_COOKIE)

    return sound
}

// Every Filament notification — Livewire event, redirect or broadcast —
// ends up as a rendered `.fi-no-notification` element, so one observer
// covers them all. Ids are remembered so Livewire DOM morphs can't replay.
const playedNotifications = new Set()

function handleNotification(element) {
    if (element.classList.contains('fi-inline')) {
        return
    }

    const id = (element.getAttribute('wire:key') ?? '').split('.')[0]

    if (id) {
        if (playedNotifications.has(id)) {
            return
        }

        playedNotifications.add(id)

        if (playedNotifications.size > 100) {
            playedNotifications.delete(playedNotifications.values().next().value)
        }
    }

    const override = id ? consumeNotificationSound(id) : undefined

    if (override === 'off') {
        return
    }

    if (override) {
        // 'event:delete' → play whatever the delete event resolves to.
        override.startsWith('event:') ? cue(override.slice('event:'.length)) : playSound(override)

        return
    }

    const status = [...element.classList]
        .find((className) => className.startsWith('fi-status-'))
        ?.slice('fi-status-'.length)

    cue(`notification.${status ?? 'info'}`)
}

function observeNotifications() {
    const notify = (root) => {
        if (! (root instanceof Element)) {
            return
        }

        if (root.matches('.fi-no-notification')) {
            handleNotification(root)
        }

        root.querySelectorAll?.('.fi-no-notification').forEach(handleNotification)
    }

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(notify)
        }
    }).observe(document.documentElement, { childList: true, subtree: true })

    document.querySelectorAll('.fi-no-notification').forEach(handleNotification)
}

function observeInteractions() {
    document.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('[role="switch"]')) {
            cue('toggle')
        }
    })

    document.addEventListener('submit', () => cue('form.submit'), { capture: true })

    document.addEventListener('change', (event) => {
        if (event.target instanceof Element && event.target.matches('.fi-fo-toggle-buttons-input')) {
            cue('toggle-buttons')
        }
    })

    let hovered = null

    document.addEventListener('mouseover', (event) => {
        const item = event.target instanceof Element
            ? event.target.closest('.fi-sidebar-item-btn, .fi-topbar-item-btn')
            : null

        if (item !== hovered) {
            hovered = item

            if (item) {
                cue('nav.hover')
            }
        }
    })

    let dragging = false
    let sliding = false

    document.addEventListener('pointerdown', (event) => {
        if (! (event.target instanceof Element)) {
            return
        }

        if (event.target.closest('[x-sortable-handle]')) {
            dragging = true
            cue('drag')
        }

        if (event.target.closest('.fi-fo-slider')) {
            sliding = true
        }
    })

    document.addEventListener('pointerup', () => {
        if (dragging) {
            dragging = false
            cue('drop')
        }

        if (sliding) {
            sliding = false
            cue('slider')
        }
    })

    // Sliders are keyboard-operable too; throttle so held keys don't machine-gun.
    let lastSliderKey = 0

    document.addEventListener('keydown', (event) => {
        if (! event.key.startsWith('Arrow')) {
            return
        }

        if (! (event.target instanceof Element && event.target.closest('.fi-fo-slider'))) {
            return
        }

        const now = performance.now()

        if (now - lastSliderKey >= 150) {
            lastSliderKey = now
            cue('slider')
        }
    })
}

function init() {
    setEnabled(! isMuted())
    applyVolume()

    observeNotifications()
    observeInteractions()
    consumeCueCookie()

    document.addEventListener('livewire:navigated', consumeCueCookie)

    const flush = () => {
        flushPending()
        document.removeEventListener('pointerdown', flush)
        document.removeEventListener('keydown', flush)
    }

    document.addEventListener('pointerdown', flush)
    document.addEventListener('keydown', flush)

    // Also honor plain `data-cuelume-*` attributes in userland views.
    bind()
}

window.audiofeedback = {
    cue,
    play: playSound,
    preview,
    isMuted,
    setMuted,
    getVolume,
    setVolume,
    defaultVolume,
    getOverrides,
    setOverride,
    defaults: sounds,
    soundNames,
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init()
