<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipient_id
 * @property int $organization_id
 * @property NotificationType $type
 * @property string $title
 * @property string $body
 * @property string $link
 * @property string $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $recipient
 * @property-read Organization $organization
 */
class Notification extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'recipient_id',
        'organization_id',
        'type',
        'title',
        'body',
        'link',
        'dedupe_key',
        'read_at',
    ];

    /**
     * Set once and never overwritten. Marking everything read a second time should not
     * quietly rewrite when somebody first saw something.
     */
    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->read_at = now();
        $this->save();
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => NotificationType::class, 'read_at' => 'datetime'];
    }
}
