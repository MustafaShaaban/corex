<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Company;

defined('ABSPATH') || exit;

use Corex\Kit\Blueprint;

/**
 * The Company Website kit (Spec 059 / M4): composes the Corex UI section patterns and
 * the M3 header/footer with the theme's universal FSE templates into a neutral,
 * brand-aware company site. Needs the UI library; the forms + mail add-ons enhance the
 * contact section. Pages are token-only and compose only registered patterns; pages
 * with no dedicated section block yet reuse section-header + core blocks and record the
 * gap for the M5 block batch (services/team/logo-cloud/case-study/locations grids).
 */
final class CompanyBlueprint extends Blueprint
{
    public function name(): string
    {
        return 'company';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['corex-ui'];
    }

    /**
     * @return list<string>
     */
    public function recommendedModules(): array
    {
        return ['corex-forms', 'corex-email'];
    }

    /**
     * @return list<string>
     */
    public function templates(): array
    {
        return ['front-page', 'page', 'single', 'archive', 'search', '404', 'index'];
    }

    /**
     * @return list<string>
     */
    public function parts(): array
    {
        return ['header', 'footer'];
    }

    /**
     * @return list<string>
     */
    public function patterns(): array
    {
        return [
            'corex/hero',
            'corex/features',
            'corex/cta',
            'corex/testimonial',
            'corex/contact',
            'corex/faq',
            'corex/news',
            'corex/stats',
            'corex/content-split',
            'corex/section-header',
        ];
    }

    /**
     * The full v1 company page set. The demo level (`minimal`/`standard`/`full`) keeps
     * the same page set and section order; only the home page's optional example
     * sections vary in depth, so structure parity holds across levels.
     *
     * @param string $level minimal|standard|full
     *
     * @return list<array{title:string,slug:string,content:string,front?:bool,seo?:array{title:string,description:string}}>
     */
    public function pages(string $level = 'standard'): array
    {
        $all = [
            [
                'title'   => __('Home', 'corex'),
                'slug'    => 'home',
                'front'   => true,
                'content' => $this->homeContent($level),
                'seo'     => $this->seo(__('Home', 'corex'), __('Welcome — what we do and how to reach us.', 'corex')),
            ],
            [
                'title'   => __('About', 'corex'),
                'slug'    => 'about',
                'content' => $this->pattern('section-header')
                    . $this->pattern('content-split')
                    . $this->pattern('stats')
                    . $this->pattern('testimonial'),
                'seo'     => $this->seo(__('About', 'corex'), __('Our story, mission, and team.', 'corex')),
            ],
            [
                'title'   => __('Services', 'corex'),
                'slug'    => 'services',
                // Services grid is an M5 block gap; reuse features as the section for now.
                'content' => $this->pattern('section-header')
                    . $this->pattern('features')
                    . $this->pattern('cta'),
                'seo'     => $this->seo(__('Services', 'corex'), __('What we offer.', 'corex')),
            ],
            [
                'title'   => __('Single Service', 'corex'),
                'slug'    => 'single-service',
                'content' => $this->pattern('section-header')
                    . $this->pattern('content-split')
                    . $this->pattern('cta'),
            ],
            [
                'title'   => __('Work', 'corex'),
                'slug'    => 'work',
                // Case-study/project grid is an M5 block gap; reuse news as the listing.
                'content' => $this->pattern('section-header') . $this->pattern('news'),
                'seo'     => $this->seo(__('Work', 'corex'), __('Selected case studies and projects.', 'corex')),
            ],
            [
                'title'   => __('Case Study', 'corex'),
                'slug'    => 'case-study',
                'content' => $this->pattern('section-header')
                    . $this->pattern('content-split')
                    . $this->pattern('testimonial'),
            ],
            [
                'title'   => __('Industries', 'corex'),
                'slug'    => 'industries',
                'content' => $this->pattern('section-header') . $this->pattern('features'),
            ],
            [
                'title'   => __('FAQ', 'corex'),
                'slug'    => 'faq',
                'content' => $this->pattern('section-header') . $this->pattern('faq'),
                'seo'     => $this->seo(__('FAQ', 'corex'), __('Answers to common questions.', 'corex')),
            ],
            [
                'title'   => __('Blog', 'corex'),
                'slug'    => 'blog',
                'content' => $this->pattern('section-header') . $this->pattern('news'),
                'seo'     => $this->seo(__('Blog', 'corex'), __('News and articles.', 'corex')),
            ],
            [
                'title'   => __('Team', 'corex'),
                'slug'    => 'team',
                // Team grid is an M5 block gap; reuse content-split for now.
                'content' => $this->pattern('section-header') . $this->pattern('content-split'),
            ],
            [
                'title'   => __('Testimonials', 'corex'),
                'slug'    => 'testimonials',
                'content' => $this->pattern('section-header') . $this->pattern('testimonial'),
            ],
            [
                'title'   => __('Locations', 'corex'),
                'slug'    => 'locations',
                // Locations/map section is an M5 block gap; reuse contact for now.
                'content' => $this->pattern('section-header') . $this->pattern('contact'),
            ],
            [
                'title'   => __('Contact', 'corex'),
                'slug'    => 'contact',
                'content' => $this->pattern('section-header') . $this->pattern('contact'),
                'seo'     => $this->seo(__('Contact', 'corex'), __('Get in touch with us.', 'corex')),
            ],
            $this->legalPage(__('Privacy Policy', 'corex'), 'privacy-policy', __('How we handle your data.', 'corex')),
            $this->legalPage(__('Terms', 'corex'), 'terms', __('The terms of using this site.', 'corex')),
            $this->legalPage(__('Cookie Policy', 'corex'), 'cookie-policy', __('How this site uses cookies.', 'corex')),
            [
                'title'   => __('Maintenance', 'corex'),
                'slug'    => 'maintenance',
                'content' => $this->pattern('section-header')
                    . '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">'
                    . esc_html__('We’ll be back shortly.', 'corex') . '</p><!-- /wp:paragraph -->',
            ],
        ];

        return $this->forLevel($all, $level);
    }

    /**
     * Filter the full page set to the chosen demo level (FR-137): Minimal seeds the essential
     * pages (home, about, contact) plus the always-present legal pages; Standard adds the
     * representative marketing pages; Full seeds the complete showcase.
     *
     * @param list<array{title:string,slug:string,content:string,front?:bool,seo?:array{title:string,description:string}}> $all
     *
     * @return list<array{title:string,slug:string,content:string,front?:bool,seo?:array{title:string,description:string}}>
     */
    private function forLevel(array $all, string $level): array
    {
        if ($level === 'full') {
            return $all;
        }

        $minimal  = ['home', 'about', 'contact', 'privacy-policy', 'terms', 'cookie-policy'];
        $standard = array_merge($minimal, ['services', 'work', 'blog', 'faq']);
        $allowed  = $level === 'minimal' ? $minimal : $standard;

        return array_values(array_filter($all, static fn (array $page): bool => in_array($page['slug'], $allowed, true)));
    }

    /**
     * The demo content levels this kit supports.
     *
     * @return list<string>
     */
    public function demoLevels(): array
    {
        return ['minimal', 'standard', 'full'];
    }

    private function homeContent(string $level): string
    {
        $content = $this->pattern('hero') . $this->pattern('features');

        if ($level !== 'minimal') {
            $content .= $this->pattern('stats') . $this->pattern('testimonial');
        }

        if ($level === 'full') {
            $content .= $this->pattern('news');
        }

        return $content . $this->pattern('cta') . $this->pattern('contact');
    }

    /**
     * @return array{title:string,slug:string,content:string,seo:array{title:string,description:string}}
     */
    private function legalPage(string $title, string $slug, string $description): array
    {
        return [
            'title'   => $title,
            'slug'    => $slug,
            'content' => '<!-- wp:heading {"level":1} --><h1>' . esc_html($title) . '</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>' . esc_html__('Replace this with your policy text.', 'corex')
                . '</p><!-- /wp:paragraph -->',
            'seo'     => $this->seo($title, $description),
        ];
    }

    private function pattern(string $name): string
    {
        return '<!-- wp:pattern {"slug":"corex/' . $name . '"} /-->';
    }

    /**
     * @return array{title:string,description:string}
     */
    private function seo(string $title, string $description): array
    {
        return ['title' => $title, 'description' => $description];
    }
}
