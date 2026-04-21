<?php

namespace Drupal\test_layout_builder_restrictions\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a test block.
 */
#[Block(
  id: "test_layout_builder_restrictions",
  admin_label: new TranslatableMarkup("Test Block"),
  category: new TranslatableMarkup("Layout Builder Restrictions")
)]
class TestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('Hello, Test!'),
    ];
  }

}
