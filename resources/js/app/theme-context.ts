import { createContext, use } from 'react'

export type ThemePreference = 'light' | 'dark' | 'system'
export type ResolvedTheme = 'light' | 'dark'

export interface ThemeContextValue {
  preference: ThemePreference
  resolved: ResolvedTheme
  setPreference: (preference: ThemePreference) => void
}

/* Split out from the provider so the provider file exports only components and Vite's
   fast refresh keeps working. */
export const ThemeContext = createContext<ThemeContextValue | null>(null)

export function useTheme(): ThemeContextValue {
  const value = use(ThemeContext)

  if (!value) {
    throw new Error('useTheme has to be used inside a ThemeProvider.')
  }

  return value
}
