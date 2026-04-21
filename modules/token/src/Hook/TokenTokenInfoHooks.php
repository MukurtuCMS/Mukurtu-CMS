<?php

namespace Drupal\token\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Token info hook implementations for token.
 */
final class TokenTokenInfoHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected readonly TokenEntityMapperInterface $tokenEntityMapper,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly TimeInterface $time,
    protected readonly FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
  }

  /**
   * Implements hook_token_info_alter().
   */
  #[Hook('token_info_alter')]
  public function tokenInfoAlter(&$info) {
    // Force 'date' type tokens to require input and add a 'current-date' type.
    // @todo Remove when http://drupal.org/node/943028 is fixed.
    $info['types']['date']['needs-data'] = 'date';
    $info['types']['current-date'] = [
      'name' => $this->t('Current date'),
      'description' => $this->t('Tokens related to the current date and time.'),
      'type' => 'date',
    ];
    // Add a 'dynamic' key to any tokens that have chained but dynamic tokens.
    $info['tokens']['date']['custom']['dynamic'] = TRUE;
    // Remove deprecated tokens from being listed.
    unset($info['tokens']['node']['tnid']);
    unset($info['tokens']['node']['type']);
    unset($info['tokens']['node']['type-name']);
    // Support 'url' type tokens for core tokens.
    if (isset($info['tokens']['comment']['url']) && $this->moduleHandler->moduleExists('comment')) {
      $info['tokens']['comment']['url']['type'] = 'url';
    }
    if (isset($info['tokens']['node']['url']) && $this->moduleHandler->moduleExists('node')) {
      $info['tokens']['node']['url']['type'] = 'url';
    }
    if (isset($info['tokens']['term']['url']) && $this->moduleHandler->moduleExists('taxonomy')) {
      $info['tokens']['term']['url']['type'] = 'url';
    }
    $info['tokens']['user']['url']['type'] = 'url';
    // Add [token:url] tokens for any URI-able entities.
    $entities = $this->entityTypeManager->getDefinitions();
    foreach ($entities as $entity_info) {
      // Do not generate tokens if the entity doesn't define a token type or is
      // not a content entity.
      if (!$entity_info->get('token_type') || !$entity_info instanceof ContentEntityTypeInterface) {
        continue;
      }
      $token_type = $entity_info->get('token_type');
      if (!isset($info['types'][$token_type]) || !isset($info['tokens'][$token_type])) {
        // Define tokens for entity type's without their own integration.
        $info['types'][$entity_info->id()] = [
          'name' => $entity_info->getLabel(),
          'needs-data' => $entity_info->id(),
          'module' => 'token',
        ];
      }
      // Add [entity:url] tokens if they do not already exist.
      // @todo Support entity:label
      if (!isset($info['tokens'][$token_type]['url'])) {
        $info['tokens'][$token_type]['url'] = [
          'name' => $this->t('URL'),
          'description' => $this->t('The URL of the @entity.', [
            '@entity' => mb_strtolower($entity_info->getLabel()),
          ]),
          'module' => 'token',
          'type' => 'url',
        ];
      }
      // Add [entity:language] tokens if they do not already exist.
      if (!isset($info['tokens'][$token_type]['language'])) {
        $info['tokens'][$token_type]['language'] = [
          'name' => $this->t('Language'),
          'description' => $this->t('The language of the @entity.', [
            '@entity' => mb_strtolower($entity_info->getLabel()),
          ]),
          'module' => 'token',
          'type' => 'language',
        ];
      }
      // Add [entity:original] tokens if they do not already exist.
      if (!isset($info['tokens'][$token_type]['original'])) {
        $info['tokens'][$token_type]['original'] = [
          'name' => $this->t('Original @entity', [
            '@entity' => mb_strtolower($entity_info->getLabel()),
          ]),
          'description' => $this->t('The original @entity data if the @entity is being updated or saved.', [
            '@entity' => mb_strtolower($entity_info->getLabel()),
          ]),
          'module' => 'token',
          'type' => $token_type,
        ];
      }
    }
    // Add support for custom date formats.
    // @todo Remove when http://drupal.org/node/1173706 is fixed.
    $date_format_types = $this->entityTypeManager->getStorage('date_format')->loadMultiple();
    foreach ($date_format_types as $date_format_type => $date_format_type_info) {
      /** @var \Drupal\Core\Datetime\Entity\DateFormat $date_format_type_info */
      if (!isset($info['tokens']['date'][$date_format_type])) {
        $info['tokens']['date'][$date_format_type] = [
          'name' => Html::escape($date_format_type_info->label()),
          'description' => $this->t("A date in '@type' format. (%date)", [
            '@type' => $date_format_type,
            '%date' => $this->dateFormatter->format($this->time->getRequestTime(), $date_format_type),
          ]),
          'module' => 'token',
        ];
      }
    }
    // Call proxy implementations.
    if ($this->moduleHandler->moduleExists('field')) {
      $this->fieldTokenInfoAlter($info);
    }
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    $info = [];
    // Call proxy implementations.
    if ($this->moduleHandler->moduleExists('book')) {
      $info += $this->bookTokenInfo();
    }
    if ($this->moduleHandler->moduleExists('menu_ui')) {
      $info = array_merge_recursive($info, $this->menuUiTokenInfo());
    }
    // Node tokens.
    if ($this->moduleHandler->moduleExists('node')) {
      $info['tokens']['node']['source'] = [
        'name' => $this->t('Translation source node'),
        'description' => $this->t("The source node for this current node's translation set."),
        'type' => 'node',
      ];
      $info['tokens']['node']['log'] = [
        'name' => $this->t('Revision log message'),
        'description' => $this->t('The explanation of the most recent changes made to the node.'),
      ];
      $info['tokens']['node']['content-type'] = [
        'name' => $this->t('Content type'),
        'description' => $this->t('The content type of the node.'),
        'type' => 'content-type',
      ];
      // Content type tokens.
      $info['types']['content-type'] = [
        'name' => $this->t('Content types'),
        'description' => $this->t('Tokens related to content types.'),
        'needs-data' => 'node_type',
      ];
      $info['tokens']['content-type']['name'] = [
        'name' => $this->t('Name'),
        'description' => $this->t('The name of the content type.'),
      ];
      $info['tokens']['content-type']['machine-name'] = [
        'name' => $this->t('Machine-readable name'),
        'description' => $this->t('The unique machine-readable name of the content type.'),
      ];
      $info['tokens']['content-type']['description'] = [
        'name' => $this->t('Description'),
        'description' => $this->t('The optional description of the content type.'),
      ];
      $info['tokens']['content-type']['node-count'] = [
        'name' => $this->t('Node count'),
        'description' => $this->t('The number of nodes belonging to the content type.'),
      ];
      $info['tokens']['content-type']['edit-url'] = [
        'name' => $this->t('Edit URL'),
        'description' => $this->t("The URL of the content type's edit page."),
      ];
    }
    // Taxonomy term and vocabulary tokens.
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $info['tokens']['term']['source'] = [
        'name' => $this->t('Translation source term'),
        'description' => $this->t("The source term for this current term's translation set."),
        'type' => 'term',
      ];
      $info['tokens']['term']['edit-url'] = [
        'name' => $this->t('Edit URL'),
        'description' => $this->t("The URL of the taxonomy term's edit page."),
      ];
      $info['tokens']['term']['parents'] = [
        'name' => $this->t('Parents'),
        'description' => $this->t("An array of all the term's parents, starting with the root."),
        'type' => 'array',
      ];
      $info['tokens']['term']['root'] = [
        'name' => $this->t('Root term'),
        'description' => $this->t("The root term of the taxonomy term."),
        'type' => 'term',
      ];
      $info['tokens']['vocabulary']['machine-name'] = [
        'name' => $this->t('Machine-readable name'),
        'description' => $this->t('The unique machine-readable name of the vocabulary.'),
      ];
      $info['tokens']['vocabulary']['edit-url'] = [
        'name' => $this->t('Edit URL'),
        'description' => $this->t("The URL of the vocabulary's edit page."),
      ];
    }
    // File tokens.
    $info['tokens']['file']['basename'] = [
      'name' => $this->t('Base name'),
      'description' => $this->t('The base name of the file.'),
    ];
    $info['tokens']['file']['extension'] = [
      'name' => $this->t('Extension'),
      'description' => $this->t('The extension of the file.'),
    ];
    $info['tokens']['file']['size-raw'] = [
      'name' => $this->t('File byte size'),
      'description' => $this->t('The size of the file, in bytes.'),
    ];
    // User tokens.
    // Add information on the restricted user tokens.
    $info['tokens']['user']['cancel-url'] = [
      'name' => $this->t('Account cancellation URL'),
      'description' => $this->t('The URL of the confirm delete page for the user account.'),
      'restricted' => TRUE,
    ];
    $info['tokens']['user']['one-time-login-url'] = [
      'name' => $this->t('One-time login URL'),
      'description' => $this->t('The URL of the one-time login page for the user account.'),
      'restricted' => TRUE,
    ];
    $info['tokens']['user']['roles'] = [
      'name' => $this->t('Roles'),
      'description' => $this->t('The user roles associated with the user account.'),
      'type' => 'array',
    ];
    // Current user tokens.
    $info['tokens']['current-user']['ip-address'] = [
      'name' => $this->t('IP address'),
      'description' => $this->t('The IP address of the current user.'),
    ];
    // Menu link tokens (work regardless if menu module is enabled or not).
    $info['types']['menu-link'] = [
      'name' => $this->t('Menu links'),
      'description' => $this->t('Tokens related to menu links.'),
      'needs-data' => 'menu-link',
    ];
    $info['tokens']['menu-link']['mlid'] = [
      'name' => $this->t('Link ID'),
      'description' => $this->t('The unique ID of the menu link.'),
    ];
    $info['tokens']['menu-link']['title'] = [
      'name' => $this->t('Title'),
      'description' => $this->t('The title of the menu link.'),
    ];
    $info['tokens']['menu-link']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the menu link.'),
      'type' => 'url',
    ];
    $info['tokens']['menu-link']['parent'] = [
      'name' => $this->t('Parent'),
      'description' => $this->t("The menu link's parent."),
      'type' => 'menu-link',
    ];
    $info['tokens']['menu-link']['parents'] = [
      'name' => $this->t('Parents'),
      'description' => $this->t("An array of all the menu link's parents, starting with the root."),
      'type' => 'array',
    ];
    $info['tokens']['menu-link']['root'] = [
      'name' => $this->t('Root'),
      'description' => $this->t("The menu link's root."),
      'type' => 'menu-link',
    ];
    // Language tokens.
    $info['types']['language'] = [
      'name' => $this->t('Language'),
      'description' => $this->t('Tokens related to site language.'),
    ];
    $info['tokens']['language']['name'] = [
      'name' => $this->t('Language name'),
      'description' => $this->t('The language name.'),
    ];
    $info['tokens']['language']['langcode'] = [
      'name' => $this->t('Language code'),
      'description' => $this->t('The language code.'),
    ];
    $info['tokens']['language']['direction'] = [
      'name' => $this->t('Direction'),
      'description' => $this->t('Whether the language is written left-to-right (ltr) or right-to-left (rtl).'),
    ];
    $info['tokens']['language']['domain'] = [
      'name' => $this->t('Domain'),
      'description' => $this->t('The domain name to use for the language.'),
    ];
    $info['tokens']['language']['prefix'] = [
      'name' => $this->t('Path prefix'),
      'description' => $this->t('Path prefix for URLs in the language.'),
    ];
    // Current page tokens.
    $info['types']['current-page'] = [
      'name' => $this->t('Current page'),
      'description' => $this->t('Tokens related to the current page request.'),
    ];
    $info['tokens']['current-page']['title'] = [
      'name' => $this->t('Title'),
      'description' => $this->t('The title of the current page.'),
    ];
    $info['tokens']['current-page']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the current page.'),
      'type' => 'url',
    ];
    $info['tokens']['current-page']['page-number'] = [
      'name' => $this->t('Page number'),
      'description' => $this->t('The page number of the current page when viewing paged lists.'),
    ];
    $info['tokens']['current-page']['query'] = [
      'name' => $this->t('Query string value'),
      'description' => $this->t('The value of a specific query string field of the current page.'),
      'dynamic' => TRUE,
    ];
    $info['tokens']['current-page']['interface-language'] = [
      'name' => $this->t('Interface language'),
      'description' => $this->t('The active user interface language.'),
      'type' => 'language',
    ];
    $info['tokens']['current-page']['content-language'] = [
      'name' => $this->t('Content language'),
      'description' => $this->t('The active content language.'),
      'type' => 'language',
    ];
    // URL tokens.
    $info['types']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('Tokens related to URLs.'),
      'needs-data' => 'path',
    ];
    $info['tokens']['url']['path'] = [
      'name' => $this->t('Path'),
      'description' => $this->t('The path component of the URL.'),
    ];
    $info['tokens']['url']['relative'] = [
      'name' => $this->t('Relative URL'),
      'description' => $this->t('The relative URL.'),
    ];
    $info['tokens']['url']['absolute'] = [
      'name' => $this->t('Absolute URL'),
      'description' => $this->t('The absolute URL.'),
    ];
    $info['tokens']['url']['brief'] = [
      'name' => $this->t('Brief URL'),
      'description' => $this->t('The URL without the protocol and trailing backslash.'),
    ];
    $info['tokens']['url']['unaliased'] = [
      'name' => $this->t('Unaliased URL'),
      'description' => $this->t('The unaliased URL.'),
      'type' => 'url',
    ];
    $info['tokens']['url']['args'] = [
      'name' => $this->t('Arguments'),
      'description' => $this->t("The specific argument of the current page (e.g. 'arg:1' on the page 'node/1' returns '1')."),
      'type' => 'array',
    ];
    // Array tokens.
    $info['types']['array'] = [
      'name' => $this->t('Array'),
      'description' => $this->t('Tokens related to arrays of strings.'),
      'needs-data' => 'array',
      'nested' => TRUE,
    ];
    $info['tokens']['array']['first'] = [
      'name' => $this->t('First'),
      'description' => $this->t('The first element of the array.'),
    ];
    $info['tokens']['array']['last'] = [
      'name' => $this->t('Last'),
      'description' => $this->t('The last element of the array.'),
    ];
    $info['tokens']['array']['count'] = [
      'name' => $this->t('Count'),
      'description' => $this->t('The number of elements in the array.'),
    ];
    $info['tokens']['array']['reversed'] = [
      'name' => $this->t('Reversed'),
      'description' => $this->t('The array reversed.'),
      'type' => 'array',
    ];
    $info['tokens']['array']['keys'] = [
      'name' => $this->t('Keys'),
      'description' => $this->t('The array of keys of the array.'),
      'type' => 'array',
    ];
    $info['tokens']['array']['join'] = [
      'name' => $this->t('Imploded'),
      'description' => $this->t('The values of the array joined together with a custom string in-between each value.'),
      'dynamic' => TRUE,
    ];
    $info['tokens']['array']['value'] = [
      'name' => $this->t('Value'),
      'description' => $this->t('The specific value of the array.'),
      'dynamic' => TRUE,
    ];
    // Random tokens.
    $info['types']['random'] = [
      'name' => $this->t('Random'),
      'description' => $this->t('Tokens related to random data.'),
    ];
    $info['tokens']['random']['number'] = [
      'name' => $this->t('Number'),
      'description' => $this->t('A random number from 0 to @max.', [
        '@max' => mt_getrandmax(),
      ]),
    ];
    $info['tokens']['random']['hash'] = [
      'name' => $this->t('Hash'),
      'description' => $this->t('A random hash. The possible hashing algorithms are: @hash-algos.', [
        '@hash-algos' => implode(', ', hash_algos()),
      ]),
      'dynamic' => TRUE,
    ];
    // Define image_with_image_style token type.
    if ($this->moduleHandler->moduleExists('image')) {
      $info['types']['image_with_image_style'] = [
        'name' => $this->t('Image with image style'),
        'needs-data' => 'image_with_image_style',
        'module' => 'token',
        'nested' => TRUE,
      ];
      // Provide tokens for the ImageStyle attributes.
      $info['tokens']['image_with_image_style']['mimetype'] = [
        'name' => $this->t('MIME type'),
        'description' => $this->t('The MIME type (image/png, image/bmp, etc.) of the image.'),
      ];
      $info['tokens']['image_with_image_style']['filesize'] = [
        'name' => $this->t('File size'),
        'description' => $this->t('The file size of the image.'),
      ];
      $info['tokens']['image_with_image_style']['height'] = [
        'name' => $this->t('Height'),
        'description' => $this->t('The height the image, in pixels.'),
      ];
      $info['tokens']['image_with_image_style']['width'] = [
        'name' => $this->t('Width'),
        'description' => $this->t('The width of the image, in pixels.'),
      ];
      $info['tokens']['image_with_image_style']['uri'] = [
        'name' => $this->t('URI'),
        'description' => $this->t('The URI to the image.'),
      ];
      $info['tokens']['image_with_image_style']['url'] = [
        'name' => $this->t('URL'),
        'description' => $this->t('The URL to the image.'),
      ];
    }
    return $info;
  }

  /**
   * Proxy implementation of hook_token_info() on behalf of book.module.
   */
  protected function bookTokenInfo() {
    $info['types']['book'] = [
      'name' => $this->t('Book'),
      'description' => $this->t('Tokens related to books.'),
      'needs-data' => 'book',
    ];

    $info['tokens']['book']['title'] = [
      'name' => $this->t('Title'),
      'description' => $this->t('Title of the book.'),
    ];
    $info['tokens']['book']['author'] = [
      'name' => $this->t('Author'),
      'description' => $this->t('The author of the book.'),
      'type' => 'user',
    ];
    $info['tokens']['book']['root'] = [
      'name' => $this->t('Root'),
      'description' => $this->t('Top level of the book.'),
      'type' => 'node',
    ];
    $info['tokens']['book']['parent'] = [
      'name' => $this->t('Parent'),
      'description' => $this->t('Parent of the current page.'),
      'type' => 'node',
    ];
    $info['tokens']['book']['parents'] = [
      'name' => $this->t('Parents'),
      'description' => $this->t("An array of all the node's parents, starting with the root."),
      'type' => 'array',
    ];

    $info['tokens']['node']['book'] = [
      'name' => $this->t('Book'),
      'description' => $this->t('The book page associated with the node.'),
      'type' => 'book',
    ];
    return $info;
  }

  /**
   * Proxy implementation of hook_token_info() on behalf of menu_ui.module.
   */
  protected function menuUiTokenInfo() {
    // Menu tokens.
    $info['types']['menu'] = [
      'name' => $this->t('Menus'),
      'description' => $this->t('Tokens related to menus.'),
      'needs-data' => 'menu',
    ];
    $info['tokens']['menu']['name'] = [
      'name' => $this->t('Name'),
      'description' => $this->t("The name of the menu."),
    ];
    $info['tokens']['menu']['machine-name'] = [
      'name' => $this->t('Machine-readable name'),
      'description' => $this->t("The unique machine-readable name of the menu."),
    ];
    $info['tokens']['menu']['description'] = [
      'name' => $this->t('Description'),
      'description' => $this->t('The optional description of the menu.'),
    ];
    $info['tokens']['menu']['menu-link-count'] = [
      'name' => $this->t('Menu link count'),
      'description' => $this->t('The number of menu links belonging to the menu.'),
    ];
    $info['tokens']['menu']['edit-url'] = [
      'name' => $this->t('Edit URL'),
      'description' => $this->t("The URL of the menu's edit page."),
    ];

    $info['tokens']['menu-link']['menu'] = [
      'name' => $this->t('Menu'),
      'description' => $this->t('The menu of the menu link.'),
      'type' => 'menu',
    ];
    $info['tokens']['menu-link']['edit-url'] = [
      'name' => $this->t('Edit URL'),
      'description' => $this->t("The URL of the menu link's edit page."),
    ];

    if ($this->moduleHandler->moduleExists('node')) {
      $info['tokens']['node']['menu-link'] = [
        'name' => $this->t('Menu link'),
        'description' => $this->t("The menu link for this node."),
        'type' => 'menu-link',
      ];
    }

    return $info;
  }

  /**
   * Proxy implementation of hook_token_info_alter() on behalf of field.module.
   *
   * We use hook_token_info_alter() rather than hook_token_info() as other
   * modules may already have defined some field tokens.
   */
  protected function fieldTokenInfoAlter(&$info) {
    $type_info = $this->fieldTypePluginManager->getDefinitions();

    // Attach field tokens to their respective entity tokens.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      // Make sure a token type exists for this entity.
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id);
      if (empty($token_type) || !isset($info['types'][$token_type])) {
        continue;
      }

      $fields = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      foreach ($fields as $field_name => $field) {
        /** @var \Drupal\field\FieldStorageConfigInterface $field */

        // Ensure the token implements FieldStorageConfigInterface or is defined
        // in token module.
        $provider = '';
        if (isset($info['types'][$token_type]['module'])) {
          $provider = $info['types'][$token_type]['module'];
        }
        if (!($field instanceof FieldStorageConfigInterface) && $provider != 'token') {
          continue;
        }

        // If a token already exists for this field, then don't add it.
        if (isset($info['tokens'][$token_type][$field_name])) {
          continue;
        }

        if ($token_type == 'comment' && $field_name == 'comment_body') {
          // Core provides the comment field as [comment:body].
          continue;
        }

        // Do not define the token type if the field has no properties.
        if (!$field->getPropertyDefinitions()) {
          continue;
        }

        // Generate a description for the token.
        $labels = $this->tokenFieldLabel($entity_type_id, $field_name);
        $label = array_shift($labels);
        $params['@type'] = $type_info[$field->getType()]['label'];
        if (!empty($labels)) {
          $params['%labels'] = implode(', ', $labels);
          $description = $this->t('@type field. Also known as %labels.', $params);
        }
        else {
          $description = $this->t('@type field.', $params);
        }

        $cardinality = $field->getCardinality();
        $cardinality = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $cardinality > 3) ? 3 : $cardinality;
        $field_token_name = $token_type . '-' . $field_name;
        $info['tokens'][$token_type][$field_name] = [
          'name' => Html::escape($label),
          'description' => $description,
          'module' => 'token',
          // For multivalue fields the field token is a list type.
          'type' => $cardinality > 1 ? "list<$field_token_name>" : $field_token_name,
        ];

        // Field token type.
        $info['types'][$field_token_name] = [
          'name' => Html::escape($label),
          'description' => $this->t('@label tokens.', ['@label' => Html::escape($label)]),
          'needs-data' => $field_token_name,
          'nested' => TRUE,
        ];
        // Field list token type.
        if ($cardinality > 1) {
          $info['types']["list<$field_token_name>"] = [
            'name' => $this->t('List of @type values', ['@type' => Html::escape($label)]),
            'description' => $this->t('Tokens for lists of @type values.', ['@type' => Html::escape($label)]),
            'needs-data' => "list<$field_token_name>",
            'nested' => TRUE,
          ];
        }

        // Show a different token for each field delta.
        if ($cardinality > 1) {
          for ($delta = 0; $delta < $cardinality; $delta++) {
            $info['tokens']["list<$field_token_name>"][$delta] = [
              'name' => $this->t('@type type with delta @delta', ['@type' => Html::escape($label), '@delta' => $delta]),
              'module' => 'token',
              'type' => $field_token_name,
            ];
          }
        }

        // Property tokens.
        foreach ($field->getPropertyDefinitions() as $property => $property_definition) {
          if (is_subclass_of($property_definition->getClass(), 'Drupal\Core\TypedData\PrimitiveInterface')) {
            $info['tokens'][$field_token_name][$property] = [
              'name' => $property_definition->getLabel(),
              'description' => $property_definition->getDescription(),
              'module' => 'token',
            ];
          }
          elseif (($property_definition instanceof DataReferenceDefinitionInterface) && ($property_definition->getTargetDefinition() instanceof EntityDataDefinitionInterface)) {
            $referenced_entity_type = $property_definition->getTargetDefinition()->getEntityTypeId();
            $referenced_token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($referenced_entity_type);
            $info['tokens'][$field_token_name][$property] = [
              'name' => $property_definition->getLabel(),
              'description' => $property_definition->getDescription(),
              'module' => 'token',
              'type' => $referenced_token_type,
            ];
          }
        }
        // Provide image_with_image_style tokens for image fields.
        if ($field->getType() == 'image') {
          $image_styles = image_style_options(FALSE);
          foreach ($image_styles as $style => $description) {
            $info['tokens'][$field_token_name][$style] = [
              'name' => $description,
              'description' => $this->t('Represents the image in the given image style.'),
              'type' => 'image_with_image_style',
            ];
          }
        }
        // Provide format token for datetime fields.
        $date_fields = ['datetime', 'timestamp', 'created', 'changed'];
        if (in_array($field->getType(), $date_fields, TRUE)) {
          $info['tokens'][$field_token_name]['date'] = $info['tokens'][$field_token_name]['value'];
          $info['tokens'][$field_token_name]['date']['name'] .= ' ' . $this->t('format');
          $info['tokens'][$field_token_name]['date']['type'] = 'date';
        }
        if ($field->getType() == 'daterange' || $field->getType() == 'date_recur') {
          $info['tokens'][$field_token_name]['start_date'] = $info['tokens'][$field_token_name]['value'];
          $info['tokens'][$field_token_name]['start_date']['name'] .= ' ' . $this->t('format');
          $info['tokens'][$field_token_name]['start_date']['type'] = 'date';
          $info['tokens'][$field_token_name]['end_date'] = $info['tokens'][$field_token_name]['end_value'];
          $info['tokens'][$field_token_name]['end_date']['name'] .= ' ' . $this->t('format');
          $info['tokens'][$field_token_name]['end_date']['type'] = 'date';
        }
      }
    }
  }

  /**
   * Returns the label of a certain field.
   *
   * Therefore it looks up in all bundles to find the most used instance.
   *
   * Based on views_entity_field_label().
   *
   * @todo Resync this method with views_entity_field_label().
   */
  protected function tokenFieldLabel($entity_type, $field_name) {
    $labels = [];
    // Count the amount of instances per label per field.
    foreach (array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type)) as $bundle) {
      $bundle_instances = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      if (isset($bundle_instances[$field_name])) {
        $instance = $bundle_instances[$field_name];
        $label = (string) $instance->getLabel();
        $labels[$label] = isset($labels[$label]) ? ++$labels[$label] : 1;
      }
    }

    if (empty($labels)) {
      return [$field_name];
    }

    // Sort the field labels by it most used label and return the labels.
    arsort($labels);
    return array_keys($labels);
  }

}
