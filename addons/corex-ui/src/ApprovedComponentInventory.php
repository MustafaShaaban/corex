<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

/**
 * The approved "Blocks & Components" inventory (Spec 068 US9 / FR-154, design file
 * `Corex Blocks & Components.dc.html`). It records the four design categories exactly as approved —
 * 33 company/content blocks, 8 WooCommerce blocks, 13 admin/product components, 23 core UI
 * components — with each item's design status, priority, and the real delivery `resolution` that
 * ships it. A resolution is one of:
 *
 *   - `corex-block:<slug>`  a registered `corex/*` dynamic block (drift-checked against real block.json)
 *   - `block-style:<name>`  a `register_block_style()` entry in {@see Blocks\BlockStyles}
 *   - `pattern:<name>`      a PatternLibrary entry or a `theme/patterns/*.php` file
 *   - `admin:<selector>`    an admin component CSS selector shipped by a corex plugin/add-on stylesheet
 *   - `runtime:<class>`     a token-only runtime utility CSS class (skeleton/toast/tooltip)
 *   - `core-block`          composed from core WordPress blocks + tokens (design's "prefer core" rule)
 *   - `deferred:<reason>`   not current: `woocommerce-dependency`, `future-pro`, or `phase-2`
 *
 * The reconciliation test proves every non-deferred resolution points at a real artifact, so this
 * inventory can never claim a component the framework does not actually ship. Pure data — no WP calls.
 */
final class ApprovedComponentInventory
{
    public const CATEGORY_CONTENT = 'content';
    public const CATEGORY_WOO     = 'woocommerce';
    public const CATEGORY_ADMIN   = 'admin';
    public const CATEGORY_CORE_UI = 'core-ui';

    // Design status (from the approved inventory legend).
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVISION = 'revision';
    public const STATUS_MISSING  = 'missing';
    public const STATUS_FUTURE   = 'future';

    // Delivery-resolution prefixes.
    public const RES_COREX_BLOCK = 'corex-block';
    public const RES_BLOCK_STYLE = 'block-style';
    public const RES_PATTERN     = 'pattern';
    public const RES_ADMIN       = 'admin';
    public const RES_RUNTIME     = 'runtime';
    public const RES_CORE_BLOCK  = 'core-block';
    public const RES_DEFERRED    = 'deferred';

    /**
     * A / Company / content blocks — 33 approved design items.
     * Tuple: [name, status, priority, resolution].
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private const CONTENT = [
        ['Hero variants',            self::STATUS_APPROVED, 'high',   'corex-block:hero'],
        ['Section header',           self::STATUS_APPROVED, 'high',   'pattern:corex/section-header'],
        ['Services grid',            self::STATUS_APPROVED, 'high',   'pattern:section-services-grid'],
        ['Service detail',           self::STATUS_REVISION, 'medium', 'pattern:corex/content-split'],
        ['Features grid',            self::STATUS_APPROVED, 'high',   'pattern:corex/features'],
        ['Icon box',                 self::STATUS_APPROVED, 'medium', 'core-block'],
        ['CTA',                      self::STATUS_APPROVED, 'high',   'corex-block:cta'],
        ['Stats',                    self::STATUS_APPROVED, 'medium', 'corex-block:stat'],
        ['Testimonials',             self::STATUS_APPROVED, 'high',   'corex-block:testimonial'],
        ['Testimonial slider',       self::STATUS_REVISION, 'medium', 'corex-block:carousel'],
        ['Logo cloud',               self::STATUS_APPROVED, 'medium', 'pattern:section-logo-cloud'],
        ['Logo carousel',            self::STATUS_REVISION, 'medium', 'corex-block:carousel'],
        ['Process / steps',          self::STATUS_APPROVED, 'medium', 'pattern:section-process-steps'],
        ['FAQ group',                self::STATUS_APPROVED, 'high',   'pattern:corex/faq'],
        ['Rich tabs',                self::STATUS_REVISION, 'high',   'corex-block:tabs'],
        ['Accordion',                self::STATUS_APPROVED, 'high',   'corex-block:accordion'],
        ['Pricing',                  self::STATUS_APPROVED, 'high',   'corex-block:pricing'],
        ['Pricing comparison',       self::STATUS_REVISION, 'medium', 'block-style:corex-striped'],
        ['Case study grid',          self::STATUS_REVISION, 'medium', 'pattern:section-selected-work'],
        ['Project card',             self::STATUS_REVISION, 'medium', 'pattern:project-card'],
        ['Gallery',                  self::STATUS_REVISION, 'medium', 'corex-block:gallery'],
        ['Gallery carousel',         self::STATUS_MISSING,  'low',    'corex-block:carousel'],
        ['Featured posts',           self::STATUS_APPROVED, 'medium', 'corex-block:posts'],
        ['Newsletter signup',        self::STATUS_APPROVED, 'medium', 'pattern:footer-newsletter'],
        ['Contact info cards',       self::STATUS_APPROVED, 'medium', 'pattern:section-contact-info'],
        ['Map / location',           self::STATUS_MISSING,  'medium', 'deferred:phase-2'],
        ['Team grid',                self::STATUS_APPROVED, 'medium', 'corex-block:team'],
        ['Timeline',                 self::STATUS_REVISION, 'low',    'deferred:phase-2'],
        ['Trust badges',             self::STATUS_APPROVED, 'low',    'core-block'],
        ['Before / after',           self::STATUS_MISSING,  'low',    'deferred:phase-2'],
        ['Video modal',              self::STATUS_REVISION, 'medium', 'corex-block:modal'],
        ['Resource / download card', self::STATUS_REVISION, 'low',    'core-block'],
        ['Related posts / projects', self::STATUS_APPROVED, 'low',    'corex-block:posts'],
    ];

    /**
     * B / WooCommerce blocks — 8 items, all dependency-gated until WooCommerce ships (design Phase 3).
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private const WOO = [
        ['Product category grid',    self::STATUS_MISSING, 'high',   'deferred:woocommerce-dependency'],
        ['Featured products',        self::STATUS_MISSING, 'high',   'deferred:woocommerce-dependency'],
        ['Best sellers',             self::STATUS_MISSING, 'medium', 'deferred:woocommerce-dependency'],
        ['Product benefits strip',   self::STATUS_MISSING, 'medium', 'deferred:woocommerce-dependency'],
        ['Shipping / returns strip', self::STATUS_MISSING, 'medium', 'deferred:woocommerce-dependency'],
        ['Product comparison',       self::STATUS_FUTURE,  'medium', 'deferred:future-pro'],
        ['Store FAQ',                self::STATUS_REVISION, 'low',   'deferred:woocommerce-dependency'],
        ['Promo banner',            self::STATUS_MISSING,  'medium', 'deferred:woocommerce-dependency'],
    ];

    /**
     * C / Admin / product components — 13 approved design items.
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private const ADMIN = [
        ['Status card',        self::STATUS_APPROVED, 'high',   'admin:corex-site-status'],
        ['Metric card',        self::STATUS_APPROVED, 'high',   'admin:corex-stat-card'],
        ['Add-on card',        self::STATUS_APPROVED, 'high',   'admin:corex-addon-card'],
        ['Dependency card',    self::STATUS_REVISION, 'high',   'admin:corex-addon-card__requires'],
        ['Readiness checklist', self::STATUS_REVISION, 'high',  'admin:corex-overview__checks'],
        ['Event log row',      self::STATUS_APPROVED, 'medium', 'admin:corex-activity'],
        ['Setup wizard step',  self::STATUS_APPROVED, 'high',   'admin:corex-setup__step'],
        ['Empty state',        self::STATUS_APPROVED, 'high',   'block-style:corex-empty'],
        ['Skeleton loader',    self::STATUS_APPROVED, 'medium', 'runtime:corex-skeleton'],
        ['Toast',              self::STATUS_APPROVED, 'medium', 'runtime:corex-toast'],
        ['Data table',         self::STATUS_APPROVED, 'high',   'admin:corex-data__table'],
        ['Filter bar',         self::STATUS_REVISION, 'medium', 'admin:corex-data__toolbar'],
        ['Edition badge',      self::STATUS_APPROVED, 'high',   'admin:corex-badge--tier'],
    ];

    /**
     * D / Core UI components — 23 approved design items.
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private const CORE_UI = [
        ['Buttons',      self::STATUS_APPROVED, 'high',   'admin:button.button'],
        ['Button groups', self::STATUS_APPROVED, 'medium', 'core-block'],
        ['Inputs',       self::STATUS_APPROVED, 'high',   'admin:input[type="text"]'],
        ['Textarea',     self::STATUS_APPROVED, 'medium', 'admin:textarea'],
        ['Select',       self::STATUS_APPROVED, 'high',   'admin:corex-select'],
        ['Checkbox',     self::STATUS_APPROVED, 'medium', 'admin:input[type="checkbox"]'],
        ['Radio',        self::STATUS_APPROVED, 'medium', 'admin:input[type="radio"]'],
        ['Switch',       self::STATUS_APPROVED, 'medium', 'admin:corex-toggle'],
        ['File upload',  self::STATUS_APPROVED, 'medium', 'core-block'],
        ['Search input', self::STATUS_APPROVED, 'medium', 'admin:input[type="search"]'],
        ['Badges',       self::STATUS_APPROVED, 'medium', 'corex-block:badge'],
        ['Alerts',       self::STATUS_APPROVED, 'high',   'corex-block:alert'],
        ['Tooltips',     self::STATUS_APPROVED, 'low',    'runtime:corex-tooltip'],
        ['Popovers',     self::STATUS_REVISION, 'low',    'deferred:phase-2'],
        ['Dropdowns',    self::STATUS_APPROVED, 'medium', 'core-block'],
        ['Drawers',      self::STATUS_REVISION, 'medium', 'corex-block:drawer'],
        ['Modals',       self::STATUS_APPROVED, 'high',   'corex-block:modal'],
        ['Tables',       self::STATUS_APPROVED, 'high',   'block-style:corex-striped'],
        ['Pagination',   self::STATUS_APPROVED, 'medium', 'admin:corex-data__pagination'],
        ['Tabs',         self::STATUS_REVISION, 'high',   'corex-block:tabs'],
        ['Accordion',    self::STATUS_APPROVED, 'medium', 'corex-block:accordion'],
        ['Stepper',      self::STATUS_REVISION, 'medium', 'admin:corex-setup__step'],
        ['Progress bar', self::STATUS_APPROVED, 'low',    'admin:corex-setup__progress'],
    ];

    /**
     * The category → design item-count contract, asserted against the approved design file.
     *
     * @var array<string,int>
     */
    private const EXPECTED_COUNTS = [
        self::CATEGORY_CONTENT => 33,
        self::CATEGORY_WOO     => 8,
        self::CATEGORY_ADMIN   => 13,
        self::CATEGORY_CORE_UI => 23,
    ];

    /**
     * @return list<array{name:string,category:string,status:string,priority:string,resolution:string}>
     */
    public function items(): array
    {
        $items = [];

        $items = array_merge($items, $this->rows(self::CONTENT, self::CATEGORY_CONTENT));
        $items = array_merge($items, $this->rows(self::WOO, self::CATEGORY_WOO));
        $items = array_merge($items, $this->rows(self::ADMIN, self::CATEGORY_ADMIN));
        $items = array_merge($items, $this->rows(self::CORE_UI, self::CATEGORY_CORE_UI));

        return $items;
    }

    /**
     * @param list<array{0:string,1:string,2:string,3:string}> $rows
     * @return list<array{name:string,category:string,status:string,priority:string,resolution:string}>
     */
    private function rows(array $rows, string $category): array
    {
        return array_map(
            static fn (array $row): array => [
                'name'       => $row[0],
                'category'   => $category,
                'status'     => $row[1],
                'priority'   => $row[2],
                'resolution' => $row[3],
            ],
            $rows
        );
    }

    /**
     * @return list<array{name:string,category:string,status:string,priority:string,resolution:string}>
     */
    public function byCategory(string $category): array
    {
        return array_values(array_filter(
            $this->items(),
            static fn (array $item): bool => $item['category'] === $category
        ));
    }

    /**
     * Items whose delivery is not deferred — every one MUST resolve to a real shipped artifact.
     *
     * @return list<array{name:string,category:string,status:string,priority:string,resolution:string}>
     */
    public function deliverable(): array
    {
        return array_values(array_filter(
            $this->items(),
            static fn (array $item): bool => !str_starts_with($item['resolution'], self::RES_DEFERRED . ':')
        ));
    }

    /** @return array<string,int> */
    public function expectedCounts(): array
    {
        return self::EXPECTED_COUNTS;
    }

    /**
     * The `corex/<slug>` blocks this inventory claims — drift-checked against the real blocks.
     *
     * @return list<string>
     */
    public function blockSlugs(): array
    {
        $slugs = [];

        foreach ($this->items() as $item) {
            if (str_starts_with($item['resolution'], self::RES_COREX_BLOCK . ':')) {
                $slugs[] = substr($item['resolution'], strlen(self::RES_COREX_BLOCK) + 1);
            }
        }

        return array_values(array_unique($slugs));
    }
}
