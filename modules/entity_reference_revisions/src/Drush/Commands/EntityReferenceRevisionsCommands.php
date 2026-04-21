<?php

namespace Drupal\entity_reference_revisions\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsOrphanPurger;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

/**
 * A Drush commandfile.
 */
final class EntityReferenceRevisionsCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a ERRCommands object.
   *
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsOrphanPurger $purger
   */
  public function __construct(
    protected EntityReferenceRevisionsOrphanPurger $purger
  ) {
    parent::__construct();
  }

  /**
   * Orphan composite revision deletion.
   */
  #[CLI\Command(name: 'err:purge', aliases: ['errp'])]
  #[CLI\Argument(name: 'types', description: 'A comma delimited list of entity types to check for orphans. Omit to choose from a list.')]
  #[CLI\Usage(name: 'drush err:purge paragraph', description: 'Purge orphaned paragraphs.')]
  public function purge($types) {
    $this->purger->setBatch(StringUtils::csvToArray($types));
    drush_backend_batch_process();
  }

  /**
   * Interact hook for err:purge command.
   */
  #[CLI\Hook(type: HookManager::INTERACT, target: 'err:purge')]
  public function interact($input, $output) {
    if (empty($input->getArgument('types'))) {
      $choices = [];
      foreach ($this->purger->getCompositeEntityTypes() as $entity_type) {
        $choices[(string) $entity_type->id()] = (string) $entity_type->getLabel();
      }
      $selected = $this->io()->choice(dt("Choose the entity type to clear"), $choices);
      $input->setArgument('types', $selected);
    }
  }

}
