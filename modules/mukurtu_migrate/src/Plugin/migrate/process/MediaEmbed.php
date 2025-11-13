<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use DOMDocument;
use DOMXPath;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateStubInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\MigrateException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts a v3 media embed into a v4 media embed.
 *
 * Here's an example of a Drupal 7 media embed:
 *
 * @code
 * <div class="dnd-atom-wrapper" data-scald-align="none" data-scald-context="sdl_editor_representation" data-scald-options="" data-scald-sid="1" data-scald-type="image">
 * <div class="dnd-caption-wrapper">
 * <div class="meta"><!--copyright=1-->Default Frontpage Hero Image, by <a data-cke-saved-href="/users/admin" href="/users/admin">admin</a><!--END copyright=1--></div>
 * </div>
 * </div>
 * @endcode
 *
 * And here's the modern Drupal equivalent:
 *
 * @code
 * <drupal-media data-entity-type="media" data-entity-uuid="186c3961-ee04-4457-82dc-ae21bca1849c">&nbsp;</drupal-media>
 * @endcode
 *
 * Hence, this plugin transforms v3 media embeds to the proper format for v4.
 * Here's a rundown of how it works:
 *
 * First, this plugin loads the $value as an HTML DOMDocument for ease of
 * parsing. Then, it searches the DOM for the media embed wrapper div with class
 * 'dnd-atom-wrapper', saving the divs it finds as DOMElements to the
 * $scaldIdDivs array. (Note: Per media wrapper div, there will be exactly one
 * media item embedded within.) Then, it extracts the scald id of the embedded
 * media from the 'data-scald-sid' attribute. Using this scald id, a migration
 * lookup is performed to check all other media migrations for the embedded
 * media's corresponding v4 id. The code therein is lifted from Drupal's
 * migration lookup service (referenced in MigrationLookup.php).
 *
 * Once we have the v4 media id, we load the media entity to get its uuid.
 * Then, the scaldIdDivs are replaced with proper, modern Drupal media embeds.
 * The &nbsp; is required--without it, the media is deleted since it's stripped
 * as whitespace.
 *
 * The $doc value is saved to HTML to return the HTML content as a string.
 * A little more processing is done to strip the <html> and <body> tags that are
 * added automatically after saving the HTML.
 *
 * @MigrateProcessPlugin(
 *   id = "media_embed",
 *   handle_multiples = FALSE
 * )
 */
class MediaEmbed extends MigrationLookup {

  /**
   * Constructs a MediaEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The Migration the plugin is being used in.
   * @param \Drupal\migrate\MigrateLookupInterface $migrate_lookup
   *   The migrate lookup service.
   * @param \Drupal\migrate\MigrateStubInterface $migrate_stub
   *   The migrate stub service
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, MigrateLookupInterface $migrate_lookup, MigrateStubInterface $migrate_stub, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migrate_lookup, $migrate_stub);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('migrate.lookup'),
      $container->get('migrate.stub'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Return early for empty string or non-string values.
    if (empty($value) || !is_string($value)) {
      return $value;
    }

    // Note that the HTML parser underlying DOMDocument is very liberal and will
    // accept a wide range of strings that are not valid HTML. We somtimes get
    // JSON data as $value here. There is no harm here, as it will load, but
    // fail the xpath query.
    $doc = new DOMDocument();
    $doc->loadHTML($value, LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    $scald_id_divs = $xpath->query("//div[@class='dnd-atom-wrapper']");

    // Nothing to do if we can't find any scald atom wrapper divs.
    if ($scald_id_divs->length === 0) {
      return $value;
    }

    $scald_atom_replaced = FALSE;
    foreach ($scald_id_divs as $div) {
      $sid = $div->getAttribute("data-scald-sid");
      $type = $div->getAttribute("data-scald-type");
      // Get the uuid based on the sid and type. However it's not so simple b/c
      // we need to look up the scald atom migration first to get the v4 uuid.
      $media_id = $this->mediaLookup($sid, $type);
      if (!$media_id) {
        continue;
      }
      $media_entity = $this->entityTypeManager->getStorage("media")->load($media_id);
      if (!$media_entity) {
        continue;
      }

      $uuid = $media_entity->uuid();
      try {
        $new_embed = $doc->createElement("drupal-media", "&nbsp;");
        $new_embed->setAttribute("data-entity-type", "media");
        $new_embed->setAttribute("data-entity-uuid", $uuid);
        $div->replaceWith($new_embed);
        $scald_atom_replaced = TRUE;
      }
      catch (\DOMException $e) {
        // If we can't replace the div with the new embed, just skip it.
      }
    }

    if ($scald_atom_replaced) {
      $new_value = $doc->saveHTML();
      $strip_these_tags = ["<html>", "</html>", "<body>", "</body>"];
      $value = str_replace($strip_these_tags, "", $new_value);
    }

    return $value;
  }

  /**
   * Look up media migrations to find the v4 media id corresponding to $sid.
   *
   * Lifted from Drupal's migration lookup service (referenced in
   * MigrationLookup.php).
   *
   * @param string $sid
   *   The Scald id of the media item.
   * @param string $type
   *   The type of media item.
   *
   * @return int|null
   *   The corresponding v4 media id.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Thrown when there is an issue with the lookup.
   */
  protected function mediaLookup(string $sid, string $type): ?int {
    $lookup_migrations = [
      "mukurtu_cms_v3_media_image",
      "mukurtu_cms_v3_media_document",
      "mukurtu_cms_v3_media_audio",
      "mukurtu_cms_v3_media_video"
    ];
    $lookup_value = (array) $sid;

    // Try to narrow the migration by type to avoid sid collisions.
    $target_migration = sprintf('mukurtu_cms_v3_media_%s', $type);
    if (in_array($target_migration, $lookup_migrations)) {
      $lookup_migrations = [$target_migration];
    }

    // Re-throw any PluginException as a MigrateException so the executable
    // can shut down the migration.
    try {
      $media_id_array = $this->migrateLookup->lookup($lookup_migrations, $lookup_value);
    } catch (PluginNotFoundException $e) {
      $media_id_array = [];
    } catch (MigrateException $e) {
      throw $e;
    } catch (\Exception $e) {
      throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
    }
    if (empty($media_id_array)) {
      return NULL;
    }
    $media_id_array = array_column($media_id_array, 'mid');
    if (empty($media_id_array)) {
      return NULL;
    }
    return (int) reset($media_id_array);
  }

}
