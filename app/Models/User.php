<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * An account carries no privileges of its own. Authority is granted per organization, on a
 * membership row, so the same person can own one competition and merely read another —
 * which is why there is deliberately no role column here to check.
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read non-empty-string $full_name
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['email', 'password', 'first_name', 'last_name'];

    /** @var list<string> */
    protected $hidden = ['password'];

    /**
     * Normalised on the way in rather than on the way out, so the unique index sees the
     * same value every reader does.
     *
     * @return Attribute<never, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => mb_strtolower(trim($value)));
    }

    /** @return Attribute<never, string|null> */
    protected function firstName(): Attribute
    {
        return Attribute::set(fn (?string $value): string => trim($value ?? ''));
    }

    /** @return Attribute<never, string|null> */
    protected function lastName(): Attribute
    {
        return Attribute::set(fn (?string $value): string => trim($value ?? ''));
    }

    /**
     * Falls back to the email so a row in a members list is never blank: an account can be
     * created without a name, and "" reads as a rendering bug rather than as a choice.
     *
     * @return Attribute<non-empty-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim($this->first_name.' '.$this->last_name);

            return $name === '' ? $this->email : $name;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
