# Changelog

All notable changes to Mukurtu CMS are recorded here. Each entry corresponds to
a tagged release; see the
[releases page](https://github.com/MukurtuCMS/Mukurtu-CMS/releases) for the tags
themselves.

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
