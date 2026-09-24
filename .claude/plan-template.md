# Plan authoring standard (`docs/specs/<feature>/plan.md`)

Every task plan in this repo MUST contain the sections below, in this order. A plan must be detailed
enough to implement without re-deriving decisions, and reviewable on its own. Keep prose tight and
prefer tables, diagrams and signatures over paragraphs.

The plan and its implementation comply with `PRINCIPLES.md`, `CLAUDE.md` § Local rules, `DESIGN.md`
and the security rules in `.claude/rules/`. **Reference those documents; never restate them in the plan.**

## Scope tiers

- **Light plan**: for a small change (docs-only, config tweak, rename, single-file bugfix). Include §1,
  §2 (if any decisions), §3, a one-line §4 and §10–§11. Skip the rest.
- **Full plan**: everything else (new payment method, new flow, schema/config change, core re-copy,
  multi-file change). Include all sections, in order.

## Required sections

1. **Title + summary.** What the task delivers, the spec it realizes
   (`docs/specs/<feature>/spec.md`), its prerequisites, and explicitly what is **out of scope**. The
   target branch is `main`. Say whether the change needs a `mollie/integration-core` release and a
   re-copy of `plugin/vmpayment/mollie/core/` (`PRINCIPLES.md` §5.8); if so, that is a prerequisite.

2. **Decisions.** The locked choices, each with a one-line *why* (plus the cost when it isn't obvious).
   Say where it reuses an existing mechanism (a core service, `Bootstrap` registration, an existing
   wrapper service, repository or VirtueMart trigger) instead of inventing one. Name any security
   rule it satisfies or overrides.

3. **Architecture impact.** Either **architectural** (new or changed component, flow, boundary,
   persistence, external call, or a change to the core) or **business-case-only**. If architectural,
   list the `DESIGN.md` sections and diagrams this plan updates in the same pass. Use the ownership
   labels `«virtuemart»` / `«joomla»` / `«mollie-virtuemart»` / `«mollie-core»` / `«mollie-api»` /
   `«mollie-js»`.

4. **Risks & emphasis** *(mandatory)*. State the risk and its mitigation for each of these:
   - **Security**: input validation, output escaping in PHP layouts, SQL via the query builder, admin
     ACL and site order ownership, `Session::checkToken()`, triggers limited to this plugin's
     methods, file uploads, webhook authenticity and idempotency, secrets/PII in logs
     (`.claude/rules/_core/asvs-l2.md` checklist).
   - **Money and state correctness**: amounts, taxes, rounding, currency, the sum of lines versus the
     payment amount, and order-status transitions (status mapping).
   - **Backward compatibility**: existing `ConfigEntity` rows, `#__mollie_entities` data, VirtueMart
     payment-method params, the `AM` order state, the admin template override, order references, and
     the install/update path (`script.php` postflight, `sql/`) for merchants upgrading in place.
   - **Compatibility**: the PHP 8.1 floor, the Joomla 5.3 and VirtueMart 4 APIs used, and the
     VirtueMart template that the `edit_edit.php` override replaces.
   - **Performance**: DB queries per page load, Mollie API calls per request or webhook (checkout
     page, AJAX lookups, payment creation), and complexity over order lines.
   - **Alignment**: `DESIGN.md` layering (entry point → wrapper → core), `ServiceRegister` DI, and the
     core copy staying unedited.

5. **Model / flow diagrams** (full plan). The entities touched (with ownership labels) and the runtime
   flows added or changed, as Mermaid `classDiagram` / `sequenceDiagram` in the `DESIGN.md` style.

6. **Persistence changes** (when it touches data). New or changed `ConfigEntity` names,
   `#__mollie_entities` types and indexes, VirtueMart tables or plugin params written, and how
   existing shops are migrated (installer `postflight`, `sql/` files, runtime upgrade).

7. **Class outline.** For each changed or new class: its path, namespace, responsibility, the
   signatures of its key members, and its `Bootstrap` registration if it has one.

8. **Files.** The exact files to create or edit. For repeated patterns (for example a new payment
   method: `SupportedPaymentMethods`, `PaymentMethodDisplayName`, capture restrictions, language
   strings), describe the pattern once and list representative files.

9. **Tasks.** The task graph that goes into `tasks.md`: ID, goal, files, verification, `blockedBy`,
   and wave. Each task is one commit (`PRINCIPLES.md` §3).

10. **Verification.** The exact commands (`php -l` on the changed files, `./build.sh`) and the
    **manual verification script**: install the built package on a Joomla 5.3 + VirtueMart 4 shop,
    then the steps with the expected result for each (checkout, redirect return, webhook, admin order
    action, configuration, payment-method edit, as applicable), plus the review sequence
    `/code-review` → `/simplify` → `/security-review`. Name the instruction/doc files this task
    updates, or state "none".

11. **Done.** The definition of done: gates green, the observable outcome confirmed, and
    `DESIGN.md` / instruction files in sync.

## Clear-explanation rule

Every diagram and class outline comes with a short plain-language explanation of *why* it looks that
way and which requirement it serves. A reviewer should understand the intent, not just the shape.
