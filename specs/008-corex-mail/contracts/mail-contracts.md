# Phase 1 Contracts: Corex Mail (MVP)

The stable public API. Signatures are the agreed shape; bodies live in `tasks.md` / implementation.

## C1 — The corex-core seam (`Corex\Mail`)

```php
namespace Corex\Mail;

final class MailRequest
{
    /**
     * @param list<string>        $to
     * @param array<string,mixed> $context
     */
    public function __construct(
        public array $to,
        public ?string $templateName = null,
        public array $context = [],
        public ?string $subject = null,
        public ?string $body = null,
        public ?string $replyTo = null,
    ) {}
}

interface Mailer
{
    public function send(MailRequest $request): void;   // best-effort; never throws
}
```

## C2 — Facade + service (`Corex\Email`)

```php
namespace Corex\Email;

final class Mail                       // fluent facade over MessageBuilder + MailService (bounded accessor)
{
    public static function to(string|array $to): MessageBuilder;
}

final class MailService implements \Corex\Mail\Mailer
{
    public function deliver(Message\EmailMessage $message): EmailResult;   // build already done
    public function send(\Corex\Mail\MailRequest $request): void;          // seam impl: build → deliver
}

final class EmailResult
{
    public function __construct(public string $status, public string $message, public ?int $logId) {}
    public function isSent(): bool;     // status === 'sent'
}
```

## C3 — Message builder + value object (`Corex\Email\Message`)

```php
namespace Corex\Email\Message;

final class MessageBuilder             // fluent; pure
{
    public function cc(string|array $cc): self;
    public function bcc(string|array $bcc): self;
    public function replyTo(string $addr): self;
    public function subject(string $subject): self;          // raw/ad-hoc path
    public function body(string $html): self;                // raw/ad-hoc path
    public function template(string $name): self;            // templated path
    /** @param array<string,mixed> $context */
    public function with(array $context): self;
    public function send(): EmailResult;                     // resolves service from the container
    public function build(): EmailMessage;                   // pure (testable without sending)
}

final class EmailMessage               // immutable
{
    /** @param list<string> $to @param list<string> $cc @param list<string> $bcc @param array<string,string> $headers */
    public function __construct(
        public array $to, public array $cc, public array $bcc,
        public ?string $replyTo, public string $subject, public string $body, public array $headers = [],
    ) {}
}
```

## C4 — Templates (`Corex\Email\Template`)

```php
namespace Corex\Email\Template;

abstract class EmailTemplate
{
    abstract public function name(): string;
    abstract public function subject(MailContext $context): string;
    abstract public function body(MailContext $context): string;     // straight-line text with {{ path }}
}

final class TemplateRegistry
{
    public function register(EmailTemplate $template): void;
    public function find(string $name): ?EmailTemplate;              // unknown → null
}

final class MailContext                // pure, whitelisted
{
    /** @param array<string,mixed> $data roots: event, site, + named models */
    public function __construct(array $data);
    public function get(string $path, string $default = ''): string; // dotted; out-of-whitelist/absent → default
}

final class TemplateRenderer           // pure
{
    public function __construct(Layout $layout) {}
    public function render(EmailTemplate $template, MailContext $context): EmailMessageParts; // {subject, body}
}
```

## C5 — Security + recipients (`Corex\Email\Security`, `Corex\Email\Recipients`)

```php
namespace Corex\Email\Security;

final class HeaderGuard                 // pure
{
    /** @param array<string,string> $fields subject/from/replyTo/display-names @return ?string rejection reason */
    public function inspect(array $fields): ?string;   // null = clean; reason string = reject
}

namespace Corex\Email\Recipients;

interface UserDirectory { /** @return list<string> */ public function emailsInRole(string $role): array; }

final class RecipientResolver           // pure (role via injected UserDirectory)
{
    public function __construct(UserDirectory $users) {}
    /**
     * @param list<array{type:string,value:string}> $specs fixed|role|dynamic
     * @return array{valid:list<string>,dropped:list<string>}
     */
    public function resolve(array $specs, \Corex\Email\Template\MailContext $context): array;
}
```

## C6 — Driver + log (`Corex\Email\Driver`, `Corex\Email\Log`) — boundaries

```php
namespace Corex\Email\Driver;

interface MailDriver { public function send(\Corex\Email\Message\EmailMessage $message): bool; }
final class WpMailDriver implements MailDriver { /* wp_mail */ }

namespace Corex\Email\Log;

final class EmailLogRepository extends \Corex\Repositories\PostRepository
{
    public function record(string $status, \Corex\Email\Message\EmailMessage $message, ?string $template): \Corex\Models\Model;
    /** @return \Corex\Database\Collection */ public function byStatus(string $status);
}
```

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C4 renderer | `{{ path }}` merged + escaped; unknown path empty; markup value not live | FR-003/4, SC-003/8 |
| C5 header guard | CR/LF/control in any field → rejection reason; clean → null | FR-006, SC-002 |
| C5 recipients | fixed/role(fake dir)/dynamic resolved; invalid dropped; none valid handled | FR-007/8, SC-004 |
| C2 service | guard→validate→render→fake driver→fake log; sent/failed/rejected paths; never throws | FR-011, SC-005 |
| C6 lifecycle (integration) | a templated send records one corex_email_log; Forms delegates when Mailer bound | FR-010/12, SC-005/6 |
