#!/bin/sh
#
# Everything that has to happen between the container starting and it being able to answer.
#
# This runs on *every* start, not just on a deploy, because a free instance sleeps after
# fifteen quiet minutes and wakes up cold. So each step has to be safe to repeat.

set -e

cd /app

# ---------------------------------------------------------------------------
# Release
# ---------------------------------------------------------------------------
# Migrations run from here rather than from a pre-deploy hook, which is a paid feature on the
# plan this runs on. `--force` because there is nobody at a prompt to confirm.
#
# Deliberately not `--isolated`. That takes a cache lock, and the cache store here is a table
# that the very first migration is on its way to create — so on an empty database it fails
# before it starts. One instance on this plan makes the lock moot anyway.
if [ "${RUN_RELEASE_ON_START}" = "true" ]; then
    echo "==> Running migrations"
    php artisan migrate --force --no-interaction
fi

# ---------------------------------------------------------------------------
# Something to look at
# ---------------------------------------------------------------------------
# In the background, because building the league takes a couple of seconds and the platform's
# health check does not wait. The command is idempotent, so later starts find it already there
# and do nothing.
if [ "${SEED_DEMO_ON_START}" = "true" ]; then
    echo "==> Preparing the demonstration league in the background"
    php artisan app:seed:demo >/dev/null 2>&1 &
fi

# ---------------------------------------------------------------------------
# Background work
# ---------------------------------------------------------------------------
# In the same container, because a separate worker service is a paid feature here. An honest
# limitation follows from that: the worker sleeps when the instance does, so a reminder due
# during a quiet spell arrives when somebody next wakes it.
#
# `--max-time` rather than an endless run: a worker holds the code it booted with, and a
# bounded one picks up a new deploy without anybody restarting it. The loop brings it back.
if [ "${RUN_WORKER}" = "true" ]; then
    echo "==> Starting the queue worker"
    (
        while true; do
            php artisan queue:work --tries=3 --max-time=3600 --sleep=3 --quiet || true
            sleep 5
        done
    ) &
fi

# The scheduler, likewise. `schedule:work` is the long-running form of the minutely cron
# entry, which is what a platform with no crontab needs.
if [ "${RUN_SCHEDULER}" = "true" ]; then
    echo "==> Starting the scheduler"
    (
        while true; do
            php artisan schedule:work --quiet || true
            sleep 5
        done
    ) &
fi

# ---------------------------------------------------------------------------
# Serve
# ---------------------------------------------------------------------------
# A host-less SERVER_NAME means plain HTTP: the platform terminates TLS in front of us, and
# Caddy would otherwise try to get a certificate for a name it does not own.
export SERVER_NAME=":${PORT:-8080}"

echo "==> Serving on ${SERVER_NAME}"
exec frankenphp run --config /etc/caddy/Caddyfile
