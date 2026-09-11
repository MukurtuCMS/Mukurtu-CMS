# Accessibility Audit Page Inventory — Admin (Phase 2)

The Phase 2 (admin/authoring, WCAG 2.1 AA + ATAG 2.0) equivalent of [page-inventory.md](page-inventory.md). The automated scan (`tests/playwright/tests/accessibility-admin.spec.ts`) visits every page listed here.

**This is a curated representative set, not an exhaustive scan of all custom admin forms.** Issue [#1975](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1975) already individually checked all 135 custom form classes by reading the code (131 clean, 4 defects filed and fixed: [#1976](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1976), [#1977](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1977), [#1978](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1978), [#1979](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1979)). This inventory instead gives the automated suite ongoing coverage of one representative page per admin surface, plus the three fixed defect forms as regression coverage. When a new admin page type ships, add it here and to `page-inventory-admin.ts`.

## Pages — admin

| Page | Path | Notes |
|---|---|---|
| Add digital heritage item | `/node/add/digital_heritage` | Content creation form |
| Add dictionary word | `/node/add/dictionary_word` | |
| Add collection | `/node/add/collection` | |
| Bulk media upload | `/admin/content/media/bulk-upload/image` | `BulkMediaUploadForm` |
| Import template (add) | `/admin/import-templates/add` | `MukurtuImportStrategyForm` — regression coverage for fixed #1976 |
| Import (upload step) | `/admin/import` | Entry point of a multi-step wizard; only this first step is a fixed, single-GET URL |
| Content warnings settings | `/admin/config/mukurtu/content-warnings` | `MukurtuContentWarningsSettingsForm` — regression coverage for fixed #1979 |
| Collection organization | discovered from `/collections` | `CollectionOrganizationController` — regression coverage for fixed #1978 |
| Export settings | `/admin/export/settings` | |
| Admin overview | `/admin` | Core route |
| Admin structure | `/admin/structure` | Core route |

Admin scans use the account in `A11Y_USERNAME`/`A11Y_PASSWORD` (default `admin`/`admin`) — a real admin account is appropriate here, unlike Phase 1's member/manage-adjacent scans, since these are genuinely admin-only routes.

## Out of scope (this pass)

Every other admin form and workflow screen not listed above. #1975's manual code-reading pass already covers form-level labeling/AJAX-announcement correctness across all 135 forms; this inventory exists for *ongoing automated regression coverage* of a representative sample, not to duplicate that audit. Expand this list opportunistically as new admin surfaces ship or as specific forms need dedicated coverage.
