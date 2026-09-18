# Accessibility Audit Page Inventory

The representative set of pages and components covered by the accessibility program. The automated scan (`tests/playwright/tests/accessibility.spec.ts`) visits every page listed here; the manual checklist targets the high-risk components.

When a new page type or interactive component ships, add it here and to the spec.

## Pages — anonymous visitor

| Page | Path | Notes |
|---|---|---|
| Home | `/` | Landing page, hero, menus |
| Browse (all content) | `/browse` | Facets, result listing |
| Digital Heritage browse | `/digital-heritage` | Grid/List/Map view switcher, facets |
| Collections browse | `/collections` | |
| Communities list | `/communities` | |
| Dictionary browse | `/dictionary` | Alphabet bar, view switcher |
| Login | `/user/login` | Form labels, errors |
| Digital heritage item | first item from browse | Media carousel, lightbox, map, citation, comments |
| Collection page | first collection from browse | Sub-collection nav |
| Community page | first community from list | |
| Dictionary word | first word from browse | Tab panels, audio player |
| Local Contexts directory | `/local-contexts` | Site-wide LC projects directory, no permission check |
| Access denied | `/mukurtu/access-denied` | Custom 403 page visitors land on from protocol-gated links |
| Community Local Contexts directory | discovered from `/communities` | Per-community LC directory |
| Protocol Local Contexts directory | discovered via a community's protocol link | Per-protocol LC directory; no public protocol listing page exists, so this is found by visiting a discovered community first |

Item pages are discovered from the browse listings at run time so the spec works against any site with default content seeded (`default-content.spec.ts`).

### Public submission form

| Page | Path | Notes |
|---|---|---|
| Submission form | `/submit/node/digital_heritage` | `PublicSubmissionForm` — a long, field-grouped public form with media upload |
| Submission thank-you | `/submit/node/digital_heritage/thank-you` | `ThankYouController` |

These are **not** in the anonymous table above because the submission form
ships disabled: the settings entity that gates it is `status: false` with
`access_level: authenticated`, and the `submit node digital_heritage content`
permission is only granted to the anonymous role once both are changed. So
the path is a 403 on a stock install, and a scan keyed on the URL alone would
silently never see it.

The specs therefore enable the form, scan it, and restore the previous
setting afterwards (`tests/playwright/src/helpers/submissions.ts`). This is
driven through the admin UI rather than drush because CI runs Playwright from
a GitHub runner against a remote Tugboat preview, where no local site exists.
If the scanning account cannot administer submissions, the form scan skips
with a reason rather than scanning the 403 page.

The admin side of this feature (settings list, settings form, the pending
submissions queue) is covered in
[page-inventory-admin.md](page-inventory-admin.md).

## Pages — authenticated member

| Page | Path | Notes |
|---|---|---|
| Logged-in home | `/` | Account menus, personal blocks |
| My content | `/my-content` | Member content dashboard |
| Personal collections | `/user/personal-collections` | |
| Account page | `/user` | |
| Digital heritage item (member view) | discovered from `/digital-heritage` | Protocol-gated fields, map, media |
| Collection page (member view) | discovered from `/collections` | |
| Community page (member view) | discovered from `/communities` | |
| Dictionary word (member view) | discovered from `/dictionary` | Tabs, audio |
| Member dashboard | `/dashboard/mukurtu_dashboard` | Default dashboard config entity, any logged-in member |
| Notifications | `/notifications` | Views page (`mukurtu_notifications_page`), any authenticated user |

Member scans use the account in `A11Y_USERNAME`/`A11Y_PASSWORD` (default `admin`/`admin`). Use a regular community/protocol member account for representative results — admin accounts add Drupal-toolbar noise, and on protocol-heavy sites only members can reach the gated item pages.

**On the Tugboat preview these accounts are provisioned for you.** The preview runs `drush site-install` on every build, so its only account is `admin`; `scripts/tugboat/provision-a11y-accounts.php` creates the member and manage-adjacent accounts during the build, from environment variables set on the Tugboat project. The script does nothing at all unless those variables are set, so a preview without them behaves exactly as before. The same values then go in the GitHub Actions secrets of the same name, so Playwright logs in as the accounts the build created. See [#2241](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2241).

The memberships are not incidental. A member account with no community or protocol membership cannot reach protocol-gated item pages at all, so those scans skip and the run looks clean while covering less. Measured against a local site: a member with no memberships scanned one page fewer than `admin` reached; with a community and protocol membership it matches `admin` exactly.

## Pages — manage-adjacent (non-admin roles)

Pages reachable by non-admin community/protocol roles (Community Managers, protocol contributors/curators/stewards via a custom `_mukurtu_role` requirement or OG permissions) — not a plain member page, and not part of the admin/authoring (ATAG) surface excluded below.

| Page | Path | Notes |
|---|---|---|
| Content (VBO dashboard) | `/admin/content` | Reachable by protocol contributor/curator/steward roles, not just admins |
| People list | `/admin/people/list` | Community Managers, via OG `manage members` permission |
| Create user | `/admin/communities/create-user` | Community Managers |
| Community Local Contexts projects | discovered from `/communities` | Community Managers/Protocol Stewards |
| Protocol Local Contexts projects | discovered via a community's protocol link | Community Managers/Protocol Stewards |

Manage scans use the account in `A11Y_MANAGER_USERNAME`/`A11Y_MANAGER_PASSWORD` (default `admin`/`admin`). As with member scans, a real Community Manager account gives more representative results than the admin fallback.

## High-risk interactive components

Priority order for manual keyboard/screen-reader testing. Automated scans cannot meaningfully assess most of these.

| Priority | Component | Code | Why high-risk |
|---|---|---|---|
| 1 | Leaflet maps (browse + item) | `modules/mukurtu_core/js/mukurtu-leaflet-widget.js`, `modules/mukurtu_browse/js/mukurtu-leaflet-preview.js`, `modules/mukurtu_browse/js/map-browse-bounding-box-query.js` | Map widgets are keyboard/SR hostile by default; bounding-box query is mouse-driven |
| 2 | Content warning overlays + consent popup | `modules/mukurtu_content_warnings/js/content-warnings.js`, `themes/mukurtu_v4/components/02-molecules/content-warning/`, `.../consent-popup/` | Interstitial gating: focus management, announcement, keyboard dismissal are load-bearing |
| 3 | Media carousel (Splide) | `themes/mukurtu_v4/js/media-asset-carousel.js`, `.../02-molecules/carousels/` | Slide announcement, control labels, keyboard operation |
| 4 | Lightbox (GLightbox) | `themes/mukurtu_v4/js/media-asset-glightbox.js` | Focus trap, Escape, control labels |
| 5 | Audio player | `themes/mukurtu_v4/js/audio-thumbnail-player.js` | Custom controls: name/role/state, keyboard |
| 6 | Dictionary word tabs | `themes/mukurtu_v4/js/dictionary-word-tabs.js`, `.../02-molecules/horizontal-tabs/` | Tabs ARIA pattern (roving tabindex, arrow keys) |
| 7 | Cultural protocol widget | `modules/mukurtu_protocol/js/cultural-protocol-widget.js`, `protocol-community-browser.js`, `membership-autocomplete.js` | Complex composite widget on core workflows |
| 8 | Dialogs | `modules/mukurtu_core/js/dialog-aria-modal.js`, `modules/mukurtu_local_contexts/js/local-contexts-dialog.js` | Existing aria-modal work — verify it holds |
| 9 | Tagify autocomplete | `modules/mukurtu_core/js/mukurtu-tagify-override.js`, `modules/mukurtu_gin_custom/js/mukurtu-tagify-focus.js` | Combobox pattern; third-party lib |
| 10 | View switchers / collapse toggles | `modules/mukurtu_browse/js/mukurtu-browse-view-switch.js`, `search-collapse-toggle.js`, `modules/mukurtu_dictionary/js/mukurtu-dictionary-view-switch.js` | Toggle state announcement |
| 11 | Facets soft limit ("show more") | `themes/mukurtu_v4/js/facets-soft-limit.js` | Expanded/collapsed state |
| 12 | Masonry grid | `themes/mukurtu_v4/components/03-organisms/masonry-grid/` | Visual order vs DOM order (WCAG 1.3.2, 2.4.3) |
| 13 | Multipage navigation | `modules/mukurtu_multipage_items/js/multipage-nav.js` | Keyboard operation, current-page state |
| 14 | Media alt-text entry | `modules/mukurtu_media/js/media-library-image-alt.js` | Directly feeds WCAG 1.1.1 for all content |
| 15 | Bot protection widget (ALTCHA) | `themes/mukurtu_v4/js/altcha-accessibility-fix.js`, `.../02-molecules/altcha/` | Third-party widget on login and public forms — a failure blocks sign-in outright; carries a local workaround (#2062) pending the v3 re-land (#2211) |

## Out of scope (current phase)

Admin/authoring UI: Gin dashboards, node edit forms, bulk media upload (`modules/mukurtu_media/js/bulk-upload-dropzone.js`), import/export (`mukurtu_export`, `mukurtu_import`). These move into scope with the ATAG phase — see the [charter](README.md).
