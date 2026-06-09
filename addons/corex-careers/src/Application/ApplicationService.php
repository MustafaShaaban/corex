<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Security\Upload\UploadValidator;

/**
 * Orchestrates an application: validate the required fields, validate the CV via the
 * upload validator (spec 012), store it, and notify HR + the applicant. Every
 * rejection short-circuits before any side effect (FR-004). Captcha/honeypot are the
 * endpoint's job; this validates fields + the file.
 */
final class ApplicationService
{
    public function __construct(
        private readonly ApplicationStore $store,
        private readonly UploadValidator $uploads,
        private readonly Mailer $mailer,
        private readonly string $hrEmail,
    ) {
    }

    /**
     * @param array<string,mixed>                                                    $data   name/email/cover_letter
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $cvFile
     */
    public function apply(int $jobId, array $data, array $cvFile, int $cvAttachmentId = 0): ApplicationResult
    {
        $name  = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ApplicationResult::rejected('invalid_fields');
        }

        $cv = $this->uploads->validate($cvFile);
        if (! $cv->valid) {
            return ApplicationResult::rejected('cv_' . $cv->reason);
        }

        $id = $this->store->create([
            'job_id'       => $jobId,
            'name'         => $name,
            'email'        => $email,
            'cover_letter' => (string) ($data['cover_letter'] ?? ''),
            'cv_attachment' => $cvAttachmentId,
            'status'       => StatusFlow::NEW,
        ]);

        $this->mailer->send(new MailRequest(
            to: [$this->hrEmail],
            templateName: 'careers-new-application',
            context: ['name' => $name, 'job_id' => (string) $jobId],
        ));

        $this->mailer->send(new MailRequest(
            to: [$email],
            templateName: 'careers-application-received',
            context: ['name' => $name],
        ));

        return ApplicationResult::stored($id);
    }
}
