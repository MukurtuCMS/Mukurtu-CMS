<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use DOMDocument;
use DOMXPath;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\MigrateException;

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
 * First, this plugin loads the $value as an HTML DOMDocument for ease of parsing.
 * Then, it searches the DOM for the media embed wrapper div with class 'dnd-atom-wrapper',
 * saving the divs it finds as DOMElements to the $scaldIdDivs array.
 * (Note: Per media wrapper div, there will be exactly one media item embedded within.)
 * Then, it extracts the scald id of the embedded media from the 'data-scald-sid' attribute.
 * Using this scald id, a migration lookup is performed to check all other media
 * migrations for the embedded media's corresponding v4 id. The code therein is
 * lifted from Drupal's migration lookup service (referenced in MigrationLookup.php).
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
class MediaEmbed extends MigrationLookup
{
  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    // TODO: what if the value coming in is NOT formatted text?
    if ($value) {
      $doc = new DOMDocument();

      $doc->loadHTML($value, LIBXML_HTML_NODEFDTD);
      $xpath = new DOMXPath($doc);
      $scaldIdDivs = $xpath->query("//div[@class='dnd-atom-wrapper']");

      foreach ($scaldIdDivs as $div) {
        $sid = $div->getAttribute("data-scald-sid");
        // Get the uuid based on the sid. However it's not so simple bc
        // we need to look up the scald atom migration first to get the v4 uuid.
        $mediaId = $this->mediaLookup($sid);
        if ($mediaId) {
          $mediaEntity = \Drupal::entityTypeManager()->getStorage("media")->load($mediaId);
          if ($mediaEntity) {
            $uuid = $mediaEntity->uuid();
            $newEmbed = $doc->createElement("drupal-media", "&nbsp;");
            $newEmbed->setAttribute("data-entity-type", "media");
            $newEmbed->setAttribute("data-entity-uuid", $uuid);
            $div->replaceWith($newEmbed);
          }
        }
      }
      $newValue = $doc->saveHTML();
      $stripTheseTags = ["<html>", "</html>", "<body>", "</body>"];
      $newValue = str_replace($stripTheseTags, "", $newValue);

      return $newValue;
    }

    return $value;
  }

  /**
   * Look up media migrations to find the v4 media id corresponding to $sid.
   *
   * Lifted from Drupal's migration lookup service (referenced in
   * MigrationLookup.php).
   *
   * @param string $sid, i.e. "1"
   * @return string mediaIdArray (array_values), the corresponding v4 media id.
   */
  protected function mediaLookup($sid) {
    $lookupMigrations = [
      "mukurtu_cms_v3_media_image",
      "mukurtu_cms_v3_media_document",
      "mukurtu_cms_v3_media_audio",
      "mukurtu_cms_v3_media_video"
    ];
    $lookupValue = (array) $sid;
    // Re-throw any PluginException as a MigrateException so the executable
    // can shut down the migration.
    try {
      $mediaIdArray = $this->migrateLookup->lookup($lookupMigrations, $lookupValue);
    } catch (PluginNotFoundException $e) {
      $mediaIdArray = [];
    } catch (MigrateException $e) {
      throw $e;
    } catch (\Exception $e) {
      throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
    }
    if ($mediaIdArray) {
      array_values(reset($mediaIdArray));
    }
    return intval($mediaIdArray);
  }
}
