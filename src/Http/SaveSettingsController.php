<?php

namespace Blemli\AudioFeedback\Http;

use Blemli\AudioFeedback\AudioFeedbackPlugin;
use Blemli\AudioFeedback\Models\AudioFeedbackSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaveSettingsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user, 401);

        $settings = $request->validate([
            'muted' => ['required', 'boolean'],
            'volume' => ['required', 'integer', 'between:0,100'],
            'overrides' => ['sometimes', 'array'],
            'overrides.*' => ['string', Rule::in([...AudioFeedbackPlugin::SOUNDS, 'off'])],
        ]);

        AudioFeedbackSetting::query()->updateOrCreate(
            ['user_id' => (string) $user->getAuthIdentifier()],
            ['settings' => $settings + ['overrides' => []]],
        );

        return new JsonResponse(['saved' => true]);
    }
}
