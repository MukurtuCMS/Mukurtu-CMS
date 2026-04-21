<?php

namespace Drupal\features;

use Drupal\Core\Extension\Extension;

/**
 * Defines a value object for storing package related data.
 *
 * A package contains of a name, version number, containing config etc.
 */
class Package {

  /**
   * The package machine name.
   *
   * @var string
   */
  protected $machineName = '';

  /**
   * The package name.
   *
   * @var string
   */
  protected $name = '';

  /**
   * The package description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The package version.
   *
   * @var string
   * @todo This could be fetched from the extension object.
   */
  protected $version = '';

  /**
   * The package type.
   *
   * @var string
   * @todo This could be fetched from the extension object.
   */
  protected $coreVersionRequirement = FeaturesBundleInterface::CORE_VERSION_REQUIREMENT;

  /**
   * The package type.
   *
   * @var string
   * @todo This could be fetched from the extension object.
   */
  protected $type = 'module';

  /**
   * The variable.
   *
   * @var string[]
   */
  protected $themes = [];

  /**
   * The package bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * A list of configuration items excluded from the package.
   *
   * @var string[]
   */
  protected $excluded = [];

  /**
   * A list of configuration items required to be included in the package.
   *
   * @var string[]|bool
   */
  protected $required = FALSE;

  /**
   * The package info array.
   *
   * @var array
   */
  protected $info = [];

  /**
   * The package depenndencies.
   *
   * @var string[]
   */
  protected $dependencies = [];

  /**
   * The package status.
   *
   * @var int
   * @todo This could be fetched from the extension object.
   */
  protected $status;

  /**
   * The package state.
   *
   * @var int
   */
  protected $state;

  /**
   * The package directory.
   *
   * @var string
   * @todo This could be fetched from the extension object.
   */
  protected $directory;

  /**
   * Files included in the package.
   *
   * @var string[]
   */
  protected $files;

  /**
   * The extension.
   *
   * @var \Drupal\Core\Extension\Extension
   */
  protected $extension;

  /**
   * Configuration items included in the package.
   *
   * @var string[]
   */
  protected $config = [];

  /**
   * Original configuration items included in the package.
   *
   * @var string[]
   */
  protected $configOrig = [];

  /**
   * Creates a new Package instance.
   *
   * @param string $machine_name
   *   The machine name.
   * @param array $additional_properties
   *   (optional) Additional properties of the object.
   */
  public function __construct($machine_name, array $additional_properties = []) {
    $this->machineName = $machine_name;

    $properties = get_object_vars($this);
    foreach ($additional_properties as $property => $value) {
      if (!array_key_exists($property, $properties)) {
        throw new \InvalidArgumentException('Invalid property: ' . $property);
      }
      $this->{$property} = $value;
    }
  }

  /**
   * @return mixed
   */
  public function getMachineName() {
    return $this->machineName;
  }

  /**
   * Return TRUE if the machine_name already has the bundle prefix.
   *
   * @param string $machine_name
   * @param string $bundle_name
   *
   * @return bool
   */
  protected function inBundle($machine_name, $bundle_name) {
    return strpos($machine_name, $bundle_name . '_') === 0;
  }

  /**
   * Return the full name of the package by prefixing it with bundle as needed.
   *
   * NOTE: When possible, use the Bundle::getFullName method since it can
   * better handle cases where a bundle is a profile.
   *
   * @return string
   */
  public function getFullName() {
    if (empty($this->bundle) || $this->inBundle($this->machineName, $this->bundle)) {
      return $this->machineName;
    }
    else {
      return $this->bundle . '_' . $this->machineName;
    }
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @return string
   */
  public function getVersion() {
    return $this->version;
  }

  /**
   * @return int
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * @return string[]
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Append a new filename.
   *
   * @param string $config
   *
   * @return $this
   */
  public function appendConfig($config) {
    $this->config[] = $config;
    $this->config = array_unique($this->config);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function removeConfig($name) {
    $this->config = array_diff($this->config, [$name]);
    return $this;
  }

  /**
   * @return string
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   * @return string[]
   */
  public function getExcluded() {
    return $this->excluded;
  }

  /**
   * @return string[]|bool
   */
  public function getRequired() {
    return $this->required;
  }

  /**
   * @return bool
   */
  public function getRequiredAll() {
    // Mark all as required if the package is not yet exported.
    if ($this->getStatus() === FeaturesManagerInterface::STATUS_NO_EXPORT) {
      return TRUE;
    }

    // Mark all as required if required is TRUE.
    if (is_bool($this->required)) {
      return $this->required;
    }

    // Mark all as required if required contains all the exported config.
    $config_orig = $this->getConfigOrig();
    $diff = array_diff($config_orig, $this->required);
    return empty($diff);
  }

  /**
   * @return string[]
   */
  public function getConfigOrig() {
    return $this->configOrig;
  }

  /**
   * @return string
   */
  public function getCoreVersionRequirement() {
    return $this->coreVersionRequirement;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @return \string[]
   */
  public function getThemes() {
    return $this->themes;
  }

  /**
   * @return array
   */
  public function getInfo() {
    return $this->info;
  }

  /**
   * @return mixed
   */
  public function getState() {
    return $this->state;
  }

  /**
   * @return string
   */
  public function getDirectory() {
    return $this->directory;
  }

  /**
   * @return mixed
   */
  public function getFiles() {
    return $this->files;
  }

  /**
   * @return \Drupal\Core\Extension\Extension
   */
  public function getExtension() {
    return $this->extension;
  }

  /**
   * {@inheritDoc}
   */
  public function getDependencies() {
    return $this->dependencies;
  }

  /**
   * {@inheritDoc}
   */
  public function removeDependency($name) {
    $this->dependencies = array_diff($this->dependencies, [$name]);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getDependencyInfo() {
    return isset($this->info['dependencies']) ? $this->info['dependencies'] : [];
  }

  /**
   * Returns the features info.
   *
   * @return array
   */
  public function getFeaturesInfo() {
    $info = [];
    if (!empty($this->bundle)) {
      $info['bundle'] = $this->bundle;
    }
    if (!empty($this->excluded)) {
      $info['excluded'] = $this->excluded;
    }
    if ($this->required !== FALSE) {
      $info['required'] = $this->required;
    }
    return $info;
  }

  /**
   * Sets a new machine name.
   *
   * @param string $machine_name
   *   The machine name.
   *
   * @return $this
   */
  public function setMachineName($machine_name) {
    $this->machineName = $machine_name;
    return $this;
  }

  /**
   * @param string $name
   *
   * @return $this
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  /**
   * @param string $description
   *
   * @return $this
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * @param string $version
   *
   * @return $this
   */
  public function setVersion($version) {
    $this->version = $version;
    return $this;
  }

  /**
   * @param string $bundle
   *
   * @return $this
   */
  public function setBundle($bundle) {
    $this->bundle = $bundle;
    return $this;
  }

  /**
   * @param array $info
   *
   * @return $this
   */
  public function setInfo(array $info) {
    $this->info = $info;
    return $this;
  }

  /**
   * @param array|TRUE $features_info
   *
   * @return $this
   */
  public function setFeaturesInfo($features_info) {
    if (isset($features_info['bundle'])) {
      $this->setBundle($features_info['bundle']);
    }
    $this->setRequired(isset($features_info['required']) ? $features_info['required'] : FALSE);
    $this->setExcluded(isset($features_info['excluded']) ? $features_info['excluded'] : []);

    return $this;
  }

  /**
   * Sets the dependencies of a package.
   *
   * Ensures that dependencies are unique and do not include the package itself.
   *
   * @param \string[] $dependencies
   *
   * @return $this
   */
  public function setDependencies(array $dependencies) {
    $dependencies = array_unique($dependencies);
    // Package shouldn't be dependent on itself.
    $full_name = $this->getFullName();
    if (in_array($full_name, $dependencies)) {
      unset($dependencies[array_search($full_name, $dependencies)]);
    }
    sort($dependencies);
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * @param string $dependency
   *
   * @return $this
   */
  public function appendDependency($dependency) {
    $dependencies = $this->getDependencies();
    array_push($dependencies, $dependency);
    return $this->setDependencies($dependencies);
  }

  /**
   * @param int $status
   *
   * @return $this
   */
  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * @param \string[] $config
   *
   * @return $this
   */
  public function setConfig(array $config) {
    $this->config = $config;
    return $this;
  }

  /**
   * @param bool $excluded
   */
  public function setExcluded($excluded) {
    $this->excluded = $excluded;
  }

  /**
   * @param bool $required
   */
  public function setRequired($required) {
    $this->required = $required;
  }

  /**
   * @param string $coreVersionRequirement
   */
  public function setCoreVersionRequirement($coreVersionRequirement) {
    $this->coreVersionRequirement = $coreVersionRequirement;
  }

  /**
   * @param string $type
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * @param \string[] $themes
   */
  public function setThemes($themes) {
    $this->themes = $themes;
  }

  /**
   * @param int $state
   */
  public function setState($state) {
    $this->state = $state;
  }

  /**
   * @param string $directory
   */
  public function setDirectory($directory) {
    $this->directory = $directory;
  }

  /**
   * @param \string[] $files
   */
  public function setFiles(array $files) {
    $this->files = $files;
  }

  /**
   * @param array $file_array
   *
   * @return $this
   */
  public function appendFile(array $file_array, $key = NULL) {
    if (!isset($key)) {
      $this->files[] = $file_array;
    }
    else {
      $this->files[$key] = $file_array;
    }
    return $this;
  }

  /**
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension.
   */
  public function setExtension(Extension $extension) {
    $this->extension = $extension;
  }

  /**
   * @param \string[] $configOrig
   */
  public function setConfigOrig(array $configOrig) {
    $this->configOrig = $configOrig;
  }

}
