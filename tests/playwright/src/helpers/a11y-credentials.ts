import { TestInfo } from '@playwright/test';

/**
 * Accounts the accessibility scans log in as.
 *
 * Phase 1's member and manage-adjacent scans are only representative when
 * they run as a real community member / Community Manager. Run as an
 * administrator they pick up Drupal-toolbar noise and see pages through
 * permissions the actual roles do not have, which is how an earlier
 * verification pass reported admin-toolbar findings as if they were new
 * member-facing ones (see findings/2026-07-22-post-merge-verification.md).
 */

/**
 * Reads an environment variable, treating empty as unset.
 *
 * This is not paranoia. An unset GitHub Actions secret interpolates to an
 * empty string rather than being absent, so `process.env.X ?? 'admin'`
 * yields '' the moment these are wired into a workflow, and every login
 * fails with an empty username. `||` would do, but being explicit keeps the
 * reason attached to the code.
 */
function envOrFallback(name: string, fallback: string): string {
  const value = process.env[name];
  return value === undefined || value.trim() === '' ? fallback : value;
}

export type Account = {
  username: string;
  password: string;
  /** True when no credentials were supplied and this is the admin fallback. */
  isFallback: boolean;
};

function account(userVar: string, passVar: string): Account {
  const username = envOrFallback(userVar, 'admin');
  return {
    username,
    password: envOrFallback(passVar, 'admin'),
    isFallback: username === 'admin' && !process.env[userVar]?.trim(),
  };
}

/**
 * The account Phase 1 member scans run as.
 *
 * Not the Phase 2 admin scans: those need permissions a representative
 * member must not have, so they use adminAccount().
 */
export function memberAccount(): Account {
  return account('A11Y_USERNAME', 'A11Y_PASSWORD');
}

/** The account Phase 1 manage-adjacent scans run as. */
export function managerAccount(): Account {
  return account('A11Y_MANAGER_USERNAME', 'A11Y_MANAGER_PASSWORD');
}

/**
 * An administrator, for setup steps that change site configuration.
 *
 * Distinct from memberAccount() on purpose. The scans should run as a
 * plain member, but enabling the submission form before scanning it needs
 * "administer mukurtu submissions", which a representative member account
 * must not have. Using the member account for setup would mean that the
 * moment real member credentials are configured, the setup starts failing
 * and the submission scans silently skip.
 *
 * Falls back to admin/admin, which is what the Tugboat build creates
 * (`drush site-install ... --account-pass="admin"`).
 */
export function adminAccount(): Account {
  return account('A11Y_ADMIN_USERNAME', 'A11Y_ADMIN_PASSWORD');
}

/**
 * Records in the test report that a scan fell back to the admin account.
 *
 * Without this the fallback is completely silent: the run goes green, the
 * results look like member results, and nothing anywhere says they were
 * gathered as an administrator. Annotating it means a reader of the report
 * can tell representative results from unrepresentative ones.
 */
export function noteFallbackAccount(testInfo: TestInfo, account: Account, scope: string): void {
  if (!account.isFallback) {
    return;
  }
  testInfo.annotations.push({
    type: 'a11y-account',
    description: `${scope} scan ran as the admin/admin fallback, so these results are not representative of the ${scope} role. Set the A11Y_* credentials to fix.`,
  });
}
