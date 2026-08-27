## Pre-merge checklist:

- [ ] I have checked for and if required, created update hooks.
- [ ] I have run an accessibility check, and resolved any issues.
- [ ] I have recompiled SCSS if required.
- [ ] I have updated or created CI/CD tests, if required.
- [ ] I have updated from `main` and resolved any merge conflicts.
- [ ] If this PR's base branch is not `main`, I understand it needs to be retargeted once that base branch's own PR merges (see [docs/stacked-prs.md](../docs/stacked-prs.md)).
- [ ] I have verified that all expected checks pass.
- [ ] I have run the pr-expert-review skill and resolved all relevant issues.
- [ ] If I added a content entity bundle, I added its `language.content_settings` and base field overrides to `mukurtu_multilingual` (see `docs/content-language-policy.md`). If I added or changed a view, I applied the content language policy in that doc.

