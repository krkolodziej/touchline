<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Database\Factories\OrganizationFactory;

/**
 * An organization with the three people every competition test needs: somebody who runs it,
 * somebody who only reads it, and somebody who is not in it at all.
 *
 * A typed object rather than properties set on the test case in `beforeEach`. Pest allows
 * the latter, but nothing can then tell what those properties hold, and static analysis has
 * to be told to look the other way over a whole file.
 */
class Cast
{
    public function __construct(
        public readonly Organization $organization,
        public readonly User $admin,
        public readonly User $member,
        public readonly User $stranger,
    ) {}

    public static function make(): self
    {
        $organization = OrganizationFactory::new()->createOne();

        return new self(
            organization: $organization,
            admin: memberOf($organization, OrganizationRole::Admin),
            member: memberOf($organization),
            stranger: \Database\Factories\UserFactory::new()->createOne(),
        );
    }

    /** The organization's own address, with an optional path under it. */
    public function url(string $path = ''): string
    {
        return "/organizations/{$this->organization->id}".$path;
    }
}
