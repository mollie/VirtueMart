# CLAUDE.md

## What this repository is

The Mollie payment package for **Joomla 5.3 + VirtueMart 4**. Only one branch, `main`, is maintained.
All work lands there. The package (`pkg_mollie.xml`, built by `build.sh`) contains two Joomla
extensions:
- the VirtueMart payment plugin `plg_vmpayment_mollie` (`plugin/vmpayment/mollie/`). It lists Mollie
  methods in checkout, creates payments, and handles the redirect return and the webhook. It also adds
  the Mollie block with capture and refund to the admin order page, and cancels at Mollie when an
  admin cancels an order.
- the admin component `com_mollie` (`administrator/components/com_mollie/`). It handles the API-key
  connection, status mapping and log level. It also installs the VirtueMart payment-method edit
  override.

Business logic lives in a **copy** of the shared core `mollie/integration-core` under
`plugin/vmpayment/mollie/core/`, with the original namespaces and an integration-core 1.3.11 base. This
repo is the Joomla/VirtueMart **wrapper** that implements the core's integration interfaces. See
`DESIGN.md`.

## Architecture map

- `plugin/vmpayment/mollie/mollie.php`: `plgVmPaymentMollie` (`vmPSPlugin`). Every VirtueMart trigger,
  plus the backend AJAX router `plgVmOnSelfCallBE`. It requires `include_mollie.php` and calls
  `Bootstrap::init()` at load.
- `plugin/vmpayment/mollie/include_mollie.php`: the autoloader (`Mollie\BusinessLogic|Infrastructure` →
  `core/`, `Mollie\Payment` → `src/`, `Mollie\Component` → `com_mollie/src/`).
- `plugin/vmpayment/mollie/src/`: wrapper code, `Mollie\Payment\…`
  - `Bootstrap.php`: the composition root (service and repository registration)
  - `Payment/`, `Capture/`, `Refund/`, `Cancel/`, `WebHook/`, `PaymentMethod/`: `Controller/` (returns
    a `Response` DTO) plus the service per area
  - `Adapter/`: core interface implementations and VirtueMart façades (`VirtueMartOrderTransitionService`,
    `JoomlaLoggerAdapter`, …)
  - `Configuration/`: `ConfigurationService` (core `Configuration`, `VERSION`), `PaymentMethodConfigService`, mappings
  - `Authorization/`: API-key connect and `EncryptionService`
  - `Repository/`: `BaseRepository` (`#__mollie_entities`), `PaymentMethodConfigRepository`, VirtueMart repositories
- `plugin/vmpayment/mollie/tmpl/`, `assets/`, `fields/`, `language/en-GB/`: layouts, storefront/admin-order JS and CSS, the form field, and strings
- `plugin/vmpayment/mollie/core/`: the copied `mollie/integration-core`. **Never edit in place.**
- `administrator/components/com_mollie/`: Joomla 5 MVC component (`services/provider.php`,
  `src/Controller`, `src/View/Configuration/HtmlView.php`, `tmpl/`, `sql/`, `script.php`), plus
  `assets/overrides/edit_edit.php` and `assets/js/` for the VirtueMart payment-method edit form
- `media/com_mollie/`: component CSS
- `pkg_mollie.xml`, `build.sh`: the package manifest and the build script

## Where the instructions live

| Scope | File | What it covers |
|---|---|---|
| **Working agreement + development flow** | `PRINCIPLES.md` | Plan first with explicit design options, the spec-driven flow (research → spec → plan → implement → verify → review → hand-off), git rules, documentation discipline, engineering principles. **Mandatory for every change; read it first.** |
| **Architecture** | `DESIGN.md` | The living architecture record: layers, components, domain model, key flows (with ownership labels), module map, patterns, boundaries, constraints. Updated in the same pass as any architectural change. |
| **Plan standard** | `.claude/plan-template.md` | The required sections of every `plan.md`, including Architecture impact and Risks & emphasis. |
| **Security** | `.claude/rules/` | See "MANDATORY sources of truth" below. |
| **Feature artifacts** | `docs/specs/<feature>/` | `research.md`, `spec.md`, `plan.md`, `tasks.md` for each feature. This is the source for resuming work. |
| **Corrections** | `LEARNINGS.md` | Append-only record of corrections and traps. Read it before planning. Format in `PRINCIPLES.md` §4.4. |

## Commands

There is no `composer.json`, no test suite, no linter, no static analysis and no CI in this repo.

```bash
# build the installable package (pkg_mollie_<VERSION>.zip in the repo root; *.zip is git-ignored)
./build.sh

# PHP syntax check (not configured in repo — ad hoc; the core copy is not edited, so it is skipped)
find plugin/vmpayment/mollie administrator/components/com_mollie -name '*.php' \
  -not -path 'plugin/vmpayment/mollie/core/*' -print0 | xargs -0 -n1 php -l
```

Verification is manual on a Joomla 5.3 + VirtueMart 4 shop:
1. Run `./build.sh`.
2. Go to System » Install » Extensions, upload `pkg_mollie_<VERSION>.zip` and enable the plugin.
3. Go to Components » Mollie and connect with a test API key.
4. Create a VirtueMart payment method with the element `mollie`, then exercise the flow under test.

## Local rules

- **PHP floor is 8.1** (`Com_MollieInstallerScript::$minimumPhp`, and Joomla 5 itself). PHP 8.1 syntax
  is used (`readonly` promoted properties, `match`, nullsafe, named arguments). Don't use anything
  newer than 8.1, and avoid patterns deprecated in later versions (for example implicit nullable
  parameters: write `?Type $x = null`).
- **The core copy is never edited in place.** A core change is made in `mollie/integration-core`,
  released there, and re-copied into `plugin/vmpayment/mollie/core/` in full. The re-copy is reviewed
  as a diff. Wrapper-specific interfaces and classes live in `src/`, not in `core/`.
- **Resolve dependencies through `Bootstrap::init()` + `ServiceRegister`.** Register services in
  `Bootstrap::initServices()` (entities in `initRepositories()`) and resolve them with
  `ServiceRegister::getService(X::CLASS_NAME)` or `X::class`. Pass dependencies as `private readonly`
  promoted constructor properties. Avoid `new` for services, and new `getInstance()` singletons.
- **Database access uses the Joomla query builder**: `Factory::getContainer()->get(DatabaseInterface::class)`,
  `createQuery()`, `quoteName()`, `bind()` / `quote()` / `(int)`. VirtueMart order status changes go
  through `VmModel::getModel('orders')->updateStatusForOneOrder()`, never through direct SQL.
- **Keep the entry points thin.** `mollie.php` triggers and the `com_mollie` controllers validate input,
  call a wrapper controller or service, and turn its `Response` into a redirect, message, layout or
  `JsonResponse`. Every VirtueMart trigger first checks that the method is its own
  (`getVmPluginMethod()` + `selectedThisElement()`).
- **Admin template override.** `com_mollie/script.php` copies `assets/overrides/edit_edit.php` over
  VirtueMart's payment-method edit template in the default admin template. A change to that form is a
  change to the override, and it reaches shops only through the installer's `postflight`.
- **Strings** go into `language/en-GB/*.ini` (`PLG_VMPAYMENT_MOLLIE_*`, `COM_MOLLIE_*`) and are used
  through `Text::_()` / `vmText`. en-GB is the only language.
- **Payments API only.** Checkout uses the Mollie Payments API (`PaymentMethodConfig::API_METHOD_PAYMENT`).
  The selectable methods are `SupportedPaymentMethods::SUPPORTED`.
- **Versions move in lockstep, in the change that ships.** Bump all five: `build.sh` `VERSION`,
  `pkg_mollie.xml`, `plugin/vmpayment/mollie/mollie.xml`, `administrator/components/com_mollie/mollie.xml`
  and `ConfigurationService::VERSION`. The developer merges to `main` and tags `vX.Y.Z`.

## Official references (use them when researching)

Check every claim about Joomla, VirtueMart or Mollie behaviour in the source or the official
documentation, and cite it (file + version/revision, or URL). **Read a local Joomla / VirtueMart
installation first** when one is available in the development environment, and use the online
sources below only when there is none or its version doesn't match (`PRINCIPLES.md` §1.4). **Never
assert platform behaviour from memory or by inference.** If it can't be checked, say "not verified". A plausible claim can still be
wrong: whether VirtueMart checks ACL and CSRF before calling `plgVmOnSelfCallBE` was only settled by
reading `controllers/plugin.php`. It checks ACL but not the token.

| Topic | Official source | Notes |
|---|---|---|
| Mollie API | https://docs.mollie.com/reference/overview | Endpoints, payloads, statuses, webhooks |
| Joomla source | https://github.com/joomla/joomla-cms | Read the tag of the supported version (for example `5.3.0`); raw files via `https://raw.githubusercontent.com/joomla/joomla-cms/<tag>/<path>` |
| Joomla developer manual | https://manual.joomla.org/ (extensions: https://manual.joomla.org/docs/building-extensions/) | Joomla 4/5 extension development, MVC, DI, installer scripts |
| Joomla API reference | https://api.joomla.org/cms-5/ | Classes and signatures for Joomla 5 |
| Joomla developer network | https://developer.joomla.org/ | Release notes, security announcements, coding standards |
| Joomla documentation wiki | https://docs.joomla.org/ | User and older developer docs; may block automated fetches |
| VirtueMart source (SVN) | https://dev.virtuemart.net/svn/virtuemart/trunk/virtuemart/ | Public, no login; browse or `curl` single files. Release tags stop at old versions; VM 4 lives in `trunk` and `branches/com_virtuemart.4.4/`. The release ZIP (below) is the exact shipped code |
| VirtueMart developer portal | https://dev.virtuemart.net/ | Issue tracker, release packages as attachments |
| VirtueMart documentation | https://docs.virtuemart.net/ | Including the SVN guide: https://docs.virtuemart.net/tutorials/development/100-svn-download.html |
| VirtueMart downloads | https://virtuemart.net/downloads | Official release packages |

Unofficial mirrors (for example Git mirrors of the VirtueMart SVN), community forums and community AI
skills are not sources of truth. At most they are pointers to the official source, which is then read.

## MANDATORY sources of truth

The security rules live in `.claude/rules/`. One of them, `_core/owasp-2025.md`, is copied from
[TikiTribe/claude-secure-coding-rules](https://github.com/TikiTribe/claude-secure-coding-rules). It is
MIT-licensed; the details are in `.claude/rules/THIRD-PARTY-NOTICES.md`. The other files
(`_core/asvs-l2.md`, `languages/php/CLAUDE.md`, `languages/javascript/CLAUDE.md`) were written for this
repository. They cover the package's real surface: PHP in Joomla/VirtueMart, and browser-only
JavaScript.

### Always applied (every change, every file)

| Rule set | File | Why mandatory |
|---|---|---|
| **OWASP ASVS v5.0 — Level 2** | `.claude/rules/_core/asvs-l2.md` | The verification standard this package is held to (payments, merchant credentials, shopper PII). Walk the checklist at the bottom before declaring any work done. |
| **OWASP Top 10 (2025)** | `.claude/rules/_core/owasp-2025.md` | Cross-cutting controls for the most common web vulnerabilities. |

### Applied per stack (path-scoped, loaded automatically when a matching file is read)

| Stack | Rule file | Loads when reading |
|---|---|---|
| PHP (plugin + component) | `.claude/rules/languages/php/CLAUDE.md` | `plugin/vmpayment/mollie/{*.php,src,tmpl,fields}/**`, `administrator/components/com_mollie/**/*.php` (not `core/`) |
| JavaScript | `.claude/rules/languages/javascript/CLAUDE.md` | `plugin/vmpayment/mollie/assets/js/**`, `administrator/components/com_mollie/assets/js/**` |

The per-stack files are required reading for a change in their scope, even before they load
automatically.

### Enforcement levels (used inside the rule files)

- `strict`: refuse to generate violating code. No "but it works" exceptions.
- `warning`: generate the code, but flag the issue and propose an alternative in the same response.
- `advisory`: mention it as a best practice.

### Conflicts

If a rule conflicts with an explicit developer instruction, follow the developer but **name the
rule being overridden**.

## Tooling

- **Built-in review skills**: `/code-review`, `/simplify` and `/security-review` are the mandatory
  review sequence (`PRINCIPLES.md` §2.6).
- **Plugin**: `.claude/settings.json` enables `security-guidance@claude-plugins-official` from the
  built-in official marketplace. It warns about risky patterns while editing. No other marketplace is
  required.
- **Only official plugins and skills.** The repo references only built-in Claude Code skills and
  plugins from the official Anthropic marketplace (`claude-plugins-official`). Community marketplaces,
  plugins and skills (including Joomla or VirtueMart ones) are never added to `.claude/settings.json`
  or referenced from the instruction files. Platform knowledge comes from § Official references.
- **Task tools**: `.claude/settings.json` sets `CLAUDE_CODE_ENABLE_TODO_TOOLS=1`, so
  `TaskCreate`/`TaskUpdate`/`TaskList` are available for the task graph (`PRINCIPLES.md` §2.3). The
  tools bind at session start, so restart Claude Code after changing this setting.
- **Plan-section gate**: `.claude/hooks/plan-gate.sh`, registered in `.claude/settings.json`,
  denies `ExitPlanMode` and blocks a written `docs/specs/<feature>/plan.md` when the plan is missing
  one of the headings **Architecture impact**, **Risks & emphasis** or **Verification**. It reads the
  hook JSON with `python3`, or `php` as a fallback. If neither is installed, it lets the plan through
  and prints a warning.
- **Permissions**: `git push`, `git commit --amend`, `git rebase` and `git reset --hard` always ask
  first. Reading `.env*`, keys and `auth.json` is denied.
- **Per-developer settings** go into `.claude/settings.local.json`, which is git-ignored.

## Development flow (mandatory)

Every task goes through the spec-driven flow in `PRINCIPLES.md` §2, in every session and without being
asked. Skipping a phase gate is a defect, not a shortcut.

| Phase | What happens | Artifact / gate |
|---|---|---|
| Research | Read-only exploration of the affected code, `DESIGN.md`, `LEARNINGS.md` and the Mollie API reference; parallel read-only subagents for broad sweeps | `docs/specs/<feature>/research.md` |
| Spec | Interview the developer (AskUserQuestion) until no decision is open, including core impact, migration and delegation | `docs/specs/<feature>/spec.md`, confirmed by the developer |
| Plan | Per `.claude/plan-template.md`: architecture impact, Risks & emphasis, task graph | `plan.md` + `tasks.md`; **the developer approves before any code** |
| Implement | Local `feature/<feature>` or `bugfix/<ticket>` branch from `main`; subagents in parallel waves where allowed; **one short commit per task**, author unchanged | Each task's verification green before its commit |
| Verify | `php -l` on changed files, the PHP 8.1 floor, `./build.sh`, the plan's manual verification, the ASVS checklist, docs in sync | Real output reported |
| Review | `/code-review` → `/simplify` → `/security-review`; every finding verified against the code | All findings resolved or answered |
| Hand-off | Summary of commits, files and verification to the developer | **No push**: the developer decides when to push |
| Resume | Continue from `docs/specs/<feature>/` + `git log`, never from memory | `tasks.md` updated first |

### Git rules (summary of `PRINCIPLES.md` §3)

- Everything stays in the local repository. Never push, open PRs or touch remotes unless the developer
  asks for that specific action.
- Never change the commit author. Use the configured git user, with no `--author`.
- Commit messages are one short line in the existing style (`Fix payment creation crash with
  unrestricted Mollie method`, `Remove Zone.Identifier files`), with no body, no `Co-Authored-By` and
  no other trailers.

## When you need to write code

1. Re-read `PRINCIPLES.md`, `LEARNINGS.md`, the relevant `DESIGN.md` sections and the applicable rule
   files (`asvs-l2.md` + the stack file). Don't work from memory.
2. Follow the development flow above. For a non-trivial change, research, spec and plan come
   before code.
3. Generate code that satisfies the `strict` rules unconditionally. If you cannot, stop and ask.
4. Verify before claiming done, and report the real results.
5. Run the review sequence and resolve the findings.
6. Update the docs and instruction files in the same pass (`PRINCIPLES.md` §4), and append any
   correction to `LEARNINGS.md`.
