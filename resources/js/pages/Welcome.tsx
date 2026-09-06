import { Head } from '@inertiajs/react'

export default function Welcome({ version }: { version: string }) {
  return (
    <>
      <Head title="Welcome" />

      <div className="surface-panel mx-auto max-w-xl p-8 text-center">
        <h1 className="text-2xl">Touchline</h1>
        <p className="mt-3 text-foreground-muted">
          Run an amateur football league: register clubs and squads, generate a season&rsquo;s
          calendar, record what happens minute by minute, and read the table off those events.
        </p>
        <p className="mt-6 text-sm text-foreground-subtle tabular">Laravel {version}</p>
      </div>
    </>
  )
}
