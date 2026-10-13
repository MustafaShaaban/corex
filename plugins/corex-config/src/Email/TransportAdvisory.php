<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Explains the boundary between CoreX (which composes messages) and an SMTP transport plugin such
 * as FluentSMTP (which delivers them), using only public, non-sensitive signals.
 *
 * It never reads, decrypts, stores, or requires a transport plugin's credentials or tables
 * (FR-027). Where a reliable signal exists — a registered `wp_mail_from` filter, a From domain
 * that differs from the site's — it says so. Where none exists, it returns general guidance and
 * never a fabricated "detected" state (FR-029).
 */
final readonly class TransportAdvisory
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function evaluate(): TransportAdvisoryResult
    {
        $notes = [$this->generalGuidance()];

        $notes = array_merge($notes, $this->domainMismatches(), $this->forcedHeaderNotes());

        return new TransportAdvisoryResult($notes);
    }

    /** @return array{level:string,message:string} */
    private function generalGuidance(): array
    {
        return [
            'level'   => TransportAdvisoryResult::LEVEL_INFO,
            'message' => __(
                'CoreX composes each message and hands it to WordPress via wp_mail(). An SMTP plugin such as FluentSMTP, when installed, transports it. SMTP credentials stay owned by that plugin — CoreX never reads them. A transport accepting a message does not prove it reached the inbox.',
                'corex',
            ),
        ];
    }

    /**
     * A warning for each address the site sends from that is on another domain than the
     * site's: the general one, and the one it names for replies (spec 106, story 5).
     *
     * @return list<array{level:string,message:string}>
     */
    private function domainMismatches(): array
    {
        $siteHost = strtolower((string) wp_parse_url((string) home_url(), PHP_URL_HOST));
        $said = [
            /* translators: 1: configured From domain, 2: site domain. */
            'mail.from.address' => __('Your From address is on %1$s, a different domain from this site (%2$s). Some providers rewrite or flag mail whose domain is not authorised to send. Confirm your transport plugin is allowed to send as this domain.', 'corex'),
            /* translators: 1: the reply sender address's domain, 2: site domain. */
            'mail.reply.from_address' => __('Your reply sender address is on %1$s, a different domain from this site (%2$s). Some providers rewrite or flag mail whose domain is not authorised to send. Confirm your transport plugin is allowed to send as this domain.', 'corex'),
        ];
        $notes = [];
        foreach ($said as $setting => $sentence) {
            $address = (string) $this->config->get($setting, '');
            $domain = str_contains($address, '@') ? strtolower(substr(strrchr($address, '@') ?: '@', 1)) : '';
            if ($domain !== '' && $siteHost !== '' && $domain !== $siteHost) {
                $notes[] = [
                    'level' => TransportAdvisoryResult::LEVEL_WARNING,
                    'message' => sprintf($sentence, $domain, $siteHost),
                ];
            }
        }

        return $notes;
    }

    /** @return list<array{level:string,message:string}> */
    private function forcedHeaderNotes(): array
    {
        $notes = [];

        // A registered wp_mail_from / wp_mail_from_name filter is the supported, public signal that
        // a transport plugin is forcing the From header — its "Force From" setting. We report the
        // filter's presence; we do not inspect which plugin registered it.
        if (has_filter('wp_mail_from')) {
            $notes[] = [
                'level'   => TransportAdvisoryResult::LEVEL_WARNING,
                'message' => __('A plugin is overriding the From address for all site mail (a "Force From" setting). CoreX\'s configured From address may not be used. Check your transport plugin\'s sender settings.', 'corex'),
            ];
        }

        if (has_filter('wp_mail_from_name')) {
            $notes[] = [
                'level'   => TransportAdvisoryResult::LEVEL_WARNING,
                'message' => __('A plugin is overriding the From name for all site mail. CoreX\'s configured From name may not be used.', 'corex'),
            ];
        }

        return $notes;
    }
}
