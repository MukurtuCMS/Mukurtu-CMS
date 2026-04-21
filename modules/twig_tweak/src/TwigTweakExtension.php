<?php

namespace Drupal\twig_tweak;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Markup as TwigMarkup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension with some useful functions and filters.
 *
 * The extension consumes quite a lot of dependencies. Most of them are not used
 * on each page request. For performance reasons services are wrapped in static
 * callbacks.
 */
class TwigTweakExtension extends AbstractExtension {

  /**
   * The module handler to invoke alter hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager to invoke alter hooks.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs the TwigTweakExtension object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager) {
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $context_options = ['needs_context' => TRUE];
    $all_options = ['needs_environment' => TRUE, 'needs_context' => TRUE];

    $functions = [
      new TwigFunction('drupal_view', 'views_embed_view'),
      new TwigFunction('drupal_view_result', 'views_get_view_result'),
      new TwigFunction('drupal_block', [self::class, 'drupalBlock']),
      new TwigFunction('drupal_region', [self::class, 'drupalRegion']),
      new TwigFunction('drupal_entity', [self::class, 'drupalEntity']),
      new TwigFunction('drupal_entity_form', [self::class, 'drupalEntityForm']),
      new TwigFunction('drupal_field', [self::class, 'drupalField']),
      new TwigFunction('drupal_menu', [self::class, 'drupalMenu']),
      new TwigFunction('drupal_form', [self::class, 'drupalForm']),
      new TwigFunction('drupal_image', [self::class, 'drupalImage']),
      new TwigFunction('drupal_token', [self::class, 'drupalToken']),
      new TwigFunction('drupal_config', [self::class, 'drupalConfig']),
      new TwigFunction('drupal_dump', [self::class, 'drupalDump'], $context_options),
      new TwigFunction('dd', [self::class, 'drupalDump'], $context_options),
      new TwigFunction('drupal_title', [self::class, 'drupalTitle']),
      new TwigFunction('drupal_url', [self::class, 'drupalUrl']),
      new TwigFunction('drupal_link', [self::class, 'drupalLink']),
      new TwigFunction('drupal_messages', function (): array {
        return ['#type' => 'status_messages'];
      }),
      new TwigFunction('drupal_breadcrumb', [self::class, 'drupalBreadcrumb']),
      new TwigFunction('drupal_breakpoint', [self::class, 'drupalBreakpoint'], $all_options),
      // @phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
      new TwigFunction('drupal_contextual_links', [self::class, 'drupalContextualLinks']),
    ];

    $this->moduleHandler->alter('twig_tweak_functions', $functions);
    $this->themeManager->alter('twig_tweak_functions', $functions);

    return $functions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {

    $filters = [
      new TwigFilter('token_replace', [self::class, 'tokenReplaceFilter']),
      new TwigFilter('preg_replace', [self::class, 'pregReplaceFilter']),
      new TwigFilter('image_style', [self::class, 'imageStyleFilter']),
      new TwigFilter('transliterate', [self::class, 'transliterateFilter']),
      new TwigFilter('check_markup', 'check_markup'),
      new TwigFilter('format_size', [ByteSizeMarkup::class, 'create']),
      new TwigFilter('truncate', [Unicode::class, 'truncate']),
      new TwigFilter('view', [self::class, 'viewFilter']),
      new TwigFilter('with', [self::class, 'withFilter']),
      new TwigFilter('data_uri', [self::class, 'dataUriFilter']),
      new TwigFilter('children', [self::class, 'childrenFilter']),
      new TwigFilter('file_uri', [self::class, 'fileUriFilter']),
      new TwigFilter('file_url', [self::class, 'fileUrlFilter']),
      new TwigFilter('entity_url', [self::class, 'entityUrl']),
      new TwigFilter('entity_link', [self::class, 'entityLink']),
      new TwigFilter('translation', [self::class, 'entityTranslation']),
      new TwigFilter('cache_metadata', [self::class, 'CacheMetadata']),
    ];

    if (Settings::get('twig_tweak_enable_php_filter')) {
      $filters[] = new TwigFilter('php', [self::class, 'phpFilter'], ['needs_context' => TRUE]);
    }

    $this->moduleHandler->alter('twig_tweak_filters', $filters);
    $this->themeManager->alter('twig_tweak_filters', $filters);

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getTests(): array {
    $tests = [];

    $this->moduleHandler->alter('twig_tweak_tests', $tests);
    $this->themeManager->alter('twig_tweak_tests', $tests);

    return $tests;
  }

  /**
   * Builds the render array for a block.
   */
  public static function drupalBlock(string $id, array $configuration = [], bool $wrapper = TRUE): array {
    return \Drupal::service('twig_tweak.block_view_builder')->build($id, $configuration, $wrapper);
  }

  /**
   * Builds the render array of a given region.
   */
  public static function drupalRegion(string $region, ?string $theme = NULL): array {
    return \Drupal::service('twig_tweak.region_view_builder')->build($region, $theme);
  }

  /**
   * Returns the render array to represent an entity.
   */
  public static function drupalEntity(string $entity_type, string $selector, string $view_mode = 'full', ?string $langcode = NULL, bool $check_access = TRUE): array {

    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    if (Uuid::isValid($selector)) {
      $entities = $storage->loadByProperties(['uuid' => $selector]);
      $entity = reset($entities);
    }
    // Fall back to entity ID.
    else {
      $entity = $storage->load($selector);
    }

    if ($entity) {
      return \Drupal::service('twig_tweak.entity_view_builder')
        ->build($entity, $view_mode, $langcode, $check_access);
    }

    return [];
  }

  /**
   * Gets the built and processed entity form for the given entity type.
   */
  public static function drupalEntityForm(string $entity_type, ?string $id = NULL, string $form_mode = 'default', array $values = [], bool $check_access = TRUE): array {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $id ?
      \Drupal::service('entity.repository')->getActive($entity_type, $id) : $entity_storage->create($values);
    if ($entity) {
      return \Drupal::service('twig_tweak.entity_form_view_builder')
        ->build($entity, $form_mode, $check_access);
    }
    return [];
  }

  /**
   * Returns the render array for a single entity field.
   */
  public static function drupalField(string $field_name, string $entity_type, string $id, $view_mode = 'full', ?string $langcode = NULL, bool $check_access = TRUE): array {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
    if ($entity) {
      return \Drupal::service('twig_tweak.field_view_builder')
        ->build($entity, $field_name, $view_mode, $langcode, $check_access);
    }
    return [];
  }

  /**
   * Returns the render array for Drupal menu.
   */
  public static function drupalMenu(string $menu_name, int $level = 1, int $depth = 0, bool $expand = FALSE): array {
    return \Drupal::service('twig_tweak.menu_view_builder')->build($menu_name, $level, $depth, $expand);
  }

  /**
   * Builds and processes a form for a given form ID.
   *
   * @param string $form_id
   *   The form ID.
   * @param mixed $args
   *   Additional arguments are passed to form constructor.
   *
   * @return array
   *   A render array to represent the form.
   */
  public static function drupalForm(string $form_id, ...$args): array {
    $callback = [\Drupal::formBuilder(), 'getForm'];
    return call_user_func_array($callback, func_get_args());
  }

  /**
   * Builds an image.
   */
  public static function drupalImage(string $selector, ?string $style = NULL, array $attributes = [], bool $responsive = FALSE, bool $check_access = TRUE): array {

    // Determine selector type by its value.
    if (preg_match('/^\d+$/', $selector)) {
      $selector_type = 'fid';
    }
    elseif (Uuid::isValid($selector)) {
      $selector_type = 'uuid';
    }
    else {
      $selector_type = 'uri';
    }

    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties([$selector_type => $selector]);

    if (count($files) == 0) {
      return [];
    }

    // To avoid ambiguity order by fid.
    ksort($files);

    $file = reset($files);
    return \Drupal::service('twig_tweak.image_view_builder')->build($file, $style, $attributes, $responsive, $check_access);
  }

  /**
   * Replaces a given token with appropriate value.
   *
   * @param string $token
   *   A replaceable token.
   * @param array $data
   *   (optional) An array of keyed objects. For simple replacement scenarios
   *   'node', 'user', and others are common keys, with an accompanying node or
   *   user object being the value. Some token types, like 'site', do not
   *   require any explicit information from $data and can be replaced even if
   *   it is empty.
   * @param array $options
   *   (optional) A keyed array of settings and flags to control the token
   *   replacement process.
   *
   * @return string
   *   The token value.
   *
   * @see \Drupal\Core\Utility\Token::replace()
   */
  public static function drupalToken(string $token, array $data = [], array $options = []): string {
    return \Drupal::token()->replace("[$token]", $data, $options);
  }

  /**
   * Retrieves data from a given configuration object.
   *
   * @param string $name
   *   The name of the configuration object to construct.
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *
   * @return mixed
   *   The data that was requested.
   */
  public static function drupalConfig(string $name, string $key) {
    return \Drupal::config($name)->get($key);
  }

  /**
   * Dumps information about variables.
   *
   * @param array $context
   *   Variables from the Twig template.
   * @param mixed $variable
   *   (optional) The variable to dump.
   */
  public static function drupalDump(array $context, $variable = NULL): void {
    $var_dumper = '\Symfony\Component\VarDumper\VarDumper';
    if (class_exists($var_dumper)) {
      call_user_func($var_dumper . '::dump', func_num_args() == 1 ? $context : $variable);
    }
    else {
      trigger_error('Could not dump the variable because symfony/var-dumper component is not installed.', E_USER_WARNING);
    }
  }

  /**
   * Returns a title for the current route.
   *
   * @todo Test it with NullRouteMatch
   */
  public static function drupalTitle(): array {
    $title = NULL;
    if ($route = \Drupal::routeMatch()->getRouteObject()) {
      $title = \Drupal::service('title_resolver')->getTitle(\Drupal::request(), $route);
    }
    $build['#markup'] = is_array($title) ?
      \Drupal::service('renderer')->render($title) : $title;
    $build['#cache']['contexts'] = ['url'];
    return $build;
  }

  /**
   * Generates a URL from an internal or external path.
   *
   * @param string $user_input
   *   User input for a link or path.
   * @param array $options
   *   (optional) An array of options.
   * @param bool $check_access
   *   (optional) Indicates that access check is required.
   *
   * @return \Drupal\Core\Url|null
   *   A new Url object or null if the URL is not accessible.
   *
   * @see \Drupal\Core\Url::fromUserInput()
   */
  public static function drupalUrl(string $user_input, array $options = [], bool $check_access = FALSE): ?Url {
    if (isset($options['langcode'])) {
      $language_manager = \Drupal::languageManager();
      if ($language = $language_manager->getLanguage($options['langcode'])) {
        $options['language'] = $language;
      }
    }
    if (UrlHelper::isExternal($user_input)) {
      return Url::fromUri($user_input, $options);
    }
    if (!in_array($user_input[0], ['/', '#', '?'])) {
      $user_input = '/' . $user_input;
    }
    $url = Url::fromUserInput($user_input, $options);
    return (!$check_access || $url->access()) ? $url : NULL;
  }

  /**
   * Generates a link from an internal path.
   *
   * @param string|\Twig\Markup $text
   *   The text to be used for the link.
   * @param string $user_input
   *   User input for a link or path.
   * @param array $options
   *   (optional) An array of options.
   * @param bool $check_access
   *   (optional) Indicates that access check is required.
   *
   * @return \Drupal\Core\Link|null
   *   A new Link object or null of the URL is not accessible.
   *
   * @see \Drupal\Core\Link::fromTextAndUrl()
   */
  public static function drupalLink($text, string $user_input, array $options = [], bool $check_access = FALSE): ?Link {
    $url = self::drupalUrl($user_input, $options, $check_access);
    if ($url) {
      // The text has been processed by twig already, convert it to a safe
      // object for the render system.
      // @see \Drupal\Core\Template\TwigExtension::getLink()
      if ($text instanceof TwigMarkup) {
        $text = Markup::create($text);
      }
      return Link::fromTextAndUrl($text, $url);
    }
    return NULL;
  }

  /**
   * Builds the breadcrumb.
   */
  public static function drupalBreadcrumb(): array {
    return \Drupal::service('breadcrumb')
      ->build(\Drupal::routeMatch())
      ->toRenderable();
  }

  /**
   * Builds contextual links.
   *
   * @param string $id
   *   A serialized representation of a #contextual_links property value array.
   *
   * @return array
   *   A renderable array representing contextual links.
   *
   * @see https://www.drupal.org/node/2133283
   */
  public static function drupalContextualLinks(string $id): array {
    $build['#cache']['contexts'] = ['user.permissions'];
    if (\Drupal::currentUser()->hasPermission('access contextual links')) {
      $build['#type'] = 'contextual_links_placeholder';
      $build['#id'] = $id;
    }
    return $build;
  }

  /**
   * Emits a breakpoint to the debug client.
   *
   * @param \Twig\Environment $environment
   *   The Twig environment instance.
   * @param array $context
   *   Variables from the current Twig template.
   */
  public static function drupalBreakpoint(Environment $environment, array $context): void {
    if (function_exists('xdebug_break')) {
      xdebug_break();
    }
    else {
      trigger_error('Could not make a break because xdebug is not available.', E_USER_WARNING);
    }
  }

  /**
   * Replaces all tokens in a given string with appropriate values.
   *
   * @param string $text
   *   An HTML string containing replaceable tokens.
   * @param array $data
   *   (optional) An array of keyed objects. For simple replacement scenarios
   *   'node', 'user', and others are common keys, with an accompanying node or
   *   user object being the value. Some token types, like 'site', do not
   *   require any explicit information from $data and can be replaced even if
   *   it is empty.
   * @param array $options
   *   (optional) A keyed array of settings and flags to control the token
   *   replacement process.
   *
   * @return string
   *   The entered HTML text with tokens replaced.
   */
  public static function tokenReplaceFilter(string $text, array $data = [], array $options = []): string {
    return \Drupal::token()->replace($text, $data, $options);
  }

  /**
   * Performs a regular expression search and replace.
   *
   * @param string $text
   *   The text to search and replace.
   * @param string $pattern
   *   The pattern to search for.
   * @param string $replacement
   *   The string to replace.
   *
   * @return string
   *   The new text if matches are found, otherwise unchanged text.
   */
  public static function pregReplaceFilter(string $text, string $pattern, string $replacement): string {
    return preg_replace($pattern, $replacement, $text);
  }

  /**
   * Returns the URL of this image derivative for an original image path or URI.
   *
   * @param string|null $path
   *   The path or URI to the original image.
   * @param string $style
   *   The image style.
   *
   * @return string|null
   *   The absolute URL where a style image can be downloaded, suitable for use
   *   in an <img> tag. Requesting the URL will cause the image to be created.
   */
  public static function imageStyleFilter(?string $path, string $style): ?string {

    if (!$path) {
      trigger_error('Image path is empty.');
      return NULL;
    }

    if (!$image_style = ImageStyle::load($style)) {
      trigger_error(sprintf('Could not load image style %s.', $style));
      return NULL;
    }

    if (!$image_style->supportsUri($path)) {
      trigger_error(sprintf('Could not apply image style %s.', $style));
      return NULL;
    }

    return \Drupal::service('file_url_generator')
      ->transformRelative($image_style->buildUrl($path));
  }

  /**
   * Transliterates text from Unicode to US-ASCII.
   *
   * @param string $text
   *   The $text to transliterate.
   * @param string $langcode
   *   (optional) The language code of the language the string is in. Defaults
   *   to 'en' if not provided. Warning: this can be unfiltered user input.
   * @param string $unknown_character
   *   (optional) The character to substitute for characters in $string without
   *   transliterated equivalents. Defaults to '?'.
   * @param int $max_length
   *   (optional) If provided, return at most this many characters, ensuring
   *   that the transliteration does not split in the middle of an input
   *   character's transliteration.
   *
   * @return string
   *   $string with non-US-ASCII characters transliterated to US-ASCII
   *   characters, and unknown characters replaced with $unknown_character.
   */
  public static function transliterateFilter(string $text, string $langcode = 'en', string $unknown_character = '?', ?int $max_length = NULL) {
    return \Drupal::transliteration()->transliterate($text, $langcode, $unknown_character, $max_length);
  }

  /**
   * Returns a render array for entity, field list or field item.
   *
   * @param object|null $object
   *   The object to build a render array from.
   * @param string|array $view_mode
   *   Can be either the name of a view mode, or an array of display settings.
   * @param string $langcode
   *   (optional) For which language the entity should be rendered, defaults to
   *   the current content language.
   * @param bool $check_access
   *   (optional) Indicates that access check for an entity is required.
   *
   * @return array
   *   A render array to represent the object.
   */
  public static function viewFilter(?object $object, $view_mode = 'default', ?string $langcode = NULL, bool $check_access = TRUE): array {
    $build = [];
    if ($object instanceof FieldItemListInterface || $object instanceof FieldItemInterface) {
      $build = $object->view($view_mode);
      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $parent */
      if ($parent = $object->getParent()) {
        CacheableMetadata::createFromRenderArray($build)
          ->addCacheableDependency($parent->getEntity())
          ->applyTo($build);
      }
    }
    elseif ($object instanceof EntityInterface) {
      $build = \Drupal::service('twig_tweak.entity_view_builder')->build($object, $view_mode, $langcode, $check_access);
    }
    return $build;
  }

  /**
   * Creates a data URI (RFC 2397).
   */
  public static function dataUriFilter(string $data, string $mime, array $parameters = []): string {
    $uri = 'data:' . $mime;
    foreach ($parameters as $key => $value) {
      $uri .= ';' . $key . '=' . rawurlencode($value);
    }
    $uri .= \str_starts_with($data, 'text/') ?
       ',' . rawurlencode($data) : ';base64,' . base64_encode($data);
    return $uri;
  }

  /**
   * Adds new element to the array.
   *
   * @param array $build
   *   The renderable array to add the child item.
   * @param mixed $key
   *   The key of the new element.
   * @param mixed $element
   *   The element to add.
   *
   * @return array
   *   The modified array.
   */
  public static function withFilter(array $build, $key, $element): array {
    if (is_array($key)) {
      NestedArray::setValue($build, $key, $element);
    }
    else {
      $build[$key] = $element;
    }
    return $build;
  }

  /**
   * Filters out the children of a render array, optionally sorted by weight.
   *
   * @param array $build
   *   The render array whose children are to be filtered.
   * @param bool $sort
   *   Boolean to indicate whether the children should be sorted by weight.
   *
   * @return array
   *   The element's children.
   */
  public static function childrenFilter(array $build, bool $sort = FALSE): array {
    $keys = Element::children($build, $sort);
    return array_intersect_key($build, array_flip($keys));
  }

  /**
   * Returns a URI to the file.
   *
   * @param object $input
   *   An object that contains the URI.
   *
   * @return string|null
   *   A URI that may be used to access the file.
   */
  public static function fileUriFilter($input): ?string {
    return \Drupal::service('twig_tweak.uri_extractor')->extractUri($input);
  }

  /**
   * Returns a URL path to the file.
   *
   * @param string|object $input
   *   Can be either file URI or an object that contains the URI.
   * @param bool $relative
   *   (optional) Whether the URL should be root-relative, defaults to true.
   *
   * @return string|null
   *   A URL that may be used to access the file.
   */
  public static function fileUrlFilter($input, bool $relative = TRUE): ?string {
    return \Drupal::service('twig_tweak.url_extractor')->extractUrl($input, $relative);
  }

  /**
   * Gets the URL object for the entity.
   *
   * @todo Remove this once Drupal allows `toUrl` method in the sandbox policy.
   *
   * @see https://www.drupal.org/node/2907810
   * @see \Drupal\Core\Entity\EntityInterface::toUrl()
   */
  public static function entityUrl(EntityInterface $entity, string $rel = 'canonical', array $options = []): Url {
    return $entity->toUrl($rel, $options);
  }

  /**
   * Gets the URL object for the entity.
   *
   * @todo Remove this once Drupal allows `toLink` method in the sandbox policy.
   *
   * @see https://www.drupal.org/node/2907810
   * @see \Drupal\Core\Entity\EntityInterface::toLink()
   */
  public static function entityLink(EntityInterface $entity, ?string $text = NULL, string $rel = 'canonical', array $options = []): Link {
    return $entity->toLink($text, $rel, $options);
  }

  /**
   * Returns the translation for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the translation from.
   * @param string $langcode
   *   (optional) For which language the translation should be looked for,
   *   defaults to the current language context.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The appropriate translation for the given language context.
   */
  public static function entityTranslation(EntityInterface $entity, ?string $langcode = NULL): EntityInterface {
    return \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
  }

  /**
   * Extracts cache metadata from object or render array.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|array $input
   *   The cacheable object or render array.
   *
   * @return array
   *   A render array with extracted cache metadata.
   */
  public static function cacheMetadata($input): array {
    return \Drupal::service('twig_tweak.cache_metadata_extractor')->extractCacheMetadata($input);
  }

  /**
   * Evaluates a string of PHP code.
   *
   * @param array $context
   *   Twig context.
   * @param string $code
   *   Valid PHP code to be evaluated.
   *
   * @return mixed
   *   The eval() result.
   */
  public static function phpFilter(array $context, string $code) {
    // Make Twig variables available in PHP code.
    // @cspell:disable-next-line
    extract($context, EXTR_SKIP);
    ob_start();
    // phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
    print eval($code);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }

}
