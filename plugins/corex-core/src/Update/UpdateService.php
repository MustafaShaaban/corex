<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Update;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Wires Corex into WordPress's plugin-update flow so a published newer version surfaces as an
 * available update in wp-admin — like any plugin. It fetches an update manifest from the
 * configured endpoint, asks the pure UpdateChecker if it is newer, and injects the update
 * into the update transient (and the details popup). Fail-safe: a missing/unreachable/
 * malformed source is a silent no-op. WordPress's own updater downloads + installs the
 * package, replacing only the framework plugin's files (spec 034).
 */
final class UpdateService
{
    public function __construct(
        private readonly UpdateChecker $checker,
        private readonly string $pluginFile,
        private readonly string $slug,
        private readonly string $currentVersion,
        private readonly ConfigInterface $config,
    ) {
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkUpdates']);
        add_filter('plugins_api', [$this, 'details'], 10, 3);
    }

    /**
     * @param mixed $transient the update_plugins site transient
     *
     * @return mixed
     */
    public function checkUpdates($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }

        $update = $this->checker->check($this->currentVersion, $this->fetchManifest());

        if ($update !== null) {
            if (! isset($transient->response) || ! is_array($transient->response)) {
                $transient->response = [];
            }
            $transient->response[$this->pluginFile] = $this->toUpdateObject($update);
        }

        return $transient;
    }

    /**
     * The WP plugin-update object for an available update (the shape WP expects in the
     * transient's `response`).
     *
     * @param array{new_version:string,package:string,url:string,requires:string,tested:string} $update
     */
    public function toUpdateObject(array $update): object
    {
        return (object) [
            'slug'        => $this->slug,
            'plugin'      => $this->pluginFile,
            'new_version' => $update['new_version'],
            'package'     => $update['package'],
            'url'         => $update['url'],
            'requires'    => $update['requires'],
            'tested'      => $update['tested'],
        ];
    }

    /**
     * @param mixed  $result
     * @param string $action
     * @param object $args
     *
     * @return mixed
     */
    public function details($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }

        $update = $this->checker->check($this->currentVersion, $this->fetchManifest());

        if ($update === null) {
            return $result;
        }

        return (object) [
            'name'          => 'Corex',
            'slug'          => $this->slug,
            'version'       => $update['new_version'],
            'download_link' => $update['package'],
            'homepage'      => $update['url'],
            'sections'      => ['description' => esc_html__('Corex framework update.', 'corex')],
        ];
    }

    /**
     * Fetch the update manifest from the configured endpoint. Fail-safe: empty endpoint, a
     * transport error, or malformed JSON all yield an empty manifest (→ no update offered).
     *
     * @return array<string,mixed>
     */
    public function fetchManifest(): array
    {
        $endpoint = trim((string) $this->config->get('updates.endpoint', ''));

        if ($endpoint === '') {
            return [];
        }

        $response = wp_remote_get($endpoint, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return [];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($data) ? $data : [];
    }
}
