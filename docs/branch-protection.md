# Branch protection for `main`

`main` is what runs in production. It only changes through pull requests that
pass CI.

**Status:** applied on 2026-11-26 as the repository ruleset `protect-main`
(Settings → Rules → Rulesets), with no bypass actors: the rules apply to
administrators too. Only the owner has write access; anyone else can read,
fork and propose pull requests, never push or merge.

## Rules

| Rule | Setting |
| --- | --- |
| Require a pull request before merging | on, 1 approval when there is a second maintainer; code owners from `.github/CODEOWNERS` |
| Dismiss stale approvals when new commits are pushed | on |
| Require status checks to pass | `Drupal (lint, tests, config)` and `Secret scan (gitleaks, full history)` |
| Require branches to be up to date before merging | on |
| Require conversation resolution | on |
| Require linear history | on (squash or rebase merges; the squash title follows Conventional Commits) |
| Block force pushes and deletion | on |
| Apply to administrators | on |

Dependabot pull requests follow the same rules. The required checks are
pinned to the GitHub Actions app, so a commit status from anything else cannot
satisfy them.

## Other repository settings (applied)

| Area | Setting |
| --- | --- |
| Merging | Squash merge only, title from the pull request; branches deleted after merge |
| Actions | Only GitHub-owned actions and `shivammathur/setup-php`; actions must be pinned to a full commit SHA |
| Actions token | Read-only by default; workflows cannot approve pull requests |
| Pull requests from forks | Workflows wait for owner approval for every external contributor |
| Secrets | Secret scanning and push protection on (plus gitleaks in CI) |
| Dependencies | Dependabot alerts and security updates on |
| Vulnerability reports | Private vulnerability reporting on (Security tab) |

## Apply with the GitHub CLI

Repository rulesets (Settings → Rules → Rulesets) can be created from the
command line. Rulesets and branch protection are enforced on public
repositories, and on private repositories only with a paid GitHub plan
(Pro, Team or Enterprise).

```bash
gh api --method POST repos/riaze/drupal-ai-portfolio/rulesets --input - <<'JSON'
{
  "name": "main",
  "target": "branch",
  "enforcement": "active",
  "conditions": { "ref_name": { "include": ["~DEFAULT_BRANCH"], "exclude": [] } },
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    { "type": "required_linear_history" },
    { "type": "pull_request", "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": true,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": true } },
    { "type": "required_status_checks", "parameters": {
        "strict_required_status_checks_policy": true,
        "required_status_checks": [
          { "context": "Drupal (lint, tests, config)" },
          { "context": "Secret scan (gitleaks, full history)" } ] } }
  ]
}
JSON
```

With a single maintainer, keep `required_approving_review_count` at 0 (you
cannot approve your own pull requests); raise it to 1 and enable code owner
review when a second maintainer joins.
