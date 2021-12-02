<?php

namespace Drupal\mukurtu_rights;

/**
 * Define interface for service to interact with the Local Contexts Label Hub.
 */
interface LocalContextsHubManagerInterface {

  /**
   * Fetch project details by ID.
   *
   * @param string $uuid
   *   The project unique id.
   *
   * @return \Drupal\mukurtu_rights\Entity\LocalContextsProjectInterface|null
   *   Project entity or null if not found.
   */
  public function getProject(string $uuid);

}
