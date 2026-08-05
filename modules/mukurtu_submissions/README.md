# Description

Lets site visitors submit content for review without community/protocol membership, via a public form at `/submit/{entity_type_id}/{bundle}`. Submissions are held under a blocked service account in a "draft" moderation state until a reviewer publishes them from the Pending Submissions queue.

The submission form, its access check, and the `mukurtu_submission_settings` config entity are all generic - none of them contain per-content-type code. Digital Heritage is the only bundle configured out of the box.

## Enabling public submissions for another content type

1. Add a `mukurtu_submission_settings` entity at `/admin/config/mukurtu/submissions/add`, selecting the target content type and checking "Enable public submissions for this content type".
2. Saving it for the first time automatically creates a `submission` form display for that bundle (seeded from every field the bundle has, minus the fields the public form never shows). Use the "Manage fields shown on this form" link on the settings edit page to adjust which fields appear and their widgets, the same way you would for any other form display.
3. The public form's URL uses a hyphenated bundle segment automatically (e.g. `field_trip_report` becomes `/submit/node/field-trip-report`) - no extra configuration needed.

If a bundle's settings are enabled but its `submission` form display was deleted or never created, the form route denies access rather than falling back to showing every field on the bundle's default add form.
