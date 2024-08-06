<?php

namespace DiscoveryDesign\FilamentGaze\Forms\Components;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Illuminate\Support\Facades\Cache;

/**
 * Class GazeBanner
 *
 * Represents a custom form component called GazeBanner.
 * This component displays a banner with the names of the current viewers.
 * It provides methods to set a custom identifier and the poll timer.
 * The component refreshes the list of viewers and renders the banner.
 */
class GazeBanner extends Component
{
    /**
     * The array of current viewers.
     */
    public array $currentViewers = [];

    /**
     * The custom identifier for the GazeBanner component.
     */
    public ?string $identifier = null;

    /**
     * The poll timer for refreshing the list of viewers.
     */
    public string | int $pollTimer = 10;

    /**
     * Set a custom identifier for the GazeBanner component.
     *
     * @param  string  $identifier
     * @return $this
     */
    public function identifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Set the poll timer for refreshing the list of viewers.
     *
     * @param  int  $poll
     * @return $this
     */
    public function pollTimer($poll)
    {
        $this->pollTimer = $poll;

        return $this;
    }

    /**
     * Refresh the list of viewers.
     *
     * If no custom identifier is set, it will use the model's identifier.
     * It retrieves the current viewers, removes expired viewers, and adds/re-adds the current user.
     *
     * @return void
     */
    public function refreshViewers()
    {
        if (! $this->identifier) {
            $record = $this->getRecord();
            if (! $record) {
                $this->identifier = (string) $this->getModel();
            } else {
                $this->identifier = get_class($record) . '-' . $record->id;
            }
        }

        $identifier = $this->identifier;
        $authGuard = Filament::getCurrentPanel()->getAuthGuard();

        // Todo: refactor this
        $guardProvider = config('auth.guards.' . $authGuard . '.provider');
        $guardModel = config('auth.providers.' . $guardProvider . '.model');

        // Check over all current viewers
        $curViewers = Cache::get('filament-gaze-' . $identifier, []);
        foreach ($curViewers as $key => $viewer) {
            $model = $guardModel::find($viewer['id']);
            $expires = Carbon::parse($viewer['expires']);

            // Remove expired viewers
            if ($expires->isPast()) {
                unset($curViewers[$key]);
            }

            // If current user, remove them so they can be re-added below.
            if (! $model || ($model?->id == auth()?->id())) {
                unset($curViewers[$key]);
            }
        }

        $user = auth()->guard($authGuard)->user();
        // Add/re-add the current user to the list
        $curViewers[] = [
            'id' => auth()->guard($authGuard)->id(),
            'name' => $user?->name ?? $user?->getFilamentName() ?? 'Unknown', // Possibly need to account for more?
            'expires' => now()->addSeconds($this->pollTimer * 2),
        ];

        $this->currentViewers = $curViewers;

        Cache::put('filament-gaze-' . $identifier, $curViewers, now()->addSeconds($this->pollTimer * 2));
    }

    /**
     * Render the GazeBanner component.
     *
     * It refreshes the list of viewers, formats the viewer names, and returns the rendered view.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        $this->refreshViewers();

        $formattedViewers = '';
        $currentViewers = collect($this->currentViewers);
        $filteredViewers = $currentViewers->filter(function ($viewer) {
            return $viewer['id'] != auth()->id();
        });

        $finalText = '';

        if ($filteredViewers->count() > 2) {
            $formattedViewers = $filteredViewers->first()['name'];
            $formattedViewers .= ', ';
            $formattedViewers .= $filteredViewers->skip(1)->first()['name'];

            $extras = $filteredViewers->count() - 2;

            $finalText = __($extras > 1 ? 'filament-gaze::gaze.banner_text_others' : 'filament-gaze::gaze.banner_text_other', [
                'viewers' => $formattedViewers,
                'count' => $extras,
            ]);

        } else {
            $formattedViewers = $filteredViewers->implode('name', ' & ');

            $finalText = __('filament-gaze::gaze.banner_text', [
                'viewers' => $formattedViewers,
            ]);
        }

        return view('filament-gaze::forms.components.gaze-banner', [
            'show' => $filteredViewers->count() >= 1,
            'currentViewers' => $this->currentViewers,
            'text' => $finalText,
            'pollTimer' => $this->pollTimer,
        ]);
    }

    /**
     * Create a new instance of the GazeBanner component.
     *
     * @param  array|\Closure  $schema
     */
    public static function make(array | Closure $schema = []): static
    {
        $static = app(static::class, ['schema' => $schema]);
        $static->configure();

        return $static;
    }
}
