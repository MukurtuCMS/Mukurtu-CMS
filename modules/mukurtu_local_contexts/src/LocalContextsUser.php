<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsUser extends LocalContextsHubBase {
  protected $id;


  public function __construct($id) {
    parent::__construct();
    $this->id = $id;
  }

  public function getProjects() {
    $projects = [];
    if ($project_ids = $this->get("projects/users/{$this->id}")) {
      $project_uuids = array_column($project_ids, 'unique_id');
      foreach ($project_uuids as $uuid) {
        $projects[$uuid] = new LocalContextsProject($uuid);
      }
    }
    return $projects;
  }

}
