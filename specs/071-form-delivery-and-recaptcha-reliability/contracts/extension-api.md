# Extension Contracts: Form Delivery & reCAPTCHA v3 Reliability

The public seams add-ons and project code integrate against. All live in `corex-core` so consumers
never depend on the optional `corex-captcha` or `corex-email` packages.

## VerifyingChallenge (new)

```php
namespace Corex\Security;

interface VerifyingChallenge extends ChallengeVerifier
{
    /**
     * Judge a token against server-known expectations and return a typed verdict.
     * Fails closed: any failure returns a non-`passed` outcome, never throws to the caller.
     */
    public function challenge(string $token, ChallengeContext $context): ChallengeVerification;
}
```

- **Backward compatible.** `ChallengeVerifier::verify(string): bool` is unchanged. Existing drivers
  (`HoneypotCaptcha`, `NullCaptcha`, `RemoteCaptcha`) keep working; `ProtectionStage` calls
  `challenge()` only when `$driver instanceof VerifyingChallenge`, else `verify()`.
- **Contract:** the implementation MUST determine the expected action from `$context`, never from the
  token or any request value. It MUST NOT expose the secret or the raw provider payload in the
  returned verification. It MUST fail closed on a provider or transport error.

**Reference implementation:** `Corex\Captcha\RecaptchaV3Captcha`. A project adding another v3-style
provider implements this interface and registers it in `CaptchaResolver`.

## CaptchaAction (new)

> Lives in `Corex\Forms\Submission`, **not** the captcha add-on. The action is a Forms concept
> (derived from a flow slug) and the verifier only *compares* the value it is handed, so placing
> it here keeps `corex-forms` from hard-depending on an optional add-on (Principle IX). Corrected
> from the original plan during implementation; see `DECISIONS.md`.

```php
namespace Corex\Forms\Submission;

final class CaptchaAction
{
    /** Derive or normalise the provider action for a form. Deterministic; safe charset; ≤100 chars. */
    public static function forFlow(string $slug, ?string $override): string;

    /** Normalise a raw string to the provider-safe action charset, or return null if unusable. */
    public static function normalise(?string $raw): ?string;
}
```

Called by **both** the renderer and `ProtectionStage`, which is what makes the browser's action and
the server's expectation provably identical (FR-004). A derived action is prefixed `corex_form_`.

## NotificationDispatcher (new)

```php
namespace Corex\Forms\Submission;

final class NotificationDispatcher
{
    /**
     * Attempt one notification through the best available path and return a typed outcome.
     * Never throws; a transport failure is a `failed`/`rejected` NotificationDelivery, not an exception.
     * The detect-and-defer ladder: RoutedMailer → AttemptingMailer → Mailer → wp_mail().
     */
    public function dispatch(NotificationRequest $request): NotificationDelivery;
}
```

- **Contract:** MUST NOT delete or roll back a saved submission on failure (FR-013). MUST map
  `wp_mail()` success to `accepted`, never `sent` (FR-015). MUST record `not_attempted` with a reason
  when no attempt was required or possible (FR-014/018).
- Used by both `EmailStage` (flow path) and `SendEmailListener` (legacy path), replacing the ladder
  duplicated in the latter and absent from the former.

## ProtectedFormRegistry (new)

```php
namespace Corex\Forms\Block;

final class ProtectedFormRegistry
{
    /** Declare that a rendered form on this request needs the provider script. Deduplicated by slug. */
    public function declare(string $slug, string $action): void;

    /** @return array<string,string> slug => action, for the asset controller to localise. */
    public function all(): array;
}
```

Request-scoped. Populated by `FlowBlockRenderer` during `the_content`; read by
`CaptchaAssetController` on `wp_footer`. Empty registry ⇒ no enqueue, no provider request (FR-001).

## TransportAdvisory (new — read-only, public signals only)

```php
namespace Corex\Config\Email;

final class TransportAdvisory
{
    /** A safe, evidence-based advisory about sender-override likelihood. Never reads plugin internals. */
    public function evaluate(): TransportAdvisoryResult;
}
```

- **Contract:** MUST read only public signals (`has_filter('wp_mail_from')`, configured From domain vs
  site domain, `is_plugin_active()`). MUST NOT read, decrypt, store, or require any transport plugin's
  credentials or tables (FR-027). Absence of evidence yields general guidance, never a fabricated
  `detected` state (FR-029).

## Stability

All five seams are additive. No existing signature changes. `FlowConfiguration`'s constructor gains a
trailing optional parameter, which is source-compatible with every existing positional call.
