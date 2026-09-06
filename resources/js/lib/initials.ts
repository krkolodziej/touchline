/**
 * Two letters from a name, or one from an email address when there is no name yet.
 *
 * Deliberately not an avatar upload: a coloured pair of initials is legible at 28px,
 * needs no storage, and cannot be a broken image.
 */
export function initialsOf(first: string, last: string, email: string): string {
  const letters = `${first.trim()[0] ?? ''}${last.trim()[0] ?? ''}`

  return (letters || email.trim()[0] || '?').toUpperCase()
}
