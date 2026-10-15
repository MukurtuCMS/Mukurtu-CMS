/**
 * The page-level extraction that runs in the browser.
 *
 * Everything here answers a question a human would otherwise have to hunt
 * for. None of it decides whether a criterion passes: the judgement is the
 * part a person has to do, and this exists so they spend their time on that
 * rather than on inventory.
 */
export const EXTRACT = () => {
  const text = (el: Element | null) => (el?.textContent || '').replace(/\s+/g, ' ').trim();
  const visible = (el: Element) => {
    const s = getComputedStyle(el);
    const b = el.getBoundingClientRect();
    return s.display !== 'none' && s.visibility !== 'hidden' && b.width > 0 && b.height > 0;
  };
  const name = (el: Element): string => {
    const labelledby = el.getAttribute('aria-labelledby');
    if (labelledby) {
      const t = labelledby.split(/\s+/).map((id) => text(document.getElementById(id))).join(' ').trim();
      if (t) return t;
    }
    const aria = el.getAttribute('aria-label');
    if (aria?.trim()) return aria.trim();
    if (el.id) {
      const lab = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
      if (text(lab)) return text(lab);
    }
    const wrapping = el.closest('label');
    if (wrapping && text(wrapping)) return text(wrapping);
    const tag = el.tagName.toLowerCase();
    if (tag === 'input') {
      const t = (el as HTMLInputElement).type;
      if (['submit', 'button', 'reset'].includes(t) && (el as HTMLInputElement).value) {
        return (el as HTMLInputElement).value;
      }
    }
    if (text(el)) return text(el);
    const img = el.querySelector('img[alt]');
    if (img?.getAttribute('alt')?.trim()) return img.getAttribute('alt')!.trim();
    return el.getAttribute('title')?.trim() || '';
  };
  const role = (el: Element) => el.getAttribute('role') || el.tagName.toLowerCase();

  const FOCUSABLE = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [tabindex]:not([tabindex="-1"]), [contenteditable=""], [contenteditable="true"]';

  // Tab order, in DOM order, with the position each lands at. A reader
  // compares the sequence against what they see; the coordinates are there so
  // a backwards jump is obvious without re-tabbing.
  const focusables = [...document.querySelectorAll<HTMLElement>(FOCUSABLE)]
    .filter(visible)
    .map((el, i) => {
      const b = el.getBoundingClientRect();
      return {
        i: i + 1, role: role(el), name: name(el).slice(0, 70) || '(no accessible name)',
        x: Math.round(b.left + window.scrollX), y: Math.round(b.top + window.scrollY),
        tabindex: el.getAttribute('tabindex'),
      };
    });

  // A stop that sits above the previous one is where DOM order and reading
  // order most often part company.
  const backwards = focusables.filter((f, i) => i > 0 && f.y < focusables[i - 1].y - 24);

  const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6,[role=heading]')]
    .filter(visible)
    .map((h) => ({
      level: Number(h.getAttribute('aria-level') || h.tagName.replace('H', '')) || 2,
      text: text(h).slice(0, 80),
    }));
  const skipped: string[] = [];
  headings.reduce((prev, h) => {
    if (prev && h.level > prev + 1) skipped.push(`h${prev} -> h${h.level}: "${h.text}"`);
    return h.level;
  }, 0 as number);

  // Links grouped by text: the same wording pointing at different places is
  // the 2.4.4 failure that reads fine one link at a time.
  const links = [...document.querySelectorAll('a[href]')].filter(visible).map((a) => ({
    text: name(a).toLowerCase().slice(0, 60) || '(no accessible name)',
    href: (a as HTMLAnchorElement).getAttribute('href') || '',
  }));
  const byText = new Map<string, Set<string>>();
  for (const l of links) {
    if (!byText.has(l.text)) byText.set(l.text, new Set());
    byText.get(l.text)!.add(l.href);
  }
  const ambiguous = [...byText.entries()]
    .filter(([, hrefs]) => hrefs.size > 1)
    .map(([t, hrefs]) => ({ text: t, targets: [...hrefs].slice(0, 4) }));

  const liveRegions = [...document.querySelectorAll('[aria-live], [role=status], [role=alert], [role=log]')]
    .map((el) => ({
      role: el.getAttribute('role') || '(none)',
      live: el.getAttribute('aria-live') || '(implicit)',
      atomic: el.getAttribute('aria-atomic') || '(unset)',
      currentlyEmpty: text(el).length === 0,
      where: el.id ? `#${el.id}` : (typeof el.className === 'string' && el.className ? `.${el.className.trim().split(/\s+/)[0]}` : el.tagName.toLowerCase()),
    }));

  // Anything that reveals content on hover or focus is 1.4.13's subject.
  const hoverTargets = [...document.querySelectorAll('[title], [data-tooltip], .tooltip, [aria-describedby]')]
    .filter(visible)
    .map((el) => ({
      where: el.tagName.toLowerCase() + (el.id ? `#${el.id}` : ''),
      name: name(el).slice(0, 50),
      via: el.hasAttribute('title') ? 'title attribute' : (el.hasAttribute('aria-describedby') ? 'aria-describedby' : 'tooltip class'),
    }));

  const controls = [...document.querySelectorAll<HTMLElement>('input:not([type=hidden]), select, textarea')]
    .filter(visible)
    .map((el) => ({
      name: name(el).slice(0, 50) || '(no accessible name)',
      field: (el as HTMLInputElement).name || el.id,
      required: el.hasAttribute('required') || el.getAttribute('aria-required') === 'true',
      describedby: el.getAttribute('aria-describedby') || '(none)',
    }));

  return {
    title: document.title,
    lang: document.documentElement.getAttribute('lang') || '(unset)',
    landmarks: [...document.querySelectorAll('main,nav,header,footer,aside,[role=main],[role=navigation],[role=banner],[role=contentinfo],[role=complementary],[role=search]')]
      .filter(visible)
      .map((el) => ({ role: role(el), label: el.getAttribute('aria-label') || text(document.getElementById(el.getAttribute('aria-labelledby') || '')) || '(unlabelled)' })),
    focusables, backwards, headings, skipped, ambiguous, liveRegions,
    hoverTargets: hoverTargets.slice(0, 20), controls,
  };
};
