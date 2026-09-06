<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This suite tests the server, not the asset pipeline. Without this, every test that
        // renders a full page rather than an Inertia response depends on somebody having run
        // `npm run build` first — which is true on a developer's machine and false in CI, so
        // the failure only ever appears where it is hardest to read.
        $this->withoutVite();
    }
}
