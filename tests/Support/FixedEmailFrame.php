<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailBrandSource;
use Corex\Email\Design\EmailFrame;
use Corex\Email\Design\StandardEmailDesign;

/**
 * The one email frame, in CoreX's standard design, around a brand a test names: what a unit
 * test draws with where there is no WordPress to read a site's brand from.
 */
final class FixedEmailFrame
{
    /** @param string $privacyPage The site's privacy page, or '' for none. */
    public static function of(EmailBrand $brand, string $privacyPage = ''): EmailFrame
    {
        return new EmailFrame(new StandardEmailDesign(), new class($brand) implements EmailBrandSource {
            public function __construct(private readonly EmailBrand $brand)
            {
            }

            public function brand(): EmailBrand
            {
                return $this->brand;
            }
        }, static fn (): string => $privacyPage);
    }

    public static function named(string $name = 'Acme', string $direction = 'ltr'): EmailFrame
    {
        return self::of(EmailBrand::standard($name, 'https://acme.test/', '', $direction, $direction === 'rtl' ? 'ar' : 'en'));
    }
}
