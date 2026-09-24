---
paths:
  - "plugin/vmpayment/mollie/assets/js/**/*.js"
  - "administrator/components/com_mollie/assets/js/**/*.js"
---

# JavaScript Security Rules (browser code of the Joomla / VirtueMart extension)

These rules cover the package's **browser-only** JavaScript. There is no server-side JS and no build
step: the files are served as-is by Joomla.
- `plugin/vmpayment/mollie/assets/js/mollie-checkout.js`: storefront cart/checkout (method filtering
  by country and amount, Mollie Components card fields, card token).
- `plugin/vmpayment/mollie/assets/js/order-payment-details.js`: the Mollie block on the VirtueMart
  admin order page (capture and refund).
- `administrator/components/com_mollie/assets/js/payment-method-edit.js`, `logo-upload.js`: the
  VirtueMart payment-method edit form override (method list by currency, logo preview).

## Prerequisites

- `.claude/rules/_core/owasp-2025.md`: core web security
- `.claude/rules/_core/asvs-l2.md`: the verification standard and checklist for this repo
- `.claude/rules/languages/php/CLAUDE.md`: the server side of every endpoint these scripts call

---

## DOM Output

### Rule: Put data into the DOM as text; never as HTML

**Level**: `strict`

**When**: Rendering anything that comes from an AJAX response, a `data-*` attribute, a form field,
`Joomla.getOptions()` / `window.*` config or the URL. That includes messages, method names, amounts,
Mollie error texts and file names.

**Do**:
```javascript
const option = document.createElement('option');
option.value = method.id;
option.textContent = method.name;      // text, never parsed as HTML
select.appendChild(option);
```

**Don't**:
```javascript
messageBox.innerHTML = response.message;              // XSS if the text contains markup
select.insertAdjacentHTML('beforeend', `<option>${method.name}</option>`);
```

**Why**: Messages and names can come from the Mollie API or from admin input. In the administrator,
an XSS runs with the shop administrator's session.

`innerHTML` is allowed only to clear an element, to restore markup the script itself saved from the
server-rendered DOM, or for static markup written in the script. Say so in a comment where it's used.

**Refs**: OWASP A05:2025, CWE-79, ASVS 1.1

---

## Code Execution

### Rule: No dynamic code

**Level**: `strict`

**When**: Always.

**Don't**: `eval`, `new Function`, `setTimeout` / `setInterval` with a string argument, `document.write`,
or `javascript:` URLs.

**Why**: These break a strict Content-Security-Policy in the merchant's shop and turn any injected
string into code.

**Refs**: OWASP A05:2025, CWE-95

---

## AJAX Requests

### Rule: Server-provided URLs, the Joomla CSRF token, and the right application

**Level**: `strict`

**When**: Calling `index.php?option=com_virtuemart&view=plugin&…&name=mollie&task=…` or any other
endpoint.

**Do**:
- Take endpoint URLs and the base path from what the server rendered (`Joomla.getOptions(...)`,
  `window.mollieAjaxUrl`, `data-*` attributes, `Uri::base()` passed from PHP). The shop may be
  installed in a subfolder, so never hard-code `/administrator/...` or `window.location.origin + '/…'`.
- Storefront scripts call site routes only. Administrator routes are for administrator scripts.
- Send the token on every request: `[Joomla.getOptions('csrf.token')]: '1'` as a POST field (or query
  parameter for read-only GETs).
- Send state changes (capture, refund) as POST.
- Handle a non-JSON or empty error response (check `response.ok`, catch the rejected promise), and
  show a generic message.

**Don't**:
- Put API keys, tokens or other secrets in query strings.
- Call cross-origin URLs other than Mollie Components.

**Refs**: OWASP A01:2025, CWE-352, ASVS 4.1, 4.3

---

## Checkout: Card Data and Mollie Components

### Rule: Card data stays inside Mollie Components; submit only the token

**Level**: `strict`

**When**: Changing `mollie-checkout.js`, `tmpl/creditcard_fields.php`, or the script registration in
`plgVmDisplayListFEPayment`.

**Do**:
- Load `mollie.js` only from `https://js.mollie.com/v1/mollie.js`.
- Initialize with only the profile ID, locale and test-mode flag from `window.mollieConfig`.
- Get the card token with `mollie.createToken()` and put only that token into the checkout form
  (`mollieCardToken`).

**Don't**:
- Read, copy, log, store or send card numbers, CVC or expiry. The shop must never see them.
- Add other third-party scripts or origins to checkout without a documented decision in `DESIGN.md`.
- Keep the token longer than the form submit (no `localStorage` / `sessionStorage` / cookies).

**Why**: This keeps card data out of the merchant's shop and PCI scope.

**Refs**: OWASP A04:2025, A08:2025, CWE-311, ASVS 14.2

---

## Secrets and Personal Data

### Rule: No secrets in browser code; no payment or customer data in browser storage or the console

**Level**: `strict`

**When**: Always.

**Do**: Only non-secret configuration reaches the browser (profile ID, test-mode flag, locale, cart
total and currency, endpoint URLs).

**Don't**:
- Put API keys (live or test) in JS, `data-*` attributes or inline scripts.
- Store order, customer or payment data in `localStorage` / `sessionStorage`.
- Use `console.log` with tokens, responses containing customer data, or keys. Remove debug logging
  before committing; keep `console.error` for real failures only, without payloads.

**Refs**: OWASP A04:2025, A09:2025, CWE-200, CWE-532, ASVS 13.1, 14.1

---

## Navigation

### Rule: Redirect only to server-provided URLs

**Level**: `warning`

**When**: Setting `location`, `location.href` or `window.open`, or following a URL from a response.

**Do**: Use `location.reload()`, or a URL that the server rendered or returned for this action.

**Don't**: Redirect to a value taken from the query string, the hash or a form field (open redirect).

**Refs**: OWASP A01:2025, CWE-601

---

## Conventions That Protect Correctness

### Rule: Respect the global namespaces and the existing syntax level

**Level**: `warning`

**When**: Adding or changing a script.

**Do**:
- Storefront code attaches to `window.MollieComponents` and **never** defines `window.Mollie`,
  because `Mollie` is the global function from `mollie.js`.
- Server-provided config is read from the existing globals (`window.mollieConfig`,
  `window.mollieCartData`, `window.mollieCountryId`, `window.mollieAjaxUrl`,
  `window.mollieCaptureRestrictions`) or `Joomla.getOptions()`. Add new config the same way, set from
  PHP with `json_encode()` / `addScriptOptions()`.
- Wrap files in an IIFE or a `DOMContentLoaded` handler, as the existing files do.
- Keep to the syntax already used in these files (ES2020: `let`/`const`, arrow functions,
  `async`/`await`, template literals, optional chaining). There is no transpiler and no bundler, and
  ES modules (`import`/`export`) are not used. Anything newer is a deliberate decision.

**Don't**: Pull in libraries from a CDN, or add a build step, without a decision in the plan. jQuery is
used only where VirtueMart's own UI requires it (chosen/select2 change events).

**Why**: Overwriting `window.Mollie` on checkout breaks Mollie Components. Syntax the shoppers'
browsers can't parse breaks the whole checkout script.
