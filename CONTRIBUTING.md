# Contributing to Luminara CRM

Thanks for taking the time. Bug reports, fixes, translations and new agent actions are all welcome.

## Before you start

- For a bug, open an issue with the steps to reproduce it and what you expected.
- For a feature, open an issue first so we can agree on the shape before you write code.
- For a security problem, do not open an issue. Follow [SECURITY.md](SECURITY.md).

## Set up

You need Docker Desktop with Compose.

```bash
git clone https://github.com/TCWuraGroupCapital/Luminara-CRM.git
cd Luminara-CRM
docker compose up --build -d
```

Open http://127.0.0.1:8080/admin/login and sign in as `admin@example.com` with the password `admin123`. The README covers ports, settings and the Assistant key.

## Rules of the codebase

[AGENTS.md](AGENTS.md) has the full conventions. The ones that catch people out:

- New code goes in a package under `packages/Webkul/`. Follow the patterns of the existing packages.
- Every user-facing string is translated in all 10 locales (`ar`, `en`, `es`, `fa`, `ja`, `ko`, `pt_BR`, `tr`, `vi`, `zh_CN`). A test fails if a locale is missing a key.
- Every change adds a line to [CHANGELOG.md](CHANGELOG.md).
- An agent action that changes data must go through the approval rules in `Webkul\Agent`. Do not add a path that lets an agent skip them.
- Screens follow [docs/design.md](docs/design.md).

## Checks to run before you open a pull request

```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G
docker compose exec app php artisan test --compact
npm run test:js
bash bin/validate-skills.sh
```

CI runs the same checks, plus the Playwright browser tests, a secret scan and a container security scan.

## Pull requests

- Keep each pull request to one change, and say in the description how you tested it.
- Add or update tests for the behaviour you changed.
- By contributing you agree that your work is released under the project's [MIT licence](LICENSE).
