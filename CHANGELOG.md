# Changelog

All notable changes to Mukurtu CMS are recorded here. Each entry corresponds to
a tagged release; see the
[releases page](https://github.com/MukurtuCMS/Mukurtu-CMS/releases) for the tags
themselves.

## 4.0.7

A security and maintenance release. It requires CAPTCHA 2.0.11 or later, which
fixes the security issue SA-CONTRIB-2026-281, and fixes the database update
failure some multilingual sites hit when updating to 4.0.6. It also fixes
several bugs and accessibility issues, and raises the minimum versions of Drupal
core (11.4.10), Gin (5.0.16), and Leaflet (10.4.13) to the security-fixed
releases that 4.0.6 recommended.

If `drush updb` stopped at `mukurtu_core_update_40214` on 4.0.6, update to 4.0.7
and run database updates again. If you skipped that update instead, run it
with:

```
drush php:eval '\Drupal::moduleHandler()->loadInclude("mukurtu_core", "install"); mukurtu_core_update_40214();'
```

New in this release: arrow buttons to reorder Entity Browser items without
dragging, an explanation when a protocol or community can't be deleted, and an
option for admins to dismiss the dashboard migration panel. Community managers
can now delete communities, and comments show the author's display name.

To update, back up your site, run `composer update`, and then run database
updates (`drush updb`). This release includes 8 database updates. They:

- Point the add content links at Drupal's standard /node/add pages.
- Remove unused settings that fail configuration schema checks.
- Remove two unused notification fields that an earlier update left partly
  deleted. The status report lists them as needing to be uninstalled. Run cron
  (`drush cron`) afterward to remove their database tables.
- Set communities with an invalid page visibility to Community only, so they
  are no longer hidden.
- Map Mukurtu 3 community visibility to valid values, and give Mukurtu 3
  language stewards the Community Manager role in their language communities.
- Delete memberships left behind by deleted communities and protocols.
- Mark the Mukurtu 3 migration setup task done on sites that no longer need it.

## 4.0.6

A security and feature release. Update as soon as you can, and make sure the
update includes Drupal core 11.4.10 or later, which fixes the critical remote
code execution issue SA-CORE-2026-014. Run a full `composer update`: updating
only `mukurtu/mukurtu` does not update Drupal core. The release also updates
Gin to 5.0.16 (SA-CONTRIB-2026-219) and Leaflet to 10.4.13
(SA-CONTRIB-2026-204).

New in this release: caption and subtitle tracks for uploaded video, home page
and header background images, a carousel option for Featured Content blocks,
optional roles on Creator, Contributor and People fields, translation status
pages, right-to-left languages, and visitors by city on the visitor map. Search
now finds words in more writing systems. Notification emails are now opt-in for
new accounts, and the Masquerade module is included by default. The release
also includes many accessibility fixes.

To update, back up your site, run `composer update`, and then run database
updates (`drush updb`). This release includes 34 database updates. Most add
settings or fields. These ones affect how the update runs:

- Search tables are converted to utf8mb4 so they can store characters such as
  emoji and Osage letters. On a site with a large search index, this step can
  take a while.
- All search indexes are queued for reindexing. Search and browse results are
  incomplete until reindexing finishes, so run cron (`drush cron`) until it
  does.
- Browse and search pages that showed every translation of an item now show one
  item per language.
- The Masquerade module is installed.
- New accounts default to "Do not send emails" for notifications. Existing
  accounts keep their setting.
- Migrated group memberships whose edit forms failed to load are repaired.

After the update, cron starts filling in locations for past visits on the
Visitors pages. This runs in the background over several cron runs.

## 4.0.5

A maintenance release with bug fixes, accessibility improvements, and fixes to
CSV import and export. It also requires Drupal core 11.4.8 or later, which fixes
the security issue SA-CORE-2026-013.

To update, run `composer update` and then run database updates (`drush updb`).
This release includes 11 database updates. They:

- Let logged-in users open the entity browsers they need, including the
  collection browser.
- Add missing columns to the default CSV export settings, and ignore the Default
  translation column in the default import templates.
- Install the content_translation module, which CSV import needs.
- Point the Landing Pages URL alias pattern at landing pages. Existing landing
  pages get an alias the next time they're saved.
- Merge identical names in Other Names on Person records and in Other Place
  Names on Place records.
- Remove the duplicate, unlabeled Glossary Entry filter from the Dictionary
  page.
- Fix the chart report links on the Visitors page, and hide contextual admin
  links in the categories view.
- Download the DB-IP fallback geolocation database. This step needs outbound
  internet access during `drush updb`.

## 4.0.4

A maintenance release fixing two defects in the Mukurtu 3 migration. Sites that
have not yet migrated should update before running it.

If you already migrated on 4.0.3, check two things: your administrator account
(user 1) may have lost its roles, which you can restore under People; and any
Mukurtu 3 user whose username was already in use on this site was not migrated,
and is imported under a numbered name if you run the migration again.

No special upgrade steps are required; update as usual.

## 4.0.3

A maintenance release with bug fixes and accessibility improvements. Sites
migrating from Mukurtu 3 should update to this release before running the
migration: it fixes legacy users being skipped or merged onto existing
accounts, and the administrator account (user 1) not being migrated. No
special upgrade steps are required; update as usual.

## 4.0.2

A maintenance release with bug fixes and accessibility improvements. No
special upgrade steps are required; update as usual.

## 4.0.1

A maintenance release with no new features. It removes the database updates that
accumulated during the 4.0.x beta period, so new sites start from a clean
baseline.

**Sites must update to 4.0.0 before updating to 4.0.1.** The updates 4.0.0
shipped no longer exist in 4.0.1, so a site that skips it will report no pending
updates while continuing to run against out-of-date configuration. See the
instructions for passing through a version in [README.md](README.md).

For sites already on 4.0.0, updating to 4.0.1 changes nothing and runs no
database updates.

## 4.0.0

The first public release of Mukurtu CMS 4.

Mukurtu 4 is a Drupal 11 distribution and a rewrite rather than an upgrade of
Mukurtu 3. Sites running Mukurtu 3 migrate into it rather than updating in
place.

Development history prior to this release is in the commit log and in the
`4.0.0-beta*` and `4.0.0-rc` tags on the
[releases page](https://github.com/MukurtuCMS/Mukurtu-CMS/releases). Those
releases were for testing and feedback and are not consolidated here.

For how to install or update a site, see [README.md](README.md).
