<?php

namespace Drupal\mukurtu_local_contexts\Form;

/**
 * Provides a form for remapping a site-wide legacy Local Contexts project.
 */
class RemapLegacyProjectSite extends RemapLegacyProjectBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_remap_legacy_project_site';
  }

}
