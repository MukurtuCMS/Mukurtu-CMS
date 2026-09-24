import { Page, Response, test } from '@playwright/test';

/**
 * Tolerating a Tugboat preview that stops serving the site mid-run.
 *
 * A Tugboat preview that is suspended, resuming, building or refreshing does
 * not return an error. Tugboat's proxy answers *every* request with its own
 * branded holding page, at HTTP 200, with no Drupal markup on it. That page
 * polls Tugboat's API over a websocket and calls location.reload(true) once
 * the preview is back.
 *
 * Two consequences, both observed in CI on 2026-09-20:
 *
 * 1. Login.login() navigated to /user/login, got a 200 with no Username
 *    field, and timed out after 30s waiting for one. Eight admin scans
 *    failed that way on seven of the ten PR runs that started within the
 *    same 30 seconds, while the identical code passed on every run launched
 *    on its own. The artifact from run 35536831683 brackets the outage
 *    precisely: real axe results at 21:01, eight login failures from ~21:04,
 *    real results again at 21:09:58.
 * 2. openForAudit() checks response.ok(), and the holding page is a 200, so a
 *    scan landing in that window would have audited Tugboat's own page and
 *    reported it clean -- the exact silent false pass openForAudit() exists
 *    to prevent.
 *
 * gotoReady() is the fix for both: it is the suite's single navigation entry
 * point, and it waits the holding page out instead of letting a locator wait
 * for a reload that may never come inside its own budget.
 */

/**
 * Tugboat titles its holding page "Tugboat - Preview is <state>", both
 * server-side and from its own script, and its missing-preview page
 * "Tugboat - Preview Not Found". The prefix is the detector: no Mukurtu page
 * carries it, which tests/preview-resilience.spec.ts pins from both
 * directions so this cannot quietly stop matching.
 */
export const TUGBOAT_TITLE_PREFIX = 'Tugboat - ';

const TUGBOAT_STATE_PREFIX = 'Preview is ';

/**
 * States Tugboat comes back from on its own, taken from the holding page's
 * own getStateMessage() -- the states it captions "This page will
 * automatically refresh when the preview is ready", plus 'ready' itself,
 * which is the moment before its reload fires.
 *
 * Every other state (absent, cancelled, failed, stopped, unavailable,
 * cancelling, stopping, deleting, and the separate Preview Not Found page)
 * is terminal: waiting it out would burn the whole budget to reach the same
 * failure, so gotoReady() gives up on those immediately.
 */
export const TUGBOAT_TRANSIENT_STATES = [
  'building',
  'ready',
  'refreshing',
  'resuming',
  'starting',
  'suspended',
];

/** How long gotoReady() waits for a transient state to clear. */
const DEFAULT_WAIT_MS = 180_000;

/** How long it leaves between attempts. */
const POLL_INTERVAL_MS = 10_000;

/**
 * How Playwright reports a navigation that ours was superseded by.
 *
 * On this suite that means one thing: Tugboat's holding page firing its own
 * location.reload(true) because the preview came back. Both shapes were
 * observed while building this helper, and which one you get depends on
 * whether the reload lands before or during our request:
 *
 * - `Navigation to "X" is interrupted by another navigation to "X"`
 * - `net::ERR_ABORTED at X`, when the in-flight request is cancelled outright
 *
 * Matching on the message is unlovely, but Playwright gives neither a type.
 */
const SUPERSEDED_NAVIGATION_ERRORS = [
  'interrupted by another navigation',
  'net::ERR_ABORTED',
];

/**
 * How many extra navigations to spend recovering a Response that Tugboat's
 * reload took with it. Bounded so a caller navigating somewhere that
 * genuinely yields no response (a same-document navigation, which
 * openForAudit() handles on purpose) is not put in a loop.
 */
const MAX_RESPONSE_REPAIRS = 2;

export type GotoReadyOptions = {
  /** Total time to spend waiting out a holding page. */
  timeoutMs?: number;
  /** Time between navigation attempts while waiting. */
  pollIntervalMs?: number;
};

/**
 * Thrown when the preview is not serving the site and waiting did not help.
 *
 * A distinct type so the failure names itself in the CI log, and so a caller
 * that would rather degrade than fail can tell it apart from an assertion
 * failure without matching on message text.
 *
 * Nothing catches it today, on purpose. Turning a preview outage into a
 * skipped scan would leave the run green and the report looking complete
 * while the pages in it were never opened, which is a worse outcome than a
 * red build that says what happened.
 */
export class PreviewUnavailableError extends Error {
  public readonly state: string;

  public constructor(message: string, state: string) {
    super(message);
    this.name = 'PreviewUnavailableError';
    this.state = state;
  }
}

/**
 * The Tugboat preview state the current document reports, or null when the
 * document is the site itself.
 *
 * Keyed on the title rather than the body: the body is rewritten by
 * Tugboat's own script as the state changes, while the title is set both
 * server-side and by that script, so it is correct either way.
 */
export async function tugboatPreviewState(page: Page): Promise<string | null> {
  let title: string;
  try {
    title = await page.title();
  } catch {
    // Mid-navigation, or no document at all. Not something to report as a
    // preview state; the caller's own navigation handling covers it.
    return null;
  }

  if (!title.startsWith(TUGBOAT_TITLE_PREFIX)) {
    return null;
  }

  const rest = title.slice(TUGBOAT_TITLE_PREFIX.length).trim();
  return rest.startsWith(TUGBOAT_STATE_PREFIX)
    ? rest.slice(TUGBOAT_STATE_PREFIX.length).trim()
    : rest;
}

/**
 * Gives the running test back the time this wait is about to spend.
 *
 * Without this the wait is self-defeating: a hook or test with a 60s budget
 * is killed long before a 180s wait could finish, and the failure reads as a
 * test timeout rather than as a preview outage. Extending only when a wait
 * actually starts leaves ordinary hang detection at its configured timeout.
 */
function extendTestTimeout(byMs: number): void {
  try {
    const info = test.info();
    // 0 means "no timeout"; adding to it would impose one.
    if (info.timeout > 0) {
      info.setTimeout(info.timeout + byMs);
    }
  } catch {
    // Called outside a running test. Nothing to extend.
  }
}

/**
 * Navigates to a URL, waiting out a Tugboat preview that is not up yet.
 *
 * Use this in place of page.goto() everywhere in the suite. It returns the
 * same Response that page.goto() returns, so callers keep whatever status
 * handling they already have.
 *
 * Re-navigating on a timer rather than waiting for Tugboat's own
 * location.reload(true) keeps this independent of Tugboat's client-side
 * script, and gives the wait a budget we control.
 */
export async function gotoReady(
  page: Page,
  url: string,
  options: GotoReadyOptions = {},
): Promise<Response | null> {
  const timeoutMs = options.timeoutMs ?? DEFAULT_WAIT_MS;
  const pollIntervalMs = options.pollIntervalMs ?? POLL_INTERVAL_MS;
  const deadline = Date.now() + timeoutMs;
  let sawHoldingPage = false;
  let announced = false;
  let repairs = 0;

  for (;;) {
    let response: Response | null = null;

    try {
      response = await page.goto(url);
    } catch (error) {
      const superseded = error instanceof Error
        && SUPERSEDED_NAVIGATION_ERRORS.some((marker) => error.message.includes(marker));
      if (!superseded) {
        throw error;
      }
      // The preview coming back, not a failure: Tugboat's holding page
      // reloaded itself while our navigation was in flight. Let that
      // navigation finish before reading the page.
      sawHoldingPage = true;
      await page.waitForLoadState('load').catch(() => {});
    }

    const state = await tugboatPreviewState(page);

    if (state === null) {
      if (response !== null) {
        return response;
      }
      // The site is up but we have no Response to hand back, because
      // Tugboat's reload superseded our navigation. Callers read null as "no
      // HTTP response; nothing to audit" and skip, so navigate again to get
      // one rather than silently not scanning a page on a healthy preview.
      // No sleep: nothing is pending, the next attempt lands on the site.
      if (!sawHoldingPage || repairs >= MAX_RESPONSE_REPAIRS || Date.now() >= deadline) {
        return null;
      }
      repairs += 1;
      continue;
    }

    sawHoldingPage = true;

    if (!TUGBOAT_TRANSIENT_STATES.includes(state.toLowerCase())) {
      throw new PreviewUnavailableError(
        `The Tugboat preview is not serving the site and will not recover on its own (state: ${state}) at ${url}. Check the preview's Tugboat dashboard.`,
        state,
      );
    }

    if (Date.now() >= deadline) {
      throw new PreviewUnavailableError(
        `The Tugboat preview stopped serving the site mid-run (state: ${state}) and did not come back within ${Math.round(timeoutMs / 1000)}s at ${url}.`,
        state,
      );
    }

    if (!announced) {
      extendTestTimeout(timeoutMs);
      announced = true;
      // Loud on purpose: a run that pauses for minutes should say why in the
      // CI log, not look like a hang.
      console.log(`Tugboat preview is ${state}; waiting up to ${Math.round(timeoutMs / 1000)}s for it before ${url}.`);
    }

    await page.waitForTimeout(pollIntervalMs);
  }
}
