import '../css/app.css'

import { createInertiaApp } from '@inertiajs/react'
import type { ResolvedComponent } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'

import { ThemeProvider } from '@/app/theme'
import { AppShell } from '@/components/layout/AppShell'

const appName = import.meta.env.VITE_APP_NAME ?? 'Touchline'

const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx')

void createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),

  resolve: async (name) => {
    const loader = pages[`./pages/${name}.tsx`]

    if (!loader) {
      throw new Error(`No page component for "${name}".`)
    }

    const { default: page } = await loader()

    // Every page sits inside the shell unless it says otherwise. Declaring it here rather
    // than in each file means a new page cannot forget the header and come out chromeless.
    page.layout ??= (rendered: React.ReactNode) => <AppShell>{rendered}</AppShell>

    return page
  },

  setup({ el, App, props }) {
    if (!el) {
      throw new Error('Inertia found no root element to mount into.')
    }

    createRoot(el).render(
      <ThemeProvider>
        <App {...props} />
      </ThemeProvider>,
    )
  },

  progress: { color: 'var(--primary)' },
})
