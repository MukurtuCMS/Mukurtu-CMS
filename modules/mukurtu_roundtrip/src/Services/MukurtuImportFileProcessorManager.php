<?php

namespace Drupal\mukurtu_roundtrip\Services;

use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorInterface;

class MukurtuImportFileProcessorManager {
  protected $processors;
  protected $sortedProcessors;

  public function addProcessor(MukurtuImportFileProcessorInterface $processor, $priority = 0) {
    $this->processors[$priority][] = $processor;
    $this->sortedProcessors = NULL;
    return $this;
  }

  protected function sortProcessors() {
    $sorted = [];
    krsort($this->processors);

    foreach ($this->processors as $processor) {
      $sorted = array_merge($sorted, $processor);
    }
    return $sorted;
  }

  public function getProcessors($file) {
    $valid_processors = [];
    if ($this->sortedProcessors === NULL) {
      $this->sortedProcessors = $this->sortProcessors();
    }

    foreach ($this->sortedProcessors as $processor) {
      if ($processor->supportsFile($file)) {
        $valid_processors[$processor->id()] = $processor->getName();
      }
    }

    return $valid_processors;
  }

}
