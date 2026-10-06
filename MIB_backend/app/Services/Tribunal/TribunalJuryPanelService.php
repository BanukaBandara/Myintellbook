<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalJuryPanelStatus;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TribunalJuryPanelService
{
    /**
     * List and filter jury panels with pagination.
     */
    public function getPanels(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = TribunalJuryPanel::query()
            ->with(['loginUser', 'creator', 'events.actor'])
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('panel_code', 'like', "%{$term}%")
                  ->orWhere('panel_name', 'like', "%{$term}%")
                  ->orWhereHas('loginUser', function ($sub) use ($term) {
                      $sub->where('email', 'like', "%{$term}%");
                  });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new Jury Panel with a dedicated User account and audit event in a single transaction.
     */
    public function createPanel(array $data, User $creator): TribunalJuryPanel
    {
        return DB::transaction(function () use ($data, $creator) {
            $panelCode = TribunalJuryPanel::generateUniqueCode();

            // 1. Create dedicated login User account
            $loginUser = User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]);

            // 2. Create Tribunal Jury Panel
            $juryPanel = TribunalJuryPanel::create([
                'panel_code' => $panelCode,
                'panel_name' => $data['panel_name'],
                'login_user_id' => $loginUser->id,
                'status' => TribunalJuryPanelStatus::Active,
                'created_by' => $creator->id,
            ]);

            // 3. Record audit event
            TribunalJuryPanelEvent::create([
                'tribunal_jury_panel_id' => $juryPanel->id,
                'actor_id' => $creator->id,
                'event_type' => 'jury_panel_created',
                'metadata' => [
                    'panel_code' => $panelCode,
                    'panel_name' => $data['panel_name'],
                    'login_user_id' => $loginUser->id,
                    'admin_user_id' => $creator->id,
                ],
                'created_at' => now(),
            ]);

            return $juryPanel->load(['loginUser', 'creator', 'events.actor']);
        });
    }

    /**
     * Update an existing Jury Panel.
     */
    public function updatePanel(TribunalJuryPanel $panel, array $data, User $actor): TribunalJuryPanel
    {
        return DB::transaction(function () use ($panel, $data, $actor) {
            $oldStatus = $panel->status;

            if (isset($data['panel_name'])) {
                $panel->panel_name = $data['panel_name'];
            }

            if (isset($data['status'])) {
                $panel->status = $data['status'];
            }

            $panel->save();

            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $eventType = match ($panel->status) {
                    TribunalJuryPanelStatus::Active => 'jury_panel_activated',
                    TribunalJuryPanelStatus::Inactive => 'jury_panel_deactivated',
                    TribunalJuryPanelStatus::Suspended => 'jury_panel_suspended',
                    default => 'jury_panel_status_updated',
                };

                TribunalJuryPanelEvent::create([
                    'tribunal_jury_panel_id' => $panel->id,
                    'actor_id' => $actor->id,
                    'event_type' => $eventType,
                    'metadata' => [
                        'panel_code' => $panel->panel_code,
                        'admin_user_id' => $actor->id,
                        'previous_status' => $oldStatus instanceof \BackedEnum ? $oldStatus->value : (string) $oldStatus,
                        'new_status' => $panel->status instanceof \BackedEnum ? $panel->status->value : (string) $panel->status,
                    ],
                    'created_at' => now(),
                ]);
            }

            return $panel->fresh(['loginUser', 'creator', 'events.actor']);
        });
    }

    /**
     * Activate a Jury Panel.
     */
    public function activatePanel(TribunalJuryPanel $panel, User $actor): TribunalJuryPanel
    {
        return DB::transaction(function () use ($panel, $actor) {
            $panel->status = TribunalJuryPanelStatus::Active;
            $panel->save();

            TribunalJuryPanelEvent::create([
                'tribunal_jury_panel_id' => $panel->id,
                'actor_id' => $actor->id,
                'event_type' => 'jury_panel_activated',
                'metadata' => [
                    'panel_code' => $panel->panel_code,
                    'admin_user_id' => $actor->id,
                ],
                'created_at' => now(),
            ]);

            return $panel->fresh(['loginUser', 'creator', 'events.actor']);
        });
    }

    /**
     * Deactivate a Jury Panel.
     */
    public function deactivatePanel(TribunalJuryPanel $panel, User $actor): TribunalJuryPanel
    {
        return DB::transaction(function () use ($panel, $actor) {
            $panel->status = TribunalJuryPanelStatus::Inactive;
            $panel->save();

            TribunalJuryPanelEvent::create([
                'tribunal_jury_panel_id' => $panel->id,
                'actor_id' => $actor->id,
                'event_type' => 'jury_panel_deactivated',
                'metadata' => [
                    'panel_code' => $panel->panel_code,
                    'admin_user_id' => $actor->id,
                ],
                'created_at' => now(),
            ]);

            return $panel->fresh(['loginUser', 'creator', 'events.actor']);
        });
    }
}
