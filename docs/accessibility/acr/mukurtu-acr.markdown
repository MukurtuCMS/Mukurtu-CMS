# Mukurtu CMS Accessibility Conformance Report

Based on VPAT® 2.4

## Name of Product/Version
Mukurtu CMS 4

## Report Dates and Version
- Report Date: 2026-07-10
- Last Modified Date: 2026-07-16
- Version: mukurtu cms-4-2

## Product Description
Mukurtu CMS is a free, mobile, and open source platform built with Indigenous communities to manage and share digital cultural heritage. It is a distribution of the Drupal content management system.

## Contact Information
### Author Information
- Name: Mukurtu CMS Development Team
- Company: Center for Digital Scholarship and Curation, Washington State University

- Email: support@mukurtu.org

- Website: https://mukurtu.org

## Notes
This is a living document maintained as part of the Mukurtu CMS accessibility program (see docs/accessibility/README.md in the Mukurtu CMS repository). All criteria begin as not-evaluated and are updated as audit findings are triaged. Assessments reflect Mukurtu CMS itself (its installation profile, custom modules, and the mukurtu_v4 theme); conformance of an individual Mukurtu site also depends on its content and configuration.

## Evaluation Methods
Automated scanning with axe-core (via Playwright) across a representative page inventory, combined with manual keyboard-only testing and screen reader testing (NVDA, VoiceOver, or Orca) following the program&#x27;s manual testing checklist. Findings are consolidated by WCAG Success Criterion before conformance levels are assigned.

## Applicable Standards/Guidelines
This report covers the degree of conformance for the following accessibility standard/guidelines:

| Standard/Guideline | Included In Report |
| --- | --- |
| [Web Content Accessibility Guidelines 2.1](https://www.w3.org/TR/WCAG21/) | <ul><li>Table 1: Success Criteria, Level A</li><li>Table 2: Success Criteria, Level AA</li></ul> |

## Terms
The terms used in the Conformance Level information are defined as follows:
- **Supports**: The functionality of the product has at least one method that meets the criterion without known defects or meets with equivalent facilitation.
- **Partially Supports**: Some functionality of the product does not meet the criterion.
- **Does Not Support**: The majority of product functionality does not meet the criterion.
- **Not Applicable**: The criterion is not relevant to the product.
- **Not Evaluated**: The product has not been evaluated against the criterion. This can be used only in WCAG 2.x Level AAA.

## WCAG 2.1 Report

### Table 1: Success Criteria, Level A

Notes: Mukurtu CMS is a Drupal distribution. The &quot;Web&quot; component below covers the visitor and logged-in member experience (the current audit scope). The &quot;Authoring Tool&quot; component covers the content creation and administration experience, which will be evaluated in a later phase against ATAG 2.0 in addition to WCAG.

Conformance to the 30 criteria listed below is distributed within each category as follows:

| Conformance Level | Web | Authoring Tool |
| --- | --- | --- |
| Supports | 6 | 0 |
| Partially Supports | 0 | 1 |
| Does Not Support | 0 | 0 |
| Not Applicable | 0 | 0 |


| Criteria | Conformance Level | Remarks and Explanations |
| --- | --- | --- |
| [1.1.1 Non-text Content](https://www.w3.org/TR/WCAG21/#non-text-content) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: Automated checks (axe-core, July 2026) across the audited page inventory found no missing text alternatives. Map location markers previously exposed as unnamed buttons were fixed to carry the name of the item whose location they show. Alt text quality on real site content is author-dependent and is reviewed in the manual audit cycle.</li> </ul> |
| [1.2.1 Audio-only and Video-only (Prerecorded)](https://www.w3.org/TR/WCAG21/#audio-only-and-video-only-prerecorded) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.2.2 Captions (Prerecorded)](https://www.w3.org/TR/WCAG21/#captions-prerecorded) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.2.3 Audio Description or Media Alternative (Prerecorded)](https://www.w3.org/TR/WCAG21/#audio-description-or-media-alternative-prerecorded) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.3.1 Info and Relationships](https://www.w3.org/TR/WCAG21/#info-and-relationships) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: Automated structural checks (axe-core, July 2026) pass across the audited page inventory. The page title region was moved inside the main landmark so all page content is contained by landmarks (fixed July 2026). Heading outline quality and reading order are verified in the manual audit cycle.</li> </ul> |
| [1.3.2 Meaningful Sequence](https://www.w3.org/TR/WCAG21/#meaningful-sequence) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.3.3 Sensory Characteristics](https://www.w3.org/TR/WCAG21/#sensory-characteristics) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.1 Use of Color](https://www.w3.org/TR/WCAG21/#use-of-color) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.2 Audio Control](https://www.w3.org/TR/WCAG21/#audio-control) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.1.1 Keyboard](https://www.w3.org/TR/WCAG21/#keyboard) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.1.2 No Keyboard Trap](https://www.w3.org/TR/WCAG21/#no-keyboard-trap) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.1.4 Character Key Shortcuts](https://www.w3.org/TR/WCAG21/#character-key-shortcuts) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.2.1 Timing Adjustable](https://www.w3.org/TR/WCAG21/#timing-adjustable) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.2.2 Pause, Stop, Hide](https://www.w3.org/TR/WCAG21/#pause-stop-hide) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.3.1 Three Flashes or Below Threshold](https://www.w3.org/TR/WCAG21/#three-flashes-or-below-threshold) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.4.1 Bypass Blocks](https://www.w3.org/TR/WCAG21/#bypass-blocks) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: A skip-to-main-content link is provided on every page, and as of July 2026 the page heading sits inside the skip link&#x27;s target landmark. Automated checks pass; keyboard verification of the skip link across page types is part of the manual audit cycle.</li> </ul> |
| [2.4.2 Page Titled](https://www.w3.org/TR/WCAG21/#page-titled) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: Automated checks (axe-core, July 2026) confirm every audited page has a page title. Title descriptiveness is reviewed with headings and labels (2.4.6) in the manual audit cycle.</li> </ul> |
| [2.4.3 Focus Order](https://www.w3.org/TR/WCAG21/#focus-order) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.4.4 Link Purpose (In Context)](https://www.w3.org/TR/WCAG21/#link-purpose-in-context) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.5.1 Pointer Gestures](https://www.w3.org/TR/WCAG21/#pointer-gestures) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.5.2 Pointer Cancellation](https://www.w3.org/TR/WCAG21/#pointer-cancellation) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.5.3 Label in Name](https://www.w3.org/TR/WCAG21/#label-in-name) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.5.4 Motion Actuation](https://www.w3.org/TR/WCAG21/#motion-actuation) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.1.1 Language of Page](https://www.w3.org/TR/WCAG21/#language-of-page) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: Automated checks (axe-core, July 2026) confirm a valid lang attribute on the html element of every audited page. Language of parts (3.1.2) for content in Indigenous languages is a separate criterion and remains under evaluation.</li> </ul> |
| [3.2.1 On Focus](https://www.w3.org/TR/WCAG21/#on-focus) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.2.2 On Input](https://www.w3.org/TR/WCAG21/#on-input) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.3.1 Error Identification](https://www.w3.org/TR/WCAG21/#error-identification) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.3.2 Labels or Instructions](https://www.w3.org/TR/WCAG21/#labels-or-instructions) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [4.1.1 Parsing](https://www.w3.org/TR/WCAG21/#parsing) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [4.1.2 Name, Role, Value](https://www.w3.org/TR/WCAG21/#name-role-value) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Partially Supports</li> </ul> | <ul><li>**Web**: Automated ARIA checks (axe-core, July 2026) pass across the audited page inventory, including protocol-gated member views. Map location markers previously rendered as focusable buttons with no accessible name were fixed (July 2026). Keyboard and screen reader verification of custom widgets (carousel, tabs, audio player, autocomplete) is part of the manual audit cycle.</li><li>**Authoring Tool**: July 2026 scans of pages rendered with the administration toolbar found toolbar menu buttons and links with no accessible name and an invalid ARIA attribute on toolbar menus. These originate in the upstream Drupal core/Gin admin toolbar and affect authenticated administrators and authors only; upstream verification and issue links are tracked in the project&#x27;s accessibility findings. The full authoring interface will be evaluated in a later phase.</li> </ul> |


### Table 2: Success Criteria, Level AA


Conformance to the 20 criteria listed below is distributed within each category as follows:

| Conformance Level | Web | Authoring Tool |
| --- | --- | --- |
| Supports | 1 | 0 |
| Partially Supports | 0 | 0 |
| Does Not Support | 0 | 0 |
| Not Applicable | 0 | 0 |


| Criteria | Conformance Level | Remarks and Explanations |
| --- | --- | --- |
| [1.2.4 Captions (Live)](https://www.w3.org/TR/WCAG21/#captions-live) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.2.5 Audio Description (Prerecorded)](https://www.w3.org/TR/WCAG21/#audio-description-prerecorded) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.3.4 Orientation](https://www.w3.org/TR/WCAG21/#orientation) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.3.5 Identify Input Purpose](https://www.w3.org/TR/WCAG21/#identify-input-purpose) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.3 Contrast (Minimum)](https://www.w3.org/TR/WCAG21/#visual-audio-contrast-contrast) | <ul><li>**Web**: Supports</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul><li>**Web**: Automated contrast checks (axe-core, July 2026) pass across the audited page inventory. A failing admin accent color (4.48:1) reachable by members on /my-content was darkened to 5.3:1 (fixed July 2026). Elements automated tools cannot judge (headings over images, map controls) are queued for manual contrast measurement.</li> </ul> |
| [1.4.4 Resize text](https://www.w3.org/TR/WCAG21/#visual-audio-contrast-scale) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.5 Images of Text](https://www.w3.org/TR/WCAG21/#visual-audio-contrast-text-presentation) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.10 Reflow](https://www.w3.org/TR/WCAG21/#reflow) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.11 Non-text Contrast](https://www.w3.org/TR/WCAG21/#non-text-contrast) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.12 Text Spacing](https://www.w3.org/TR/WCAG21/#text-spacing) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [1.4.13 Content on Hover or Focus](https://www.w3.org/TR/WCAG21/#content-on-hover-or-focus) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.4.5 Multiple Ways](https://www.w3.org/TR/WCAG21/#multiple-ways) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.4.6 Headings and Labels](https://www.w3.org/TR/WCAG21/#headings-and-labels) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [2.4.7 Focus Visible](https://www.w3.org/TR/WCAG21/#focus-visible) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.1.2 Language of Parts](https://www.w3.org/TR/WCAG21/#language-of-parts) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.2.3 Consistent Navigation](https://www.w3.org/TR/WCAG21/#consistent-navigation) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.2.4 Consistent Identification](https://www.w3.org/TR/WCAG21/#consistent-identification) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.3.3 Error Suggestion](https://www.w3.org/TR/WCAG21/#error-suggestion) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [3.3.4 Error Prevention (Legal, Financial, Data)](https://www.w3.org/TR/WCAG21/#error-prevention-legal-financial-data) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |
| [4.1.3 Status Messages](https://www.w3.org/TR/WCAG21/#status-messages) | <ul><li>**Web**: Not Evaluated</li><li>**Authoring Tool**: Not Evaluated</li> </ul> | <ul> </ul> |


## Legal Disclaimer
The information herein is provided in good faith based on the analysis of the product at the time of the review and does not represent a legally-binding claim. Please contact us to report any accessibility errors or conformance claim errors for re-evaluation and correction, if necessary.

## Repository
https://github.com/MukurtuCMS/Mukurtu-CMS

## Feedback
https://github.com/MukurtuCMS/Mukurtu-CMS/issues

## Related OpenACRs
- https://github.com/GSA/openacr/blob/main/openacr/drupal-9.yaml (secondary)

## Copyright

[OpenACR](https://github.com/GSA/openacr) is a format maintained by the [GSA](https://gsa.gov/). The content is the responsibility of the author.

This content is licensed under a [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0-standalone.html).
