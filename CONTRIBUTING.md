# Contributing to Mukurtu CMS

Contributions and feedback are welcome, whether that is code, a bug report, or
telling us that something does not work the way your community needs it to.

- **Issues and feature requests**:
  [our issue tracker](https://github.com/MukurtuCMS/Mukurtu-CMS/issues).
- **Questions and support**:
  [support@mukurtu.org](mailto:support@mukurtu.org).
- **Security vulnerabilities**: do not open a public issue. See
  [SECURITY.md](SECURITY.md).

## Setting up a development environment

`README.md` covers installing a site. If you intend to work on the installation
profile itself, connect a git checkout to a new project following the
[additional installation steps on the wiki](https://github.com/MukurtuCMS/Mukurtu-CMS/wiki).

## Before opening a pull request

The pull request template lists the checks we expect. The ones that catch the
most problems:

- **Update hooks.** If you changed anything under a module's `config/install`,
  existing sites will not pick it up without a `hook_update_N()`. See
  [docs/update-hooks.md](docs/update-hooks.md) for placement and numbering, and
  note that `hook_update_N()` never runs on a fresh install, so the same end
  state has to be in `config/install` too.
- **Tests.** Kernel and unit tests run in CI on every pull request. New
  behaviour needs coverage, and the suites must pass.
- **Multilingual.** Mukurtu has an international user base. Wrap user-facing
  strings in `t()` or `$this->t()`; `scripts/lint/multilingual-guardrails.sh`
  enforces this in CI. If you add a content entity bundle or a view, read
  [docs/content-language-policy.md](docs/content-language-policy.md).
- **Accessibility.** Work should meet WCAG 2.1 AA, and ATAG 2.0 for authoring
  interfaces.
- **SCSS.** Compile it if you changed it, and do not minify `style.css`.

## Things that are easy to get wrong

Documented because each of these has cost someone real time:

- **Stacked pull requests.** Opening a pull request against another open pull
  request's branch is fine and useful, but the base must be retargeted before
  merging or the work merges into a stale branch and never reaches `main`. This
  has happened three times. See [docs/stacked-prs.md](docs/stacked-prs.md).
- **Patches.** Third-party patches are fetched by URL and pinned to a commit
  SHA. See [docs/composer-patches.md](docs/composer-patches.md), particularly
  for what happens when a release adds a patch file that an older site does not
  have yet.
- **Pinned dependencies.** Some constraints are deliberately narrow because a
  newer release is broken. Check the git history for a constraint before
  widening it.

## Style

Follow Drupal's [coding standards](https://www.drupal.org/docs/develop/standards)
and match the conventions of the code around you. Drupal 11 hook classes live in
`src/Hook/` with `#[Hook]` attributes; prefer those over procedural hooks,
except where a hook does not support them, such as `hook_install`,
`hook_schema`, `hook_requirements` and `hook_update_N`.
