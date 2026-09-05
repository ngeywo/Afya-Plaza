<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'resource_label',
        'before',
        'after',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before'       => 'array',
            'after'        => 'array',
            'resource_id'  => 'integer',
        ];
    }

    // ─── Relations ───────────────────────────────────────────────────────────────

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForResource($q, string $type, $id = null)
    {
        $q->where('resource_type', $type);
        if ($id !== null) {
            $q->where('resource_id', $id);
        }
        return $q;
    }

    public function scopeAction($q, string $action)
    {
        return $q->where('action', $action);
    }

    public function scopeByActor($q, int $actorId)
    {
        return $q->where('actor_id', $actorId);
    }

    public function scopeRecent($q, int $days = 30)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public static function record(
        int|null $actorId,
        string $action,
        string $resourceType,
        int|null $resourceId = null,
        string|null $resourceLabel = null,
        array|null $before = null,
        array|null $after = null,
        string|null $reason = null,
        string|null $ipAddress = null,
        string|null $userAgent = null,
    ): self {
        return static::create([
            'actor_id'       => $actorId,
            'action'         => $action,
            'resource_type'  => $resourceType,
            'resource_id'    => $resourceId,
            'resource_label' => $resourceLabel,
            'before'         => $before,
            'after'          => $after,
            'reason'         => $reason,
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
        ]);
    }

    public function getChangeSummaryAttribute(): string
    {
        $before = $this->before ?? [];
        $after  = $this->after  ?? [];

        $changes = [];
        foreach ($after as $key => $val) {
            if (!array_key_exists($key, $before) || $before[$key] !== $val) {
                $changes[] = "{$key}: " . ($before[$key] ?? '—') . " → {$val}";
            }
        }

        return implode('; ', $changes) ?: 'No field changes';
    }
}
