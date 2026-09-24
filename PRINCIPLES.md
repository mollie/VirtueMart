# PRINCIPLES.md — how work is done in this repository

Read this together with `CLAUDE.md` (repo map, local rules, sources of truth). It encodes the
working agreement, the spec-driven development flow and the engineering principles that every
change must follow, whether a human or an AI wrote it. Where `CLAUDE.md` or `.claude/rules/` is more
specific, it wins; this file covers everything in between.

## 1. Working agreement

1. **Plan before code.** For any non-trivial change, explore first, then present a plan. Surface every
   real design decision as an explicit question with 2–4 options and their trade-offs; never pick one
   silently. Once the developer chooses, that choice is fixed and goes into the spec/plan verbatim.
2. **Artifacts are in English.** Code, comments, docs, specs, plans and commit messages are all in English.
3. **Report reality.** Run the verification and paste the real result. Never claim a green result
   without running it. If something failed or was skipped, say so first, not last.
4. **No hallucination.** Read a file before editing it, and grep before referencing it. Never invent a
   Joomla, VirtueMart or Mollie API, file, class, trigger or dependency. For Mollie API questions, use
   https://docs.mollie.com/reference/overview. For Joomla and VirtueMart questions, the sources of
   truth are, in this order:
   1. **The local installation's source files**, when a Joomla / VirtueMart shop is available in the
      development environment (for example `libraries/src/`,
      `administrator/components/com_virtuemart/`). Read these first, instead of searching online for
      code that already exists locally. Check that its version is in the supported range (Joomla
      `libraries/src/Version.php`, VirtueMart `administrator/components/com_virtuemart/version.php`).
      If the location isn't known, ask the developer once for the path.
   2. **The official online source and documentation** of the supported versions (Joomla 5.3,
      VirtueMart 4), listed in `CLAUDE.md` § Official references, when there is no local installation
      or its version doesn't match.

   Never answer from memory, and never state platform behaviour as fact without a cited source (file
   + version, or URL): either check it or mark it "not verified". Never derive project
   facts or constraints from IDE or developer-environment files (`.idea/`, `.vscode/`, local
   settings). Use the Joomla manifests, `build.sh`, the README and the code.
5. **Ask when a rule conflicts.** If an instruction conflicts with a rule here or in `.claude/rules/`,
   follow the developer but name the rule being overridden.
6. **Consult `LEARNINGS.md` before planning** (§4.4), and append to it whenever the developer
   corrects an approach.
7. **The developer owns git history and the remote.** All work happens in the local repository. The
   developer decides when to push (§3).

## 2. The development flow (spec-driven, mandatory)

Every non-trivial task goes through these phases, in order. The artifacts live in
`docs/specs/<feature>/`. A trivial change (typo, a one-line fix with an obvious cause, a
docs-only edit) may skip Research and Spec, but it still gets a light plan (§2.3) and the full
Verify + Review phases.

| # | Phase | Output | Gate to the next phase |
|---|---|---|---|
| 1 | Research | `docs/specs/<feature>/research.md` | Findings cite files/lines; open questions listed |
| 2 | Spec | `docs/specs/<feature>/spec.md` | No open decision remains; the developer confirms the spec |
| 3 | Plan | `docs/specs/<feature>/plan.md` + `tasks.md` | **The developer approves the plan.** No production code before this. |
| 4 | Implement | Local commits, one per task | Each task's own verification is green before its commit |
| 5 | Verify | Real gate output | All gates green (§2.5) |
| 6 | Review | Findings resolved | `/code-review` → `/simplify` → `/security-review` clean |
| 7 | Hand-off | Change summary to the developer | The developer decides when to push |

### 2.1 Research

- Read-only. Explore the code paths the task touches: entry points (plugin triggers, AJAX tasks,
  component controllers), wrapper services, core calls, and Joomla / VirtueMart APIs used. For broad
  sweeps, use parallel read-only subagents (Explore) and keep only their conclusions.
- Check the task against `DESIGN.md` (components, flows, constraints), `LEARNINGS.md` and the
  Mollie API reference.
- When the task depends on how Joomla or VirtueMart behave (what a trigger receives, whether a core
  controller checks ACL/CSRF, what an installer enforces), read that code: the local installation
  first, otherwise the official source (§1.4, `CLAUDE.md` § Official references). Cite file and version/revision in `research.md`. Anything that
  could not be checked is listed as "not verified", together with how to verify it.
- `research.md` records what exists today (with file paths), what the change will affect, the risks,
  and the questions the spec has to answer. Describe what is there, not what should be there.

### 2.2 Spec

- Interview the developer (AskUserQuestion) in rounds until no open decision remains: goals, scope
  and out-of-scope, acceptance criteria, payment methods affected, data/config migration, whether the
  change needs a core change (§5.8), and backward compatibility for merchants who upgrade in place.
- Also ask whether implementation may be delegated to subagents in parallel waves (default: yes).
- `spec.md` holds: problem and goal, scope and out of scope, acceptance criteria (observable and
  testable), decisions with a one-line *why* each, and open risks.

### 2.3 Plan

- Follow `.claude/plan-template.md`. It must include the **architecture-impact classification**
  (architectural vs business-case-only) and **Risks & emphasis**, which are mandatory.
- **Architectural changes update `DESIGN.md`, including its diagrams, in the same pass.**
- `tasks.md` is the task graph. Each task has an ID, a goal, the files it touches, its verification,
  and `blockedBy` (only where the dependency is real). Group independent tasks into parallel waves.
  Also register the tasks in the task tools (`TaskCreate`/`TaskUpdate`), so progress is visible and
  survives a restart. `tasks.md` stays the durable record.
- `.claude/hooks/plan-gate.sh` enforces the mandatory headings (Architecture impact, Risks &
  emphasis, Verification) on `ExitPlanMode` and on every write of `plan.md`.
- Implementation starts only after the developer explicitly approves the plan.

### 2.4 Implement

- Work on a **local** branch created from `main`. The default name is `feature/<feature>` (bug fixes:
  `bugfix/<ticket>`, as in `bugfix/PISYL-321`); the developer may choose another. Never push.
- The main agent orchestrates, and subagents execute independent tasks in parallel waves when the
  spec allows delegation. Every subagent result is checked against the actual diff before it is accepted.
- **One commit per task**, made only after that task's verification is green. Commit rules are in §3.
- Code follows `CLAUDE.md` § Local rules, `.claude/rules/**` and §5. Keep changes surgical: touch only
  what the task needs.
- **Tests come first where a test harness exists.** Write the failing test, watch it fail for the right
  reason, then implement. The repo has no PHP test harness yet. Until it does, each task in `tasks.md`
  names its concrete manual verification (steps plus expected result on a Joomla 5.3 + VirtueMart 4
  install) before the code is written.

### 2.5 Verify (definition of done)

1. **Syntax gate** is green on every changed PHP file:
   `php -l` (the command is in `CLAUDE.md` § Commands). Where available, run it on both PHP 8.1 and
   the highest PHP version Joomla 5 supports.
2. **PHP 8.1 floor** holds: no syntax or functions newer than PHP 8.1 in changed files, and no
   deprecated patterns of later versions (`CLAUDE.md` § Local rules).
3. **Build and manual verification**: `build.sh` produces the package, it installs on a Joomla 5.3 +
   VirtueMart 4 shop, and the plan's manual verification is executed there. Report the result as
   observed. If it could not be run, say so explicitly; the change is then not done.
4. **Security checklist** at the end of `.claude/rules/_core/asvs-l2.md` is answered for the change.
5. **Docs are in sync** (§4): `DESIGN.md`, `CLAUDE.md`, `.claude/rules/**`, the plan template and
   `LEARNINGS.md`, wherever the change touched a convention.

### 2.6 Review

Run these in order on the feature diff, and resolve or explicitly answer every finding:

1. `/code-review`: correctness, alignment with `DESIGN.md`, the Risks & emphasis dimensions.
2. `/simplify`: reuse, simplification, efficiency. Fixes are applied and the gates re-run.
3. `/security-review`: `.claude/rules/_core/asvs-l2.md`, `owasp-2025.md` and the per-language rules.

Check each finding against the code before acting on it. A wrong finding gets a technical answer,
not a change. Review fixes are committed like any other task (§3).

### 2.7 Hand-off

Present the developer with a summary: the commits (hash + subject), the files changed, and the
verification that was run with its real results, plus any gaps. **Stop there.** The developer
reviews and decides when to push, or pushes themselves.

### 2.8 Resume

A session that continues a feature starts from `docs/specs/<feature>/` (`spec.md`, `plan.md`,
`tasks.md`) and `git log`, never from memory. Before continuing, update `tasks.md` to reflect the
commits that already exist.

## 3. Git rules

1. **Local only.** Never `git push`, never open PRs, never change remotes, unless the developer
   explicitly asks for that specific action.
2. **Never change the author.** Commit with the repository's configured git user. Never pass `--author`,
   and never change `user.name` / `user.email`.
3. **Short commit messages in the existing style.** One short line: an imperative subject in sentence
   case, with no trailing period, no body, no trailers (no `Co-Authored-By`, no "Generated with"), no
   ticket or emoji prefixes. Examples in the style of the history:
   - `Fix payment creation crash with unrestricted Mollie method`
   - `Remove Zone.Identifier files`
   - `Initial code commit`
4. **One task, one commit.** Don't amend or rewrite commits that already exist unless the developer asks.
5. **Never commit secrets** (API keys, including test keys) or developer-environment files (`.idea/`, local settings).
6. **Versions move in lockstep, in the change that ships.** A change that goes into a release bumps the
   version in all five places in the same commit: `build.sh` `VERSION`, `pkg_mollie.xml`,
   `plugin/vmpayment/mollie/mollie.xml`, `administrator/components/com_mollie/mollie.xml` and
   `ConfigurationService::VERSION`. The release itself is the merge to `main` plus a `vX.Y.Z` tag, both
   made by the developer.

## 4. Documentation discipline

1. **Docs describe the current state.** They say how the code works *now*. The reasoning behind
   significant architecture decisions goes in `DESIGN.md` as short ADRs (What / Why / Cost, numbered).
2. **Docs move in the same pass as the change.** When a flow, component or convention changes, update
   its section and diagrams in `DESIGN.md`. Grep for old names; every hit left in current prose is a bug.
3. **Instruction files are docs too.** When a task changes a convention (a local rule, a gate, a code
   pattern), update its home in the same pass: `CLAUDE.md`, `PRINCIPLES.md`,
   `.claude/plan-template.md` or `.claude/rules/**`. If the convention has no home yet, create one.
4. **`LEARNINGS.md` is the running record of corrections.** Consult it before planning, and append an
   entry whenever the developer corrects an approach, an assumption proves wrong, or a trap costs
   time. Entry format:

   ```markdown
   ## YYYY-MM-DD — <one-line title>

   - **Symptom:** what went wrong or was corrected
   - **Correction:** what the developer said or what turned out to be true
   - **Rule going forward:** the rule, stated so it can be enforced
   ```

   It is append-only. When a learning becomes a permanent rule, it is also added to its home (§4.3).
5. **Defects and improvement proposals don't go into `DESIGN.md` or `CLAUDE.md`.** `DESIGN.md` §9
   lists only binding constraints of the setup. Found defects, security debts and refactoring ideas
   are reported to the developer, who decides where they are tracked.

## 5. Engineering principles

1. **Simplicity first.** Prefer the simplest solution that solves the actual problem well. Don't add
   layers, abstractions, registries, factories or configuration without a clear and immediate reason.
2. **KISS.** Keep the code simple, explicit and readable. A developer should be able to follow the flow
   without learning a custom framework.
3. **YAGNI.** Don't build for hypothetical future use. Mention a future option instead of implementing it.
4. **SOLID, pragmatically.** Apply it where it improves maintainability and testability, never
   mechanically. Every abstraction needs a concrete responsibility.
5. **Simplify before wrapping.** Check whether existing code can be simplified (moved, renamed,
   reduced) before adding a new abstraction around it.
6. **Clear responsibilities.** Don't mix reading, mapping, validating, transforming, writing and
   Joomla/VirtueMart-specific behaviour in one unit without reason. In this repo that means the plugin
   triggers in `mollie.php` and the component controllers stay thin, controllers return a `Response`
   DTO, business logic lives in the wrapper services (`src/<Area>/`) or the core, and VirtueMart data
   access lives in the repositories and adapters.
7. **No hidden magic.** Behaviour must be traceable. Registration goes explicitly through
   `Bootstrap::initServices()` / `initRepositories()`, and services are resolved through
   `ServiceRegister::getService()`.
8. **Respect the layering.** Joomla/VirtueMart entry points → wrapper (`src/`) → copied core. The core
   calls back only through the registered integration interfaces. Never edit
   `plugin/vmpayment/mollie/core/` in place. A change the core should make is made in
   `mollie/integration-core`, released there, and then re-copied here in full.
9. **Preserve the existing project style.** Match naming, namespaces, the DI style, file layout and
   comment density. A diff should read as if the original author wrote it.
10. **Backward compatibility for merchants.** Merchants upgrade in place. Existing configuration
    (`ConfigEntity` rows), `#__mollie_entities` data, VirtueMart payment-method params, order statuses
    (including `AM`) and order references must keep working after an upgrade. Plan the migration path explicitly when something must change.
11. **Incremental refactoring.** No big-bang rewrites. Make small, safe steps that preserve behaviour.
12. **Optimal by default.** Avoid N+1 queries and per-row round-trips. Read only what is used. Don't
    make extra Mollie API calls per page load or webhook. Minimise passes over collections. State the
    request/query count when it matters.
13. **Security by default.** It is weighed in every plan (Risks & emphasis) and reviewed on every
    change (`.claude/rules/`). `strict` rules are never traded for convenience.
14. **Make behaviour verifiable.** Design changes so they can be verified by a test or a concrete
    manual check.
15. **Explain trade-offs.** When several solutions fit, compare them briefly and choose the one with the
    lowest complexity that meets the requirement.

## 6. Decision heuristics

| Situation | Default |
|---|---|
| Add abstraction vs inline | Inline until the third occurrence |
| New dependency vs existing code | Existing code / core service |
| Platform helper vs custom code | The platform's own helper: VirtueMart helpers (`vRequest`, `vmText`, `VmHTML`, `vmJsApi`, `VmModel`) in VirtueMart contexts, Joomla APIs in `com_mollie`. Custom code only when neither provides it, after checking in the source what the helper really does |
| `new Service()` vs `ServiceRegister` | `ServiceRegister` (register in `Bootstrap`) |
| Raw SQL string vs query builder | Joomla query builder with `quoteName()` / `bind()`; order status via the VirtueMart orders model |
| Change the core vs work around it in the wrapper | Ask; a core change goes to `mollie/integration-core` first, never into the copy |
| Break config/data vs migrate | Migrate, so merchants upgrading in place keep working |
| Is this done? | Only if the gates are green, all three reviews are clean, and the docs match reality |
