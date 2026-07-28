<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Security\Upload\AttachmentStorage;
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
        private readonly AttachmentStorage $attachments,
        private readonly Mailer $mailer,
        private readonly string $hrEmail,
    ) {
    }

    /**
     * @param array<string,mixed>                                                    $data   name/email/cover_letter
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $cvFile
     *
     * The `int $cvAttachmentId = 0` parameter that used to sit at the end is gone. No caller ever
     * supplied it — `CareersServiceProvider` passed three arguments — so `cv_attachment` was written
     * as `0` on every application ever submitted: the CV was validated and then dropped on the
     * floor (#138 item 8). An attachment id is not something a caller can be trusted to have; it is
     * the result of storing the file, so this stores it.
     */
    public function apply(int $jobId, array $data, array $cvFile): ApplicationResult
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

        // Stored before the row is written, so an application never records an id that does not
        // resolve. The other order leaves a row pointing at nothing whenever the move fails —
        // indistinguishable, later, from the `0` this used to write.
        $stored = $this->attachments->store($cvFile, 'careers-cv');
        if (! $stored->stored) {
            return ApplicationResult::rejected('cv_' . $stored->reason);
        }

        $id = $this->store->create([
            'job_id'       => $jobId,
            'name'         => $name,
            'email'        => $email,
            'cover_letter' => (string) ($data['cover_letter'] ?? ''),
            'cv_attachment' => $stored->attachmentId,
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
