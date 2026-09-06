<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Console\Commands\SeedDemoCommand;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A way in without an account.
 *
 * It lands the visitor *inside* the season rather than on a list of organizations. A
 * competition already thirteen rounds deep is the thing they came to see, and somebody
 * dropped three clicks away from it mostly does not take them.
 *
 * It signs in as a **second** account, and that is the point. The seeder makes two: an owner,
 * which nothing reaches, and a visitor who is an administrator. Everything worth showing is
 * open to an administrator — creating leagues, registering clubs, running matches — while
 * deleting the organization needs OWNER. So a button on the open internet cannot destroy the
 * thing it opens.
 */
class DemoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Absent rather than forbidden when the switch is off: there is no such door here.
        if (! config('app.demo_login_enabled')) {
            throw new NotFoundHttpException;
        }

        $visitor = User::query()->where('email', SeedDemoCommand::VISITOR_EMAIL)->first();
        $organization = Organization::query()->where('slug', SeedDemoCommand::SLUG)->first();

        // Seeding runs in the background on a cold start so the first request is not held up
        // by it, which leaves a minute or so where the button exists and the league does not.
        // Saying so is better than a login that half works.
        if ($visitor === null || $organization === null) {
            throw new HttpException(503, 'The demonstration league is still being prepared. Try again in a minute.');
        }

        Auth::login($visitor);
        $request->session()->regenerate();

        $season = Season::query()
            ->whereIn('league_id', $organization->leagues()->select('id'))
            ->orderByDesc('start_date')
            ->first();

        if ($season === null) {
            return redirect()->route('organizations.leagues', $organization->id);
        }

        return redirect()->route('seasons.overview', [
            $organization->id,
            $season->league_id,
            $season->id,
        ]);
    }
}
