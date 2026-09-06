import { useCallback, useEffect, useMemo, useState } from 'react'

import {
  ThemeContext,
  type ResolvedTheme,
  type ThemePreference,
} from '@/app/theme-context'

const STORAGE_KEY = 'touchline.theme'

function readStoredPreference(): ThemePreference {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)

    if (stored === 'light' || stored === 'dark' || stored === 'system') {
      return stored
    }
  } catch {
    /* Private windows throw on reading localStorage. Fall through to the default. */
  }

  return 'system'
}

function systemTheme(): ResolvedTheme {
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [preference, setPreferenceState] = useState<ThemePreference>(readStoredPreference)
  const [system, setSystem] = useState<ResolvedTheme>(systemTheme)

  // "System" is not a snapshot taken at load: someone flipping their OS to dark at dusk
  // should see the page follow without reloading it.
  useEffect(() => {
    const query = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = (event: MediaQueryListEvent) => setSystem(event.matches ? 'dark' : 'light')

    query.addEventListener('change', onChange)

    return () => query.removeEventListener('change', onChange)
  }, [])

  const resolved: ResolvedTheme = preference === 'system' ? system : preference

  useEffect(() => {
    document.documentElement.classList.toggle('dark', resolved === 'dark')
  }, [resolved])

  const setPreference = useCallback((next: ThemePreference) => {
    setPreferenceState(next)

    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      /* Not being able to remember the choice is not a reason to refuse to make it. */
    }
  }, [])

  const value = useMemo(
    () => ({ preference, resolved, setPreference }),
    [preference, resolved, setPreference],
  )

  return <ThemeContext value={value}>{children}</ThemeContext>
}
