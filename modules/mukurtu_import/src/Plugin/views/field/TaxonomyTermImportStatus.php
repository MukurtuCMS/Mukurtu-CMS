<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;

/**
 * Former taxonomy-only import status field, kept as an alias.
 *
 * mukurtu_import_update_40403() replaces it with mukurtu_import_status in
 * the taxonomy terms results view. It stays registered so a site whose view
 * still references it keeps rendering until that update runs.
 *
 * @see \Drupal\mukurtu_import\Plugin\views\field\ImportStatus
 */
#[ViewsField("mukurtu_import_term_status")]
class TaxonomyTermImportStatus extends ImportStatus {

}
