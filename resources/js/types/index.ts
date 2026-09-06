/**
 * The wire contract, written out rather than generated.
 *
 * Every key here is exactly the key the server sends. Field names stay snake_case all the
 * way to the input's `name`, so a validation message comes back under the key the form
 * already has and nothing in between has to translate it.
 */

export interface User {
  id: number
  email: string
  first_name: string
  last_name: string
}

export interface SharedProps {
  auth: { user: User | null }
  flash: { message: string | null }

  /**
   * Every message the last request produced, keyed by field name. `useForm` narrows its own
   * `errors` to the fields it holds, which is right for inputs and wrong for the failures
   * that belong to no single input — a wrong email-and-password pair, for one.
   */
  errors: Record<string, string>
  [key: string]: unknown
}

export type OrganizationRole = 'OWNER' | 'ADMIN' | 'MEMBER'

/** OWNER is missing on purpose: no form anywhere mints a second owner. */
export const ASSIGNABLE_ROLES: OrganizationRole[] = ['ADMIN', 'MEMBER']

export function canManage(role: OrganizationRole): boolean {
  return role === 'OWNER' || role === 'ADMIN'
}

export interface Organization {
  id: number
  name: string
  slug: string
  my_role: OrganizationRole
  member_count: number
  created_at: string
}

export interface Membership {
  id: number
  user_id: number
  email: string
  full_name: string
  role: OrganizationRole
  created_at: string
}
