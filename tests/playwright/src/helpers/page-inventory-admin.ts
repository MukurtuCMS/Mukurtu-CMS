/**
 * Phase 2 (admin/authoring, WCAG 2.1 AA + ATAG 2.0) equivalent of
 * page-inventory.ts. See docs/accessibility/page-inventory-admin.md at the
 * profile root -- keep both in sync when a page is added.
 *
 * A curated representative set, not an exhaustive scan of all custom admin
 * forms: issue #1975 already individually checked all 135 custom form
 * classes by reading the code (131 clean, 4 defects filed and fixed). This
 * inventory instead covers one representative page per admin surface
 * (content creation, bulk upload, import, export, settings, dashboards) so
 * the automated suite has ongoing coverage, plus the three
 * already-fixed defect forms as regression coverage.
 */
export const adminPages = [
  { slug: 'node-add-digital-heritage', path: '/node/add/digital_heritage' },
  { slug: 'node-add-dictionary-word', path: '/node/add/dictionary_word' },
  { slug: 'node-add-collection', path: '/node/add/collection' },
  { slug: 'bulk-media-upload', path: '/admin/content/media/bulk-upload/image' },
  // MukurtuImportStrategyForm -- regression coverage for the fixed #1976 defect.
  { slug: 'import-template-add', path: '/admin/import-templates/add' },
  { slug: 'import-upload', path: '/admin/import' },
  // MukurtuContentWarningsSettingsForm -- regression coverage for the fixed #1979 defect.
  { slug: 'content-warnings-settings', path: '/admin/config/mukurtu/content-warnings' },
  { slug: 'export-settings', path: '/admin/export/settings' },
  { slug: 'admin-overview', path: '/admin' },
  { slug: 'admin-structure', path: '/admin/structure' },
];

/**
 * Admin pages discovered from a listing page, same rationale as
 * discoveredPages in page-inventory.ts.
 */
export const adminDiscoveredPages = [
  {
    // CollectionOrganizationController -- regression coverage for the fixed #1978 defect.
    slug: 'collection-organization',
    listPath: '/collections',
    itemLink: 'main a[href*="/collection"]',
    pathSuffix: '/organization',
  },
];
