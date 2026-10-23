<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Design\EmailKind;
use Corex\Email\Routing\EmailRouteMessageFactory;
use Corex\Mail\MailResult;
use Corex\Mail\SubjectLine;
use DomainException;

/**
 * The preview and the test send of an Email Studio template (spec 120, story 1): the draft being
 * edited, drawn on the server by the code that sends it, with sample values, and the same email
 * sent to one address with "[Test]" before its subject.
 */
final readonly class EmailTemplatePreview
{
    public function __construct(
        private EmailTemplateService $editor,
        private EmailSampleValues $samples,
        private EmailVariableCatalog $catalog,
        private EmailLayoutRepository $layouts,
        private DefaultEmailTemplates $defaults,
        private EmailTemplateWarnings $warnings,
        private EmailStudioService $studio,
        private EmailDeliveryContextFactory $delivery,
    ) {
    }

    /**
     * @param array<string,mixed> $overrides Sample values typed beside the preview.
     *
     * @return array{subject:string,html:string,text:string,samples:list<array{key:string,label:string,value:string,editable:bool}>,warnings:array<string,string>}
     *
     * @throws EmailTemplateUnrenderable
     */
    public function preview(EmailTemplate $template, EmailTemplateVersion $draft, array $overrides = [], ?string $direction = null): array
    {
        [$rendered, $samples] = $this->draw($template, $draft, $overrides, $direction);

        return [
            'subject'  => $rendered['subject'],
            'html'     => $rendered['html'],
            'text'     => $rendered['plain'],
            'samples'  => $samples,
            'warnings' => $this->warnings->of($template, $draft),
        ];
    }

    /**
     * Sends the previewed email to one address, as the site's delivery rules decide.
     *
     * @param array<string,mixed> $overrides
     *
     * @return array{result:MailResult,summary:string,reason:string,log_url:string}
     *
     * @throws EmailTemplateUnrenderable
     */
    public function testSend(EmailTemplate $template, EmailTemplateVersion $draft, string $to, array $overrides = []): array
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new EmailTemplateUnrenderable(['to' => sprintf(
                /* translators: %s: what was typed as the test address. */
                __('Enter a valid email address: %s is not one.', 'corex'),
                $to === '' ? __('an empty field', 'corex') : $to,
            )]);
        }
        [$rendered] = $this->draw($template, $draft, $overrides, null);
        $rendered['subject'] = SubjectLine::of(sprintf(
            /* translators: %s: the email's subject. */
            __('[Test] %s', 'corex'),
            $rendered['subject'],
        ));
        $delivery = $this->delivery->make();
        $decision = $this->studio->decide($delivery);
        $result = $this->studio->testSend(
            EmailRouteMessageFactory::message($draft, [$to], null, $rendered),
            $delivery,
            new EmailTemplateReference(
                $template->slug,
                $template->id,
                $template->activeVersion ?: ($template->draftVersion ?: null),
            ),
        );

        return [
            'result'  => $result,
            'summary' => $this->summary($result, $decision->code),
            'reason'  => $result->message,
            'log_url' => add_query_arg(['page' => 'corex-email-studio', 'tab' => 'logs'], admin_url('admin.php'))
                . '#corex-attempt-' . $result->attemptId,
        ];
    }

    /**
     * The variables by the emails that supply them, each with its sample: the Variables tab.
     *
     * @return list<array{key:string,label:string,variables:list<array{key:string,label:string,sample:string}>}>
     */
    public function variableGroups(): array
    {
        return array_map(
            fn (array $group): array => [
                ...$group,
                'variables' => array_map(
                    fn (array $variable): array => [
                        ...$variable,
                        'sample' => $variable['key'] === EmailVariableCatalog::ANSWERS
                            ? __('Every answer, with its label, in the form\'s order', 'corex')
                            : $this->samples->valueOf($variable['key']),
                    ],
                    $group['variables'],
                ),
            ],
            $this->catalog->groups(),
        );
    }

    /**
     * @param array<string,mixed> $overrides
     *
     * @return array{0:array{subject:string,html:string,plain:string,missing:list<string>},1:list<array{key:string,label:string,value:string,editable:bool}>}
     */
    private function draw(EmailTemplate $template, EmailTemplateVersion $draft, array $overrides, ?string $direction): array
    {
        $errors = $this->editor->validateDraft($draft);
        $layout = $this->layouts->findVersion($draft->layoutId, $draft->layoutVersion);
        if ($layout === null) {
            $errors['layout_id'] = __('The layout revision this template is arranged by is not available. Choose another.', 'corex');
        }
        if ($errors !== []) {
            throw new EmailTemplateUnrenderable($errors);
        }
        $samples = $this->samples->for($this->editor->variablesOf($draft), $overrides);
        $kind = $this->defaults->definition($template->slug)?->kind
            ?? ($layout?->slug === 'newsletter' ? EmailKind::NEWSLETTER : EmailKind::MESSAGE);

        try {
            $rendered = $this->editor->render($draft, EmailRouteMessageFactory::withSiteContext($samples['context']), $layout, $kind, $direction);
        } catch (DomainException $error) {
            throw new EmailTemplateUnrenderable(['html' => $error->getMessage()]);
        }

        return [$rendered, $samples['list']];
    }

    /** What a test send did, in words. */
    private function summary(MailResult $result, string $decision): string
    {
        return match ($result->state) {
            MailResult::STATE_CAPTURED => __('Captured: nothing left this site', 'corex'),
            MailResult::STATE_SENT, MailResult::STATE_ACCEPTED => __('Delivered to the mail server', 'corex'),
            MailResult::STATE_FAILED => __('Failed: the mail server did not accept it', 'corex'),
            default => match ($decision) {
                DeliveryPolicy::NO_PROVIDER => __('Blocked: no mail provider is set', 'corex'),
                DeliveryPolicy::LIVE_OFF => __('Blocked: live delivery is switched off', 'corex'),
                DeliveryPolicy::UNKNOWN_ENVIRONMENT => __('Blocked: this site does not say which kind of site it is', 'corex'),
                default => __('Not sent', 'corex'),
            },
        };
    }
}
