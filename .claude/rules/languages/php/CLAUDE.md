---
paths:
  - "plugin/vmpayment/mollie/*.php"
  - "plugin/vmpayment/mollie/src/**/*.php"
  - "plugin/vmpayment/mollie/tmpl/**/*.php"
  - "plugin/vmpayment/mollie/fields/**/*.php"
  - "administrator/components/com_mollie/**/*.php"
---

# PHP Security Rules (Joomla / VirtueMart extension)

Security rules for the PHP code of the VirtueMart payment plugin and the `com_mollie` admin component.
The copied core (`plugin/vmpayment/mollie/core/`) is out of scope, because it is never edited in
place (`CLAUDE.md` § Local rules).

## Prerequisites

- `.claude/rules/_core/owasp-2025.md`: core web security
- `.claude/rules/_core/asvs-l2.md`: the verification standard and checklist for this repo

Every rule has to work on the **PHP 8.1 floor** (see `CLAUDE.md` § Local rules).

---

## Database Access

### Rule: Use the Joomla query builder with quoting or bound values

**Level**: `strict`

**When**: Any read or write to the database.

**Do**:
```php
$db = Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->createQuery()
    ->select($db->quoteName(['virtuemart_order_id', 'order_number']))
    ->from($db->quoteName('#__virtuemart_orders'))
    ->where($db->quoteName('virtuemart_order_id') . ' = :orderId')
    ->bind(':orderId', $orderId, ParameterType::INTEGER);
$row = $db->setQuery($query)->loadObject();
```

**Don't**:
```php
$db->setQuery("SELECT * FROM #__virtuemart_orders WHERE order_number = '" . $input->getString('order_number') . "'");
```

**Why**: Request values concatenated into SQL are injection. Identifiers go through `quoteName()`,
values through `bind()`, `quote()` or an `(int)` cast. VirtueMart order status changes go through
`VmModel::getModel('orders')->updateStatusForOneOrder()`, never through direct SQL, so VirtueMart's
own triggers and emails run.

**Refs**: OWASP A05:2025, CWE-89, ASVS 1.3

---

## Input Handling

### Rule: Read request input through a typed filter and validate it at the entry point

**Level**: `strict`

**When**: Reading `Factory::getApplication()->input`, `vRequest`, `$_FILES`, the webhook POST, or data
returned by the Mollie API.

**Do**:
```php
$orderId = $input->getInt('order_id');
if ($orderId <= 0) {
    echo new JsonResponse(null, Text::_('PLG_VMPAYMENT_MOLLIE_INVALID_ORDER'), true);
    $app->close();
}
$amount = $input->getString('amount');
if (!$this->isValidDecimalAmount($amount)) { /* generic error */ }
$methodId = in_array($value, SupportedPaymentMethods::SUPPORTED, true) ? $value : null;
```

**Don't**:
```php
$orderId = $_GET['order_id'];                                    // raw superglobal
$mapping = $input->post->get('status_mapping', [], 'array');     // stored without an allow-list
```

**Why**: Plugin triggers, AJAX tasks and component controllers are the trust boundary. Everything
below them assumes validated types. Controller signatures are typed (`string`, `float`, `int`), so an
unvalidated `null` becomes a `TypeError`, which `catch (\Exception)` does not catch.

**Refs**: OWASP A05:2025, CWE-20, ASVS 2.1

---

## Output Encoding

### Rule: Escape every dynamic value, using the platform's own helpers first

**Level**: `strict`

**When**: Rendering data in `plugin/vmpayment/mollie/tmpl/*.php`,
`administrator/components/com_mollie/tmpl/**`, `assets/overrides/edit_edit.php`, a custom form field,
or HTML built in PHP.

**Order of preference**: (1) the VirtueMart helpers in VirtueMart contexts (plugin, its layouts, the
payment-method edit override), (2) the Joomla helpers in `com_mollie`, and (3) plain PHP
(`htmlspecialchars`, `json_encode`) only where neither exists. Don't write custom escaping or
rendering helpers.

What the helpers actually do (verified in VirtueMart SVN trunk r11459,
`administrator/components/com_virtuemart/helpers/`, and Joomla 5.3.0
`libraries/src/MVC/View/HtmlView.php`):

| Need | VirtueMart context | `com_mollie` (Joomla) | Escapes? |
|---|---|---|---|
| Text / attribute value | `vRequest::vmSpecialChars($v)` (= `htmlspecialchars`, `ENT_QUOTES\|ENT_SUBSTITUTE`, UTF-8), `VmHTML::shopMakeHtmlSafe($v)` | `$this->escape($v)` in an `HtmlView` layout (= `htmlspecialchars`, `ENT_QUOTES`) | Yes |
| Translation | `vmText::_()`, `vmText::sprintf()` | `Text::_()`, `Text::sprintf()` | **No**: they only translate. Escape `sprintf` arguments that carry data |
| Select, radio, checkbox | `VmHTML::genericlist()`, `options()`, `select()`, `radioList()`, `checkbox()` | `HTMLHelper::_('select.genericlist', …)` | Keys and option texts yes (`option.key.toHtml` / `option.text.toHtml` default to `true`) |
| Input, textarea, hidden, link | `VmHTML::input()`, `textarea()`, `inputHidden()`, `link()`, `value()`, `raw()`, `row()` | form fields / `HTMLHelper` | **No**: the value is concatenated as-is. Pass it through `vRequest::vmSpecialChars()` first unless it is already trusted |
| Data for JS | `vmJsApi::addJScript($name, $script)` to register the script | `$doc->addScriptOptions()` + `Joomla.getOptions()` | `vmJsApi::safe_json_encode()` does **not** use `JSON_HEX_*`. For data inside an inline `<script>`, use `json_encode($v, JSON_HEX_TAG\|JSON_HEX_AMP\|JSON_HEX_APOS\|JSON_HEX_QUOT)` or `addScriptOptions()` |

**Do**:
```php
<span><?php echo vRequest::vmSpecialChars($viewData['order_number']); ?></span>      // plugin layout
<?php echo vmText::sprintf('PLG_VMPAYMENT_MOLLIE_X', vRequest::vmSpecialChars($name)); ?>
<?php echo VmHTML::input('mollie_x', vRequest::vmSpecialChars($value)); ?>
<?php echo $this->escape($this->config->profileName); ?>                              // com_mollie view
$doc->addScriptOptions('mollie', ['profileId' => $profileId]);                          // data to JS
```

**Don't**:
```php
<?php echo $viewData['payment_name']; ?>                          // unescaped
<?php echo VmHTML::input('mollie_x', $request->get('x')); ?>      // VmHTML::input does not escape
<?php echo vmText::_($mollieErrorMessage); ?>                     // translation is not escaping
<script>var cfg = '<?php echo $profileId; ?>';</script>          // string built into JS
function mollieEscape($s) { … }                                  // custom helper instead of the platform one
```

**Why**: Joomla and VirtueMart PHP layouts do not auto-escape. Order data, method names and Mollie
error messages end up in admin and site pages, so they are XSS vectors. The platform helpers keep the
code consistent with the surrounding VirtueMart/Joomla code, and they are already maintained there.
Check what a helper does in the official source (`CLAUDE.md` § Official references) before relying on
it for escaping.

**Refs**: OWASP A05:2025, CWE-79, ASVS 1.1

---

## Access Control and CSRF

### Rule: Admin tasks check the token and the ACL; site endpoints verify order ownership

**Level**: `strict`

**When**: Adding or changing an AJAX task in `plgVmOnSelfCallBE`, a `com_mollie` controller task, a
backend order trigger, or a site trigger that takes an order id.

**Do**:
- Admin, state-changing: `Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));` (POST), plus
  `$app->getIdentity()->authorise('core.manage', 'com_virtuemart')` (or the relevant component
  action). Read-only AJAX lookups use `Session::checkToken('get')`.
- Site: for any `order_id`, check that the order belongs to the current shopper (VirtueMart session
  or user id), or require an HMAC token that was put into the `redirectUrl`.
- Every VirtueMart trigger first checks that it handles its own method:
  `getVmPluginMethod($id)` + `selectedThisElement($method->payment_element)`, or the `pm` parameter
  for `plgVmOnPaymentNotification` / `plgVmOnPaymentResponseReceived`. Otherwise it returns `null`.

**Don't**:
- Expose capture, refund, configuration or upload actions through a site trigger
  (`plgVmOnSelfCallFE`, `pluginresponse`).
- Act on an order id from the URL without an ownership check, or render `order_pass` to an
  unverified caller.
- `exit` or change the HTTP response in a trigger that is not addressed to this plugin.

**Why**: Site routes are reachable by anyone on the internet, and VirtueMart calls payment triggers on
every installed `vmpayment` plugin.

**Refs**: OWASP A01:2025, CWE-284, CWE-352, CWE-639, ASVS 4.2, 4.3, 4.5, 8.1

---

## Webhook

### Rule: Trust only what is fetched back from Mollie; stay idempotent; map errors to a retry status

**Level**: `strict`

**When**: Changing `plgVmOnPaymentNotification`, `WebHookController`, or `VirtueMartOrderTransitionService`.

**Do**:
- Read only the `id` from the request and let the core `WebHookTransformer` fetch the payment.
- Keep status updates safe to run more than once (Mollie retries on non-2xx). Skip unchanged
  statuses.
- Catch `\Throwable` at the entry point and answer 200 (done, or reference unknown) or 503 (retry
  makes sense). Never let an exception render a Joomla error page to Mollie.

**Don't**:
- Read status, amount or metadata from the webhook POST body.
- Perform side effects (emails, stock) without checking whether they have already happened.

**Refs**: OWASP A08:2025, CWE-345, ASVS 4.4

---

## File Uploads

### Rule: Allow-list raster image types and size; generate the file name server-side

**Level**: `strict`

**When**: Handling `$_FILES` / `$input->files` (payment-method logo upload).

**Do**:
```php
$allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime || $file['size'] > 1048576 || !getimagesize($file['tmp_name'])) {
    return null;
}
$target = JPATH_ROOT . '/images/mollie/custom-logos/' . bin2hex(random_bytes(8)) . '.' . $ext;
```

**Don't**:
- Accept SVG (active content) or `text/*` MIME types for a publicly served file.
- `File::upload($file['tmp_name'], $dir . $file['name'])` with the user-supplied name.

**Refs**: OWASP A06:2025, CWE-434, ASVS 5.1–5.3

---

## Secrets and Tokens

### Rule: No keys in code, HTML or logs; tokens are HMAC/CSPRNG with constant-time comparison

**Level**: `strict`

**When**: Handling Mollie API keys or the auth token, building links or tokens, or logging.

**Do**:
- Read and write keys only through `ConfigurationService` (encrypted with `EncryptionService`).
  Never log them, and never render them back in full in an admin form.
- Build tokens with `hash_hmac('sha256', $data, $secret)` and compare with `hash_equals()`.
- Log Mollie IDs (`tr_…`) and order IDs, not payloads with addresses or emails.

**Don't**:
- `md5($orderId . $customerId)` as an access token.
- `rand()`, `mt_rand()` or `uniqid()` for anything security-relevant.
- `Logger::logDebug(...)` with full API requests/responses, headers or card tokens.

**Refs**: OWASP A04:2025, A09:2025, CWE-330, CWE-532, ASVS 11.1–11.4, 14.1, 14.3

---

## Dangerous Functions

### Rule: No dynamic code execution or unsafe deserialization

**Level**: `strict`

**When**: Always.

**Don't**: `eval`, `assert` with strings, `preg_replace` with `/e`, `exec` / `shell_exec` / `system` /
`passthru` / backticks, or `unserialize` on anything not written by this package (use `json_decode`).
Never pass a variable to `include` / `require`. Never instantiate a class whose name comes from
request data.

**Refs**: OWASP A05:2025, CWE-94, CWE-502

---

## Error Handling

### Rule: Catch at entry points; show generic messages

**Level**: `warning`

**When**: Plugin triggers, AJAX tasks, component controllers, installer scripts.

**Do**: At the entry point, catch `\Throwable`, log the details through `Logger`, and show a generic
`enqueueMessage()` / `JsonResponse` error. Wrapper controllers return
`Infrastructure\DTO\Response` instead of throwing.

**Don't**: Let exceptions reach Joomla's error page, or print raw Mollie API error bodies to shoppers.

**Refs**: OWASP A10:2025, CWE-209
