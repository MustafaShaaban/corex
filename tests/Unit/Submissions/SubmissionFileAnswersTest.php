<?php

/**
 * Which of a submission's answers is an uploaded file (spec 081, FR-007 to FR-009; Phase F).
 *
 * Reported on 2026-10-11 from a client site: a submission with an uploaded file showed, under
 * "Answers", the number `224`. The file was stored and protected as it should be. Nothing told
 * the inbox that the number named a file.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionFileAnswers;
use Corex\Tests\Support\HeldUploads;

const FILE_QUESTIONS = [
    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
    ['key' => 'cv', 'label' => 'Your CV', 'type' => 'file'],
    ['key' => 'years', 'label' => 'Years of experience', 'type' => 'number'],
];

function fileAnswers(): SubmissionFileAnswers
{
    return new SubmissionFileAnswers(new HeldUploads([
        'cv' => [
            224 => ['name' => 'salma-cv.pdf', 'missing' => false],
            230 => ['name' => 'gone.pdf', 'missing' => true],
        ],
        'portfolio' => [301 => ['name' => 'work.zip', 'missing' => false]],
        'years' => [7 => ['name' => 'not-an-answer.pdf', 'missing' => false]],
    ]));
}

it('reads the answer to a file question as the file that was uploaded', function (mixed $stored) {
    expect(fileAnswers()->file('cv', $stored, FILE_QUESTIONS))
        ->toBe(['id' => 224, 'name' => 'salma-cv.pdf', 'missing' => false]);
})->with([
    'as post meta gives it' => ['224'],
    'as a number' => [224],
]);

it('says a file is missing when the form asked for one and the id names none', function () {
    expect(fileAnswers()->file('cv', '999', FILE_QUESTIONS))
        ->toBe(['id' => 999, 'name' => '', 'missing' => true]);
});

it('reads no file where none was sent', function (mixed $stored) {
    expect(fileAnswers()->file('cv', $stored, FILE_QUESTIONS))->toBeNull();
})->with([
    'an empty answer' => [''],
    'no answer' => [null],
    'zero' => [0],
    'an address, as a careers form once stored it' => ['https://example.test/cv.pdf'],
    'a number with a fraction' => ['224.5'],
    'several values' => [['224']],
]);

it('never reads a file into an answer the form asks as something else', function () {
    // Seven years of experience is not the file numbered 7, whatever that file is.
    expect(fileAnswers()->file('years', '7', FILE_QUESTIONS))->toBeNull();
});

it('still knows the file of a question the form no longer asks', function () {
    expect(fileAnswers()->file('portfolio', '301', FILE_QUESTIONS))
        ->toBe(['id' => 301, 'name' => 'work.zip', 'missing' => false]);
});

it('leaves a number alone when the form no longer asks its question and no such file was uploaded', function () {
    expect(fileAnswers()->file('budget', '5000', FILE_QUESTIONS))->toBeNull();
});

it('describes each file for somebody reading the submission, with the address to open it', function () {
    $record = ['id' => 41, 'form' => 'apply', 'values' => ['name' => 'Salma', 'cv' => '224', 'years' => '7']];

    $described = fileAnswers()->describedIn($record, FILE_QUESTIONS);

    expect($described['values'])->toBe([
        'name' => 'Salma',
        'cv' => [
            'id' => 224,
            'name' => 'salma-cv.pdf',
            'missing' => false,
            'url' => 'https://example.test/file?submission=41&field=cv',
        ],
        'years' => '7',
    ])
        // What is not an answer is as it was.
        ->and($described['id'])->toBe(41)
        ->and($described['form'])->toBe('apply');
});

it('gives no address for a file whose record is there and whose file is not', function () {
    $described = fileAnswers()->describedIn(['id' => 41, 'values' => ['cv' => '230']], FILE_QUESTIONS);

    expect($described['values']['cv'])->toBe(['id' => 230, 'name' => 'gone.pdf', 'missing' => true, 'url' => '']);
});

it('names each file for a document that leaves the site, with no address in it', function () {
    $named = fileAnswers()->namedIn(['id' => 41, 'values' => ['name' => 'Salma', 'cv' => '224']], FILE_QUESTIONS);

    expect($named['values'])->toBe([
        'name' => 'Salma',
        'cv' => ['id' => 224, 'name' => 'salma-cv.pdf', 'missing' => false],
    ]);
});

it('returns a submission with no answers as it is', function () {
    expect(fileAnswers()->describedIn(['id' => 41], FILE_QUESTIONS))->toBe(['id' => 41]);
});
