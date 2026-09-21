import * as path from 'path';

/**
 * Where the auth setup project saves each role's signed-in session.
 *
 * The suite used to log in in a `beforeEach`, which meant about 66 logins
 * per run for three accounts: 10 member and 5 manage-adjacent scans in each
 * of the two Phase 1 specs, 14 admin scans in each of the two Phase 2
 * specs, 6 in default-content, and 2 for the submission-form setup and
 * teardown. Every one of them carried the fixed 7-second wait for
 * Honeypot's time floor in Login.login(), and every one was an independent
 * chance to hit whatever the Tugboat preview happened to be doing at that
 * moment -- the run went red if any single one landed in an outage.
 *
 * tests/auth.setup.ts logs each role in once and saves its state here;
 * consumers declare `test.use({ storageState: ... })` instead of logging
 * in. Three logins per run, not 66. See issue #2280.
 *
 * Under playwright/ because tests/playwright/.gitignore already excludes
 * `/playwright/.cache/`, and these files are session cookies: they must
 * never be committed.
 */
const AUTH_DIR = path.join(__dirname, '..', '..', 'playwright', '.auth');

/** The account Phase 1 member scans run as. */
export const MEMBER_STATE = path.join(AUTH_DIR, 'member.json');

/** The account Phase 1 manage-adjacent scans run as. */
export const MANAGER_STATE = path.join(AUTH_DIR, 'manager.json');

/**
 * An administrator, for the Phase 2 admin scans, default content creation,
 * and the submission-form setup that has to change site configuration.
 */
export const ADMIN_STATE = path.join(AUTH_DIR, 'admin.json');
