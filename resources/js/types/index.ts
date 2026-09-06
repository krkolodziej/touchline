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

export type PlayerPosition = 'GOALKEEPER' | 'DEFENDER' | 'MIDFIELDER' | 'FORWARD'

export const PLAYER_POSITIONS: PlayerPosition[] = [
  'GOALKEEPER',
  'DEFENDER',
  'MIDFIELDER',
  'FORWARD',
]

export interface League {
  id: number
  organization_id: number
  name: string
  slug: string
  description: string
  created_at: string
  season_count: number
}

export interface Team {
  id: number
  organization_id: number
  name: string
  short_name: string
  slug: string
  created_at: string
  squad_size: number
  seasons_played: number
}

export interface Player {
  id: number
  organization_id: number
  first_name: string
  last_name: string
  full_name: string
  date_of_birth: string | null
  age: number | null
  created_at: string
}

/** The paginated envelope. Page numbers, not URLs — see the server side for why. */
export interface ResultPage<T> {
  count: number
  page: number
  page_size: number
  next: number | null
  previous: number | null
  results: T[]
}

export interface ListQueryState {
  search: string
  page: number | null
  page_size: number | null
  order: string | null
}

/**
 * A collection arrives either as a plain array or as the envelope, depending on whether the
 * caller asked for a page. Both shapes are read the same way from here on.
 */
export function toRows<T>(value: T[] | ResultPage<T>): {
  rows: T[]
  page: ResultPage<T> | null
} {
  return Array.isArray(value) ? { rows: value, page: null } : { rows: value.results, page: value }
}

export interface OrganizationTabProps {
  organization: Organization
  counts: { leagues: number; clubs: number; players: number; members: number }
  can_manage: boolean
  can_delete: boolean
}

export interface Season {
  id: number
  league_id: number
  name: string
  start_date: string
  end_date: string | null
  created_at: string
  club_count: number
}

export interface SeasonTeam {
  id: number
  season_id: number
  team_id: number
  team_name: string
  team_short_name: string
  squad_size: number
}

export interface RosterEntry {
  id: number
  season_team_id: number
  player_id: number
  player_name: string
  shirt_number: number | null
  position: PlayerPosition | null
  captain: boolean
}

export interface NamedRef {
  id: number
  name: string
}

export interface SeasonTabProps {
  organization: NamedRef
  league: NamedRef
  season: Season
  counts: { clubs: number; fixtures: number }
  can_manage: boolean
}

export type MatchStatus = 'SCHEDULED' | 'LIVE' | 'FINISHED' | 'CANCELLED' | 'POSTPONED'

export interface Fixture {
  id: number
  season_id: number
  round_number: number
  leg: number
  home_team_id: number
  home_team_name: string
  home_team_short_name: string
  away_team_id: number
  away_team_name: string
  away_team_short_name: string
  kick_off_at: string | null
  status: MatchStatus
  home_score: number
  away_score: number
  started_at: string | null
  finished_at: string | null
  /** What the server would accept right now. The client keeps no copy of the rules. */
  allowed_transitions: MatchStatus[]
}

export type MatchEventType = 'GOAL' | 'YELLOW_CARD' | 'RED_CARD' | 'SUBSTITUTION'

export const MATCH_EVENT_TYPES: MatchEventType[] = [
  'GOAL',
  'YELLOW_CARD',
  'RED_CARD',
  'SUBSTITUTION',
]

export interface MatchEvent {
  id: number
  fixture_id: number
  type: MatchEventType
  minute: number
  team_id: number
  /** Which side of the timeline it belongs on. Derived on the server. */
  home: boolean
  player_id: number
  player_name: string
  related_player_id: number | null
  related_player_name: string | null
}

export interface SquadForMatch {
  team_id: number
  team_name: string
  players: { id: number; full_name: string; shirt_number: number | null }[]
}

export function isLive(fixture: Fixture): boolean {
  return fixture.status === 'LIVE'
}
