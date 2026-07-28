<?php

namespace Blemli\AudioFeedback;

use Filament\View\PanelsRenderHook;

enum MuteTogglePosition: string
{
    case TopbarStart = 'topbar-start';

    case TopbarEnd = 'topbar-end';

    case UserMenuBefore = 'user-menu-before';

    case UserMenu = 'user-menu';

    public function getRenderHook(): string
    {
        return match ($this) {
            self::TopbarStart => PanelsRenderHook::TOPBAR_START,
            self::TopbarEnd => PanelsRenderHook::TOPBAR_END,
            self::UserMenuBefore => PanelsRenderHook::USER_MENU_BEFORE,
            self::UserMenu => PanelsRenderHook::USER_MENU_PROFILE_AFTER,
        };
    }

    public function isMenuItem(): bool
    {
        return $this === self::UserMenu;
    }
}
