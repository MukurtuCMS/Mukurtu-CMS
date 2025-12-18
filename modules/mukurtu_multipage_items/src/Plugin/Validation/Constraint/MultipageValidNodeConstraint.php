<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the node to be added to MPI pages is valid.
 */
#[Constraint(
  id: 'MultipageValidNode',
  label: new TranslatableMarkup('Multipage Valid Node', [], ['context' => 'Validation']),
  type: 'string'
)]
class MultipageValidNodeConstraint extends SymfonyConstraint {

  public $isDuplicate = '%value is a duplicate.';

  public $alreadyInMPI = '%value is a page in a different multipage item. Multipage items cannot share pages.';

  public $isCommunityRecord = '%value: Community records cannot be pages of a multipage item.';

  public $notEnabledBundleType = '%value: This content type is not enabled for multipage items.';

  protected $enabledBundles;

}
