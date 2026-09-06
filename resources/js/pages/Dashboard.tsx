import { Head, usePage } from '@inertiajs/react'

import type { SharedProps } from '@/types'

export default function Dashboard() {
  const { auth } = usePage<SharedProps>().props

  return (
    <>
      <Head title="Dashboard" />

      <div className="surface-panel p-7">
        <h1 className="text-[22px]">
          {auth.user?.first_name ? `Hello, ${auth.user.first_name}` : 'Hello'}
        </h1>
        <p className="mt-2 text-sm text-foreground-muted">
          Organizations, leagues and everything they hold arrive in the next stage.
        </p>
      </div>
    </>
  )
}
