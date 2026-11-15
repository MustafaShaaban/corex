<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

/**
 * What a visitor reads where a form that is not taking answers would be (spec 122, US-VIS-01): a
 * title, a message and, when the owner set one, another way to get in touch. Already translated.
 */
final readonly class FlowNotice
{
    public function __construct(
        public string $title,
        public string $message,
        public FlowAlternative $alternative = new FlowAlternative(),
    ) {
    }

    /**
     * The alternative as a link: its address (`mailto:` for an email) and the words it shows.
     *
     * @return array{type:string,label:string,url:string}
     */
    public function link(): array
    {
        $alternative = $this->alternative;

        return match ($alternative->type) {
            FlowAlternative::LINK => ['type' => FlowAlternative::LINK, 'label' => $alternative->label, 'url' => $alternative->url],
            FlowAlternative::EMAIL => [
                'type' => FlowAlternative::EMAIL,
                'label' => $alternative->label !== '' ? $alternative->label : $alternative->email,
                'url' => 'mailto:' . $alternative->email,
            ],
            default => ['type' => FlowAlternative::NONE, 'label' => '', 'url' => ''],
        };
    }

    /** @return array{title:string,message:string,alternative:array{type:string,label:string,url:string}} */
    public function toArray(): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'alternative' => $this->link()];
    }
}
