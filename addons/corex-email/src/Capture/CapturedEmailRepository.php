<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailStudioStore;
use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;
use Exception;

/**
 * Append-only repository for Development message capture.
 */
final class CapturedEmailRepository
{
    private const TYPE = 'captured_email';

    /** Records read beyond the limit, to stand in for any that cannot be read. */
    private const SPARE = 10;

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function capture(
        EmailMessage $message,
        ?string $attemptId = null,
        ?DateTimeImmutable $capturedAt = null,
    ): CapturedEmail
    {
        $captureId = Uuid::v4();
        $attemptId ??= Uuid::v4();
        $capturedAt ??= new DateTimeImmutable('now');
        $id = $this->store->create(self::TYPE, $captureId, $message->subject ?: __('(no subject)', 'corex'), 0, [
            'capture_id'  => $captureId,
            'attempt_id'  => $attemptId,
            'to'          => $message->to,
            'cc'          => $message->cc,
            'bcc'         => $message->bcc,
            'reply_to'    => $message->replyTo,
            'subject'     => $message->subject,
            'body'        => $message->body,
            'html_body'   => $message->body,
            // What the message was sent with, where it brought its own.
            'plain_text'  => $message->plainText ?? $this->plainText($message->body),
            'headers'     => $message->headers,
            'captured_at' => $capturedAt->format(DATE_ATOM),
            'retention_until' => $capturedAt->modify('+30 days')->format(DATE_ATOM),
        ]);
        $record = $this->store->find($id);

        return $this->capturedEmail($record ?? throw new DomainException(__('The email could not be captured.', 'corex')));
    }

    /** @return list<CapturedEmail> */
    public function latest(int $limit = 50): array
    {
        $limit    = max(1, min(100, $limit));
        $captures = [];
        // A few more than asked for, so that an unreadable record skipped below still leaves the
        // list as long as asked.
        foreach ($this->store->latest(self::TYPE, $limit + self::SPARE) as $record) {
            if (count($captures) === $limit) {
                break;
            }
            // A record that cannot be read as an email, whatever is wrong with it, must not
            // take the list of the others away: one did, on a development site, until it was
            // found and removed by hand. The store no longer leaves such a record behind.
            try {
                $captures[] = $this->capturedEmail($record);
            } catch (Exception) {
                continue;
            }
        }

        return $captures;
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function capturedEmail(array $record): CapturedEmail
    {
        $payload = $record['payload'];

        return new CapturedEmail(
            id: $record['id'],
            captureId: (string) ($payload['capture_id'] ?? ''),
            to: $this->stringList($payload['to'] ?? []),
            cc: $this->stringList($payload['cc'] ?? []),
            bcc: $this->stringList($payload['bcc'] ?? []),
            replyTo: is_string($payload['reply_to'] ?? null) ? $payload['reply_to'] : null,
            subject: (string) ($payload['subject'] ?? ''),
            body: (string) ($payload['html_body'] ?? $payload['body'] ?? ''),
            headers: $this->stringMap($payload['headers'] ?? []),
            capturedAt: new DateTimeImmutable((string) ($payload['captured_at'] ?? '')),
            retentionUntil: new DateTimeImmutable((string) ($payload['retention_until'] ?? '')),
            attemptId: is_string($payload['attempt_id'] ?? null) ? $payload['attempt_id'] : null,
            plainText: (string) ($payload['plain_text'] ?? ''),
        );
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
    }

    /** @return array<string,string> */
    private function stringMap(mixed $value): array
    {
        $strings = [];
        foreach (is_array($value) ? $value : [] as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $strings[$key] = $item;
            }
        }

        return $strings;
    }

    private function plainText(string $html): string
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }
}
