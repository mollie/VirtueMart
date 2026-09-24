# OWASP ASVS v5.0 — Level 2 controls

These are the verification requirements that **must hold** for every change shipped from this repository. They are distilled from OWASP ASVS v5.0 for a payment package that runs inside merchants' Joomla / VirtueMart shops. A change that cannot satisfy an applicable item does not get committed.

This package handles payment flows, merchant API credentials and shopper PII (names, addresses, emails sent to Mollie). Level 2 is the minimum. Card data never touches the shop, because it is tokenised by Mollie Components in the browser. Keep it that way.

The "Enforcement here" column maps each control to this codebase. PHP details are in `.claude/rules/languages/php/CLAUDE.md`, browser JS details in `.claude/rules/languages/javascript/CLAUDE.md`. The copied core (`plugin/vmpayment/mollie/core/`) is out of scope for edits (`CLAUDE.md` § Local rules); a control the core violates is fixed in `mollie/integration-core` or compensated in the wrapper.

---

## V1 — Encoding and Sanitization

| ID | Requirement | Enforcement here |
|---|---|---|
| 1.1 | Output encoding chosen for the **sink context** (HTML, attribute, JS, CSS, URL). | Layouts (`plugin/vmpayment/mollie/tmpl/*.php`, `administrator/components/com_mollie/tmpl/**`, `assets/overrides/edit_edit.php`) are plain PHP with no auto-escaping. Every dynamic value is escaped with the platform helper: `vRequest::vmSpecialChars()` in VirtueMart contexts, `$this->escape()` in `com_mollie` views, and plain `htmlspecialchars()` only where neither exists. `VmHTML::input()` / `textarea()` / `inputHidden()` / `link()` and `vmText::_()` / `Text::_()` do **not** escape (table in `languages/php/CLAUDE.md` § Output Encoding). Values for inline JS go through `addScriptOptions()` or `json_encode()` with `JSON_HEX_*` flags, and URLs through `Route::_()` / `Uri`. |
| 1.2 | All untrusted input is treated as untrusted across **all** sinks (SQL, OS commands, templates, file paths). | `Factory::getApplication()->input` / `vRequest` values, `$_FILES`, the webhook POST and Mollie API payloads are untrusted until validated. |
| 1.3 | SQL uses parameter binding or query-builder quoting. | Joomla `DatabaseInterface` query builder (`createQuery()`) with `quoteName()` for identifiers and `quote()`, `bind()` or `(int)` casts for values. **Never** concatenate request data into SQL. VirtueMart order status changes go through `VmModel::getModel('orders')`, never through direct SQL. |
| 1.4 | OS commands are never built from input. | No `exec`, `shell_exec`, `system`, `passthru` or backticks in extension code (`build.sh` is a developer tool and is not shipped). |

## V2 — Validation and Business Logic

| ID | Requirement | Enforcement here |
|---|---|---|
| 2.1 | Server-side allow-list validation for every untrusted field. | Read input with a typed filter (`getInt`, `getCmd`, `getString`) and validate further: IDs `(int)` and `> 0`, amounts as decimal strings with at most two decimals (`isValidDecimalAmount()`), enum-like values (Mollie method ids, Mollie status keys, order status codes, log levels) against an allow-list (`SupportedPaymentMethods`, `MollieStatusMapping`, `#__virtuemart_orderstates`). Browser JS validation is never enough. |
| 2.2 | Malformed payloads are rejected with a generic error; input is not echoed. | AJAX tasks return a generic `JsonResponse` error and log the details. The webhook answers with an HTTP status only. |
| 2.3 | Business invariants are enforced server-side. | Amounts sent to Mollie come from `VirtueMartCart` / the VirtueMart order, never from the browser. Capture and refund amounts are checked against the Mollie payment (authorized amount, paid minus `amountRefunded`). Order status transitions go through `VirtueMartOrderTransitionService` and the configured status mapping. |
| 2.4 | Numeric values are formatted locale-independently. | Money goes to Mollie as a string with a `.` separator, two decimals and no thousands separator. |

## V3 — Web Frontend Security

| ID | Requirement | Enforcement here |
|---|---|---|
| 3.1 | Third-party scripts only from required, known origins. | The only external script is `https://js.mollie.com/v1/mollie.js` (Mollie Components), registered in `plgVmDisplayListFEPayment`. Adding another origin is a design decision and needs an ADR in `DESIGN.md`. |
| 3.2 | No secrets in browser code. | API keys and the auth token never reach layouts or JS. Only the profile ID, locale and test-mode flag go to Mollie Components. |
| 3.3 | Response headers (CSP, HSTS, nosniff) | These are owned by the merchant's Joomla installation or server, not by this package. Don't break a strict CSP: no `eval`, no `new Function`, and register scripts through the Web Asset Manager / `vmJsApi` instead of inline code where possible. |

## V4 — API and Web Service

| ID | Requirement | Enforcement here |
|---|---|---|
| 4.1 | Every state-changing endpoint accepts only the intended HTTP method. | State changes (capture, refund, configuration save, payment-method save with logo upload) accept POST only. |
| 4.2 | Admin endpoints are behind Joomla administrator authentication and authorization. | Admin functionality runs only in the administrator application (`com_mollie`, VirtueMart `view=plugin` → `plgVmOnSelfCallBE`, backend order triggers). Check the permission explicitly (`$app->getIdentity()->authorise('core.manage', 'com_virtuemart')` or the component's own action) for every state-changing task. Never expose admin functionality through a site (frontend) trigger. |
| 4.3 | State-changing admin requests are protected against CSRF. | `Session::checkToken()` (POST) / `Session::checkToken('get')` on every state-changing task. The token comes from `HTMLHelper::_('form.token')` in forms, or from `Joomla.getOptions('csrf.token')` / `Session::getFormToken()` for AJAX. |
| 4.4 | Inbound webhooks are authenticated. | Mollie webhooks carry only an `id`. Authenticity comes from fetching the resource back from the Mollie API with the merchant's key (core `WebHookTransformer`), and the POST body is never trusted. The handler must stay idempotent, because Mollie retries on non-2xx responses. |
| 4.5 | Public (site) endpoints that act on an order verify ownership. | The redirect return (`pluginresponsereceived&order_id=N`) and any other site endpoint that takes an order id check that the order belongs to the current shopper (VirtueMart session / user), or carry an unguessable signed token. Order passwords (`order_pass`) are only shown after ownership is proven. |
| 4.6 | Plugin triggers act only for this plugin's own methods. | VirtueMart dispatches payment triggers to every `vmpayment` plugin. Each trigger first checks `getVmPluginMethod()` + `selectedThisElement()` (or the `pm` request parameter), and returns `null` for other plugins' methods. |

## V5 — File Handling

| ID | Requirement | Enforcement here |
|---|---|---|
| 5.1 | Uploaded file type and size are validated server-side (extension allow-list + detected MIME type). | Applies to every `$_FILES` / `input->files` handler (the payment-method logo upload in `handleLogoUpload()` / `validateUploadedFile()`). |
| 5.2 | Uploaded files are never executable or active content. | Allow raster image types only. SVG is active content (script) and must not be stored as-is under a public path. |
| 5.3 | User-supplied file names are never used as filesystem paths. | Generate the target name server-side (`images/mollie/custom-logos/`). `basename()` alone is not enough; use `File::makeSafe()` only in addition to a generated name. |

## V6/V7 — Authentication and Session Management

| ID | Requirement | Enforcement here |
|---|---|---|
| 6.1 | This package adds no authentication of its own. | Shopper and admin authentication belong to Joomla / VirtueMart. Use their identity and cart context; never build a parallel login. |
| 7.1 | Session data is minimal and single-use where possible. | The wrapper does not use the Joomla session today. The card token travels only as the POST field `mollieCardToken`. Never store API keys, tokens or full payment payloads in the session. |

## V8 — Authorization

| ID | Requirement | Enforcement here |
|---|---|---|
| 8.1 | Authorization decisions are made server-side, on the resource being accessed. | A site endpoint that takes `order_id` checks ownership (4.5). Admin tasks check the Joomla ACL (4.2). |
| 8.2 | No client-supplied identity or amount is trusted. | Customer identity comes from Joomla / VirtueMart, amounts from the cart, the order or Mollie. |
| 8.3 | Default deny. | Unknown AJAX tasks fall through to a no-op (`default => fn() => null` in `plgVmOnSelfCallBE`), never to a state change. |

## V11 — Cryptography

| ID | Requirement | Enforcement here |
|---|---|---|
| 11.1 | Tokens that protect a resource are unguessable. | `hash_hmac('sha256', …, <secret>)` or `random_bytes()`-based. Never plain `md5`/`sha1` of IDs. |
| 11.2 | Token comparison is constant-time. | `hash_equals()`. |
| 11.3 | Security randomness comes from a CSPRNG. | `random_bytes()` / `random_int()` / `sodium_*`. Never `rand`, `mt_rand` or `uniqid` for security. |
| 11.4 | Stored secrets are encrypted with a vetted primitive. | `EncryptionService` (libsodium `crypto_secretbox`, key derived from the Joomla `secret`). Don't add a second scheme. |

## V12 — Secure Communication

| ID | Requirement | Enforcement here |
|---|---|---|
| 12.1 | Outbound calls use TLS with certificate verification on. | All Mollie calls go through the core `Proxy` + `CurlHttpClient` over `https://api.mollie.com/v2/`. Never disable `CURLOPT_SSL_VERIFYPEER` / `VERIFYHOST`. |
| 12.2 | Outbound destinations are fixed. | No request-controlled URLs for server-side HTTP calls (SSRF). The only outbound host is the Mollie API. |

## V13 — Configuration

| ID | Requirement | Enforcement here |
|---|---|---|
| 13.1 | No secrets in source control. | API keys live only in the merchant's database (`#__mollie_entities`, encrypted). No keys in code, fixtures, docs or commits, and that includes test keys. |
| 13.2 | Debug output is off by default. | The log level defaults to `disabled`. Debug logging is an explicit admin choice on the configuration page. |
| 13.3 | Dependencies are pinned and reviewed. | The core is a copy of a released `mollie/integration-core` version. A re-copy is reviewed as a diff of `plugin/vmpayment/mollie/core/`. No other third-party PHP dependency is shipped. |

## V14 — Data Protection

| ID | Requirement | Enforcement here |
|---|---|---|
| 14.1 | No secrets or PII in logs. | `Logger::log*()` (→ `JoomlaLoggerAdapter`, `com_mollie.php`) never receives API keys, the `Authorization` header, card tokens, or request/response bodies with addresses or emails. Log IDs (`tr_…`, VirtueMart order id) instead. |
| 14.2 | Only the data Mollie needs is sent. | `PaymentService::buildPaymentDTO()` sends what the Payments API requires (lines, amounts, addresses for the methods that need them). Don't add fields "just in case". |
| 14.3 | Stored credentials are protected. | Treat `ConfigEntity` rows (`liveApiKey`, `testApiKey`, `authToken`) as secret. Never render stored keys back in full in the admin UI or logs. |

---

## Verification checklist applied to every change

Before declaring a change done, the implementer (human or AI) walks through:

1. **Input**: every untrusted field (request input, `$_FILES`, webhook, Mollie payload) is validated or cast server-side.
2. **Sinks**: SQL goes through the query builder with quoting/binding, output is escaped for its context, and files are validated.
3. **AuthZ**: admin functionality is only in the administrator application with an ACL check, site endpoints check order ownership, state changes are POST with `Session::checkToken()`, and every trigger checks it handles its own method.
4. **Crypto / secrets**: there are no keys in source, tokens are HMAC/CSPRNG-based, and comparisons are constant-time.
5. **Logging**: there are no API keys, PII or card tokens in the log.
6. **Webhook**: the resource is fetched from Mollie, the handler is idempotent, and it returns the correct HTTP status for retries.
7. **Errors**: messages shown to shoppers and admins are generic, with no stack traces or raw API errors in site output.

A change that cannot answer "yes" to every applicable item is not ready for review.
