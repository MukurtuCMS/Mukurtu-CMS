<?php

namespace Drupal\mukurtu_roundtrip\ImportProcessor;

class MukurtuImportFileProcessorResult {
  protected $data;
  protected $entityClass;
  protected $format;
  protected $context;

  public function __construct($data, $class, $format, $context) {
    $this->data = $data;
    $this->entityClass = $class;
    $this->format = $format;
    $this->context = $context;
  }

  public function getData() {
    return $this->data;
  }

  public function getClass() {
    return $this->entityClass;
  }

  public function getFormat() {
    return $this->format;
  }

  public function getContext() {
    return $this->context;
  }
}
