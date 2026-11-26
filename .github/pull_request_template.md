## What and why

<!-- One or two sentences. Link the issue if there is one. -->

## Type

<!-- The PR title must follow Conventional Commits, e.g. `feat(theme): add case study card`. -->

- [ ] feat
- [ ] fix
- [ ] docs / chore / ci / build / refactor / test

## Checklist

- [ ] `make lint` and `make test` pass locally
- [ ] Config changes are exported (`make export-config`) and `make config-check` is clean
- [ ] No secrets, credentials or personal data added (the gitleaks pre-commit hook ran)
- [ ] User-facing strings are translatable and translated (FR and EN)
- [ ] Accessibility checked for UI changes (keyboard, focus, contrast, alt text)
- [ ] Docs or ADR updated if behaviour or architecture changed

## How to test

<!-- Steps a reviewer can follow, e.g. `make setup && make import-content`, then visit /contact. -->
