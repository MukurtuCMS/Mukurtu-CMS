# Issue #2242: desk review results (bucket A)

Worked through the 18 criteria that
[2026-10-06-criteria-triage.md](2026-10-06-criteria-triage.md) put in bucket A,
the ones decidable from the codebase and config without a running site.

**14 of 18 are decided here. 4 need more than code reading.** Two real gaps
turned up, both capability gaps rather than markup bugs, which means neither
would ever have been caught by a page scan.

**No ACR level is changed by this document.** The "Recommended" column is a
recommendation for review, not a claim. Levels move in a separate change once
these are agreed, per the rule that evidence precedes the claim.

## Gaps found

### 1. Locally hosted video cannot carry captions or audio description

`media.video` lets an author fill in `field_media_video_file`, `field_thumbnail`,
`field_people`, `field_cultural_protocols`, `field_identifier`, `field_media_tags`,
`name`, `path`, `status`, `created`, `uid` and `langcode`. There is **no caption
or subtitle track field**, and the `file_video` formatter (`controls: true`,
`autoplay: false`) renders no `<track>` element.

So a site that uploads its own video has no way to provide synchronized
captions (1.2.2, Level A) or audio description (1.2.5, Level AA). The
`remote_video` bundle is unaffected, because YouTube carries its own captions.

A transcript is *not* a substitute. It satisfies 1.2.3 (which accepts a media
alternative) but not 1.2.2, which requires captions synchronized with the video.

This is as much an ATAG Part B question as a WCAG one: the authoring tool does
not let an author produce accessible content even when they want to.

### 2. Dictionary content cannot express its language

Each dictionary word has `field_dictionary_word_language`, a required reference
to a term in the `language` vocabulary. But **that vocabulary has no fields** —
a term carries a name and nothing else. There is no ISO 639 or BCP 47 code
anywhere, so there is no value a template could put in a `lang` attribute, and
no dictionary template emits one.

This is the interesting shape of the problem: the language of each word *is*
known and *is* required, and is still unavailable to assistive technology. The
fix is not "add `lang` to the template" — it is "give the language vocabulary a
code field first." Many Indigenous languages have ISO 639-3 codes; those that
do not can use a private-use subtag, so this is solvable, not blocked.

Local Contexts labels already do this correctly (`local-contexts-item.html.twig`
emits `lang="{{ locale }}"` on label text and on each translation), which is a
useful precedent and shows the gap is specific rather than systemic.

## Decided (14)

| SC | Level | Recommended | Evidence |
|---|---|---|---|
| 1.2.1 | A | supports | `digital_heritage` has `field_transcription` (`text_long`), described as "a basic text transcription of an audio or video recording, or of text in an image or document", and it is **shown** on the `full` and `taxonomy_record` displays |
| 1.2.2 | A | **partially-supports** | No caption track field on `media.video`; `file_video` renders no `<track>`. `remote_video` inherits provider captions. See gap 1 |
| 1.2.3 | A | supports | The DH transcription field is a media alternative, which 1.2.3 accepts. Caveat: it is described as "basic", so whether a given item's transcript is a *full* alternative is editorial |
| 1.2.4 | AA | **not-applicable** | No live-streaming feature. Recorded as not-applicable rather than supports, which are different claims |
| 1.2.5 | AA | **does-not-support** | No synchronized audio description capability for local video. See gap 1 |
| 1.3.3 | A | supports | No UI string found that gives an instruction by position, shape or colour alone. Confirm rendered instructions in the visual pass |
| 1.3.4 | AA | supports | No orientation lock. `_include-media.scss` defines `portrait`/`landscape` aliases but nothing restricts to one |
| 1.4.2 | A | supports | Nothing autoplays: dictionary audio sets `autoplay: false`, GLightbox sets `autoplayVideos: false`, and the YouTube oEmbed lists `autoplay` in `allow=` (a permission) with no `autoplay=1` in the URL |
| 2.1.4 | A | supports | All 15 files binding `keydown`/`keypress` use non-printable keys (Arrow\*, Home, End, Escape, Tab) except Enter/Space in `media-library-cancel.js` and `content-warnings.js`. Both are scoped to a focused element, so the "active only on focus" exception applies, and activating a control with Enter/Space is standard operation rather than a shortcut |
| 2.2.1 | A | supports | No `http-equiv="refresh"`, no `location.reload`, no `setInterval` in our JS, and no session lifetime override shipped. Drupal's session expiry is a security timeout, which 2.2.1 exempts |
| 2.3.1 | A | supports | No `@keyframes` in any of our SCSS. The only animation is vendor Splide's rotating loading spinner, which does not flash |
| 2.5.4 | A | **not-applicable** | No `devicemotion`, `deviceorientation` or `DeviceMotionEvent` anywhere in `modules/` or `themes/` |
| 3.1.2 | AA | **does-not-support** | The `language` vocabulary has no code field, so no `lang` can be emitted for dictionary content. See gap 2 |
| 4.1.1 | A | supports | Satisfied by definition in HTML5, and removed from WCAG in 2.2. Recording the reasoning rather than testing |

## Still open after desk review (4)

| SC | Why code reading was not enough | Next step |
|---|---|---|
| 1.3.5 Identify Input Purpose | Needs the rendered `autocomplete` attribute on fields collecting the user's own information. The `autocomplete` matches in the codebase are Drupal's entity-reference widget, which is unrelated | Automate as a DOM check |
| 2.4.5 Multiple Ways | Site search, main navigation and browse all exist, and no sitemap module is installed. Whether *every* page type is reachable two independent ways is a judgment about the live information architecture | Confirm on a running site |
| 2.5.3 Label in Name | Needs each control's accessible name compared against its visible label | Automate as a DOM check |
| 3.3.4 Error Prevention | 19 classes extend a confirm-form base. Which destructive or legally significant actions lack one is the actual question | Enumerate destructive routes and check each |

## Suggested follow-ups

Two issues worth filing, neither of which a page scan would find:

1. **Captions and audio description for locally hosted video** (1.2.2, 1.2.5, ATAG B). Add a caption-track field to `media.video` and render `<track>`, or document `remote_video` as the supported path for captioned video.
2. **A language code on the `language` vocabulary** (3.1.2). Add an ISO 639 / BCP 47 field, then emit `lang` on dictionary word, definition and sample-sentence output, following the Local Contexts template as the precedent.

Neither is in scope for #2242 itself, which is an audit.
