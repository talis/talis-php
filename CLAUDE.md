# CLAUDE.md

`talis/talis-php` (Composer package `talis/talis-php`) is a PHP client library for Talis service APIs. It bundles clients that used to be separate libraries, under `src/Talis/`:

| Namespace | Client for |
|---|---|
| `Persona` | Persona (OAuth, tokens, users, OAuth clients, request signing) |
| `Babel` | Babel (annotations) |
| `Critic` | Critic (reviews) |
| `EchoClient` | Echo (events) |
| `Manifesto` | Manifesto (file manifests and archives) |

The README describes this as an early development version whose API will change. Several Talis PHP apps depend on it through Composer (at least `rl-app`, `dc-app` and `persona-server`). Default branch: `master`.

**Risk:** this library handles token validation and request signing, and several production apps use it. A change is only picked up when a new version is tagged and an app updates, so treat any public API or exception change as breaking for consumers.

## Commands

Everything runs in Docker through compose services, from the repo root. Docker must be available.

| Task | Command |
|---|---|
| Install dependencies (builds the image if missing) | `docker compose run --rm init` |
| Lint (phplint + phpcs) | `docker compose run --rm lint` |
| Unit tests | `docker compose run --rm unittest` |
| Integration tests | `docker compose run --rm integrationtest` |
| All tests | `docker compose run --rm test` |
| Mess detector | `docker compose run --rm code-check` |
| Static analysis (phpstan) | `docker compose run --rm analyse` |
| Shell in the container | `docker compose run --rm local-dev` |

A full `unittest` run is fine locally (no network). Integration tests need Persona and are an ask-first item (Guardrails). Run one test from the shell service: `/vendor/bin/phpunit --filter <testName> test/unit/`.

## Tests

- `test/unit/` needs no network. `test/integration/` talks to Persona at `http://persona.talis.local` and needs a client with `su` scope.
- Persona comes from the `infra` repo, checked out at `$DEVELOPMENT_WORK_DIR/infra`. The engineer starts it in their own terminal (`./docker-compose-dev.sh persona-server` from that directory); do not start it yourself.
- The compose file defaults `PERSONA_TEST_OAUTH_CLIENT` and `PERSONA_TEST_OAUTH_SECRET` to the seeded local client, so no `.env` is needed with the standard local Persona. Override the host or client with `PERSONA_TEST_HOST`, `PERSONA_TEST_OAUTH_CLIENT`, `PERSONA_TEST_OAUTH_SECRET` (a `.env` file is git-ignored). The README explains how to create your own client; `su` scope cannot be granted through the API.
- `phpunit.xml` fails on warnings and converts notices and deprecations to exceptions, so a new deprecation breaks the suite.
- `test/keys/*.pem` are test fixtures for signature tests, not real keys.

## Conventions

- Support every PHP version CI tests, 7.3 to 8.5 (matrix in `.circleci/config.yml`; `composer.json` requires `php >=7.3`). Do not use syntax or functions newer than 7.3 without a polyfill. The local Docker image is PHP 7.3.
- Style is checked by `phpcs` (`phpcs.xml`); fix what `lint` reports rather than editing the ruleset.
- Add exception classes next to their client (for example `src/Talis/Persona/Client/`), following the existing pattern.
- A new public class needs a unit test in the matching `test/unit/<Namespace>/` directory.
- Do not edit `vendor/`, `build/` or `coverage/` (git-ignored, as is `composer.lock`). Change dependencies in `composer.json` and install through the `init` or `local-dev` service.

## Guardrails

- **Claude may run:** unit tests, lint, code-check and analyse from the Commands table, and read-only `git` commands.
- **Ask first:** integration tests (they call a local Persona and create OAuth clients and users there), and anything pointed at a Persona host other than `persona.talis.local` through `PERSONA_TEST_HOST`.
- **Never:** run integration tests against a staging or production Persona, put real OAuth client credentials in `.env` or the repo, or tag a release or change `version` in `composer.json` as a side effect of another change.

## Traps

- The README is partly stale. It says `docker compose exec mongo32` for the local Persona database, but the local infra Mongo container is `mongo36`. It still shows a Travis build badge (CI is CircleCI), and it mixes `PERSONA_TEST_*` with `PERSONA_OAUTH_*` names in one step. Prefer `PERSONA_TEST_*`, which `docker-compose.yml` and the tests use.
- The `lint` helper script at the repo root calls a `codecheck` service that does not exist (the service is `code-check`) and relies on `inotifywait`. Use the Commands table instead.
- `Dockerfile` pins PHP 7.3, so a feature working on a newer host PHP can still fail in the container or the CI matrix.

## Where other things live

- Workspace guidance (what the other Talis repos are, how the local stack fits together): `CLAUDE.md` and `LOCAL-DEV.md` in the workspace root repo `talis/talis-dev-root`, at `$DEVELOPMENT_WORK_DIR`.
- `README.md` - the longer setup walkthrough, including creating a custom OAuth client. `.circleci/config.yml` - exactly what CI runs.

## PR conventions

- Branch from `master`, one change per PR, and start the title with the ticket key (`TA-3547 - Add retry middleware ...`), as recent history does.
- Versions are git tags (the latest tag matches `version` in `composer.json`). Check how earlier releases were cut in the git history before releasing, and leave releasing to a person.
- CI (CircleCI) runs `composer lint` and the test report on every PHP version from 7.3 to 8.5; all must pass.
- Open as a draft when Claude-authored; a human reviews and merges.

## Skills

- `talis:ci` - Talis rules for reading and re-running CircleCI.
- `talis:pr-review-feedback` - work through review comments on a PR.

## Keeping this file current

If a change alters anything described here (commands, supported PHP versions, test setup), update this file in the same PR. Link detail rather than copying it.
