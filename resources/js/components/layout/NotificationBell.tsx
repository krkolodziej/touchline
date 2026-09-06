import { router } from '@inertiajs/react'
import { Bell, CalendarClock, Trophy } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { formatRelative } from '@/lib/datetime'
import { cn } from '@/lib/cn'

interface AppNotification {
  id: number
  type: 'MATCH_FINISHED' | 'KICK_OFF_REMINDER'
  title: string
  body: string
  link: string
  organization_id: number
  organization_name: string
  created_at: string
  read_at: string | null
}

const ICON = {
  MATCH_FINISHED: Trophy,
  KICK_OFF_REMINDER: CalendarClock,
} as const

const COUNT_INTERVAL = 30_000

/**
 * A badge on a header every page already has.
 *
 * The count is polled; the list is only fetched when somebody opens the panel. Those are two
 * different questions — "is there anything?" is asked constantly and answers with one
 * integer, while "what is it?" is asked rarely and costs a query with joins. Fetching both
 * every thirty seconds would be paying the second price for the first answer.
 *
 * And the polling stops while the tab is hidden. A laptop lid closed overnight should not
 * come back to eight hundred requests.
 */
export function NotificationBell() {
  const [count, setCount] = useState(0)
  const [open, setOpen] = useState(false)
  const [notifications, setNotifications] = useState<AppNotification[] | null>(null)
  const panel = useRef<HTMLDivElement>(null)

  useEffect(() => {
    let cancelled = false

    const load = async () => {
      if (document.hidden) {
        return
      }

      try {
        const response = await fetch('/notifications/unread-count', {
          headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
          return
        }

        const body = (await response.json()) as { count: number }

        if (!cancelled) {
          setCount(body.count)
        }
      } catch {
        // A bell that cannot count is a bell without a number on it, not an error anybody
        // needs to be shown.
      }
    }

    void load()
    const timer = setInterval(() => void load(), COUNT_INTERVAL)

    return () => {
      cancelled = true
      clearInterval(timer)
    }
  }, [open])

  useEffect(() => {
    if (!open) {
      return
    }

    const onClickOutside = (event: MouseEvent) => {
      if (panel.current && !panel.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    const onEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', onClickOutside)
    document.addEventListener('keydown', onEscape)

    return () => {
      document.removeEventListener('mousedown', onClickOutside)
      document.removeEventListener('keydown', onEscape)
    }
  }, [open])

  const openPanel = async () => {
    setOpen(true)

    try {
      const response = await fetch('/notifications', { headers: { Accept: 'application/json' } })
      const body = (await response.json()) as { notifications: AppNotification[] }

      setNotifications(body.notifications)
    } catch {
      setNotifications([])
    }
  }

  const markAllRead = async () => {
    await fetch('/notifications/read', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
    })

    setCount(0)
    setNotifications((current) =>
      current?.map((notification) => ({
        ...notification,
        read_at: notification.read_at ?? new Date().toISOString(),
      })) ?? null,
    )
  }

  return (
    <div className="relative" ref={panel}>
      <button
        type="button"
        aria-label={count === 0 ? 'Notifications' : `Notifications, ${count} unread`}
        aria-expanded={open}
        onClick={() => (open ? setOpen(false) : void openPanel())}
        className="relative grid size-9 place-items-center rounded-[var(--radius-control)] text-foreground-subtle transition-colors hover:bg-surface-muted hover:text-foreground"
      >
        <Bell className="size-4" />

        {count > 0 ? (
          <span className="tabular absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-4 text-primary-foreground">
            {count > 9 ? '9+' : count}
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          role="dialog"
          aria-label="Notifications"
          className="surface-panel absolute right-0 top-11 z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden shadow-[var(--shadow-lift)]"
        >
          <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
            <h2 className="text-[13px] font-semibold">Notifications</h2>

            {count > 0 ? (
              <button
                type="button"
                onClick={() => void markAllRead()}
                className="text-[12.5px] text-primary hover:underline"
              >
                Mark all read
              </button>
            ) : null}
          </header>

          <div className="max-h-96 overflow-y-auto">
            {notifications === null ? (
              <p className="px-4 py-6 text-center text-[13px] text-foreground-muted">Loading…</p>
            ) : notifications.length === 0 ? (
              <p className="px-4 py-6 text-center text-[13px] text-foreground-muted">
                Nothing yet. Finished matches and tomorrow&rsquo;s kick-offs turn up here.
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {notifications.map((notification) => {
                  const Icon = ICON[notification.type]

                  return (
                    <li key={notification.id}>
                      <button
                        type="button"
                        onClick={() => {
                          setOpen(false)
                          router.post(`/notifications/${notification.id}/read`)
                        }}
                        className={cn(
                          'flex w-full items-start gap-3 px-4 py-2.5 text-left transition-colors hover:bg-surface-muted',
                          notification.read_at === null && 'bg-primary-wash/40',
                        )}
                      >
                        <Icon className="mt-0.5 size-4 shrink-0 text-foreground-subtle" />

                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-[13px] font-medium">
                            {notification.title}
                          </span>
                          <span className="block truncate text-[12.5px] text-foreground-muted">
                            {notification.body}
                          </span>
                          <span className="mt-0.5 block text-[11.5px] text-foreground-subtle">
                            {formatRelative(notification.created_at)}
                          </span>
                        </span>
                      </button>
                    </li>
                  )
                })}
              </ul>
            )}
          </div>
        </div>
      ) : null}
    </div>
  )
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}
