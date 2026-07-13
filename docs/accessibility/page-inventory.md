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

Item pages are discovered from the browse listings at run time so the spec works against any site with default content seeded (`default-content.spec.ts`).

## Pages — authenticated member

| Page | Path | Notes |
|---|---|---|
| Logged-in home | `/` | Account menus, personal blocks |
| My content | `/my-content` | Member content dashboard |
| Personal collections | `/user/personal-collections` | |
| Account page | `/user` | |

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

## Out of scope (current phase)

Admin/authoring UI: Gin dashboards, node edit forms, bulk media upload (`modules/mukurtu_media/js/bulk-upload-dropzone.js`), import/export (`mukurtu_export`, `mukurtu_import`). These move into scope with the ATAG phase — see the [charter](README.md).
