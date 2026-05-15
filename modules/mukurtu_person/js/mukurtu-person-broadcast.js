/**
 * @file
 * Broadcasts a BroadcastChannel message after a new person record is saved.
 *
 * hook_page_attachments() attaches this library (with drupalSettings carrying
 * the new person's nid and title) on the first page load after a person node
 * is inserted. The message is picked up by mukurtu-person-listener.js running
 * in any other open tab that has a field_related_person entity browser.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.mukurtuPersonBroadcast = {
    attach: function (context, settings) {
      if (!settings.mukurtuPerson || !settings.mukurtuPerson.newPerson) {
        return;
      }

      // Grab and immediately clear so re-attaches (e.g. AJAX) don't re-fire.
      const person = settings.mukurtuPerson.newPerson;
      delete drupalSettings.mukurtuPerson.newPerson;

      if (!('BroadcastChannel' in window)) {
        return;
      }

      const channel = new BroadcastChannel('mukurtu_person_created');
      channel.postMessage({ nid: person.nid, title: person.title });
      channel.close();

      // Close the new tab so the user lands back on the original form where
      // the entity browser is already opening. A brief delay lets the browser
      // finish rendering the page before closing. Modern browsers (Chrome,
      // Firefox) allow window.close() on tabs opened via user link click.
      setTimeout(() => window.close(), 500);
    }
  };

})(Drupal, drupalSettings);
