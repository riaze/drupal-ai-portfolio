# Branch protection for `main`

`main` is what runs in production. It only changes through pull requests that
pass CI.

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

Dependabot pull requests follow the same rules.

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
