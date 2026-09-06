<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Authority inside one organization. Deliberately not a Laravel gate ability or a column on
 * users: the same person can own one competition and merely read another, so the role lives
 * on the membership row that joins them, and an account on its own carries nothing.
 */
enum OrganizationRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';

    /** Owners and administrators write; members read. */
    public function canManage(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * Ownership is established once, when the organization is created, and is not handed out
     * through the members API. An organization with two owners, or with none, is a state
     * nothing else in the application knows how to reason about.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member];
    }

    /** @return list<string> */
    public static function assignableValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::assignable());
    }

    /** Owners first, then administrators, then members — the order a roster is read in. */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 0,
            self::Admin => 1,
            self::Member => 2,
        };
    }
}
