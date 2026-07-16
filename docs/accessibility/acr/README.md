# Mukurtu CMS OpenACR (Accessibility Conformance Report)

[mukurtu-acr.yaml](mukurtu-acr.yaml) is Mukurtu's accessibility conformance report in [OpenACR](https://github.com/GSA/openacr) format — the GSA's machine-readable successor to the VPAT. It covers all WCAG 2.1 Level A and AA Success Criteria with two components per criterion:

- **web** — the visitor and logged-in member experience (current audit scope)
- **authoring-tool** — content creation and administration (later phase; will also be measured against ATAG 2.0)

Every criterion starts as `not-evaluated`. Levels are updated during the triage step of each audit cycle (see the [program charter](../README.md)). Allowed levels: `supports`, `partially-supports`, `does-not-support`, `not-applicable`, `not-evaluated`.

This file is the **living working copy** and tracks the development branch. At each tagged release, snapshot it as a release artifact (attach the YAML — and optionally the rendered markdown below — to the GitHub release), since a conformance claim describes a specific released version.

[catalog-2.4-edition-wcag-2.1-en.yaml](catalog-2.4-edition-wcag-2.1-en.yaml) is a vendored copy of the GSA's WCAG 2.1 catalog, kept alongside so validation is deterministic.

## Validating after edits

The OpenACR CLI requires Node 20+. The host default may be older, so run it in the DDEV web container:

```bash
# Run from the site project root (the profile lives at web/profiles/mukurtu):
ddev exec "cd web/profiles/mukurtu/docs/accessibility/acr && \
  npx -y -p @openacr/openacr openacr validate -f mukurtu-acr.yaml -c catalog-2.4-edition-wcag-2.1-en.yaml"
# Expected output: Valid!
```

(With Node 20+ on the host you can run the same `npx` command directly from this directory.)

## Rendering the human-readable report (release artifact)

`openacr output` renders the YAML into a VPAT-style markdown document — criterion
numbers with their names linked to the W3C spec (e.g. "2.4.2 Page Titled"),
per-component conformance levels with our notes, and summary count tables.
[openacr-markdown-0.1.0.handlebars](openacr-markdown-0.1.0.handlebars) is the
GSA's template, vendored here because the npm package doesn't resolve it on its
own (same reasoning as the vendored catalog).

```bash
# From the site project root; output lands in this directory.
ddev exec "cd web/profiles/mukurtu/docs/accessibility/acr && \
  npx -y -p @openacr/openacr openacr output -f mukurtu-acr.yaml \
    -c catalog-2.4-edition-wcag-2.1-en.yaml \
    -t openacr-markdown-0.1.0.handlebars \
    -o mukurtu-acr.markdown"
```

At each tagged release, generate this and attach **both** files to the GitHub
release: the YAML (machine-readable, canonical) and the markdown (for humans).
Don't commit the rendered markdown to the repo — it's a build artifact of the
YAML and would drift.

**Before publishing a release ACR:** a final conformance report should not
contain `not-evaluated` for Level A/AA criteria (VPAT reserves "Not Evaluated"
for AAA). While the working copy legitimately carries `not-evaluated` between
audit cycles, treat remaining ones as the to-do list that gates calling a
release's ACR complete.

## Structure of a criterion entry

```yaml
- num: 1.1.1
  components:
    - name: web
      adherence:
        level: partially-supports
        notes: >-
          Plain-language summary of what supports/fails, with links to the
          GitHub issues tracking each defect.
    - name: authoring-tool
      adherence:
        level: not-evaluated
        notes: ""
```

Follow the [Drupal-ACR](https://github.com/civicactions/Drupal-ACR) conventions for notes: state what works, what doesn't, and link the tracking issues so the report stays a living document.
