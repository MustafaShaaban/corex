<?php

/**
 * The data layer hands back the text it was handed.
 *
 * WordPress takes one level of slashes off a post's fields and off a meta value on their way
 * in. The field driver and the post repository handed text over as it was, so a backslash was
 * gone from everything stored through them: a model's title, its content and each of its
 * fields (measured 2026-10-13, DECISIONS #331).
 *
 * On real WordPress, through a repository of a model defined here.
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\QueryExecutor;
use Corex\Fields\MetaFieldDriver;
use Corex\Models\Model;
use Corex\Repositories\Hydrator;
use Corex\Repositories\PostRepository;
use Corex\Tests\Support\BackslashedText;
use Corex\Tests\Support\CreatedPosts;

final class KeptRecord extends Model
{
    public static function postType(): string
    {
        return 'corex_kept_record';
    }

    public static function fields(): array
    {
        return ['summary' => 'corex_kept_summary', 'details' => 'corex_kept_details'];
    }
}

final class KeptRecordRepository extends PostRepository
{
    protected function model(): string
    {
        return KeptRecord::class;
    }
}

beforeEach(function () {
    register_post_type(KeptRecord::postType(), ['public' => false]);
    $this->records = CreatedPosts::watch(KeptRecord::postType());
    $this->driver = new MetaFieldDriver();
    $this->repository = new KeptRecordRepository(
        $this->driver,
        new Hydrator($this->driver),
        Boot::app()->container()->make(QueryExecutor::class),
    );
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $this->records->delete();
    unregister_post_type(KeptRecord::postType());
});

it('hands back a field value as the driver was handed it', function (string $text) {
    $id = $this->repository->create(['title' => 'A record'])->id();

    $this->driver->set($id, 'corex_kept_summary', $text);

    expect($this->driver->get($id, 'corex_kept_summary'))->toBe($text);
})->with(BackslashedText::cases());

it('hands back text inside a list as the driver was handed it', function (string $text) {
    $id = $this->repository->create(['title' => 'A record'])->id();
    $value = ['text' => $text, 'inside' => ['a list' => [$text], 'deeper' => ['still' => $text]]];

    $this->driver->set($id, 'corex_kept_details', $value);

    expect($this->driver->get($id, 'corex_kept_details'))->toBe($value);
})->with(BackslashedText::cases());

it('hands back what is not text inside a list as the kind of value it was', function () {
    // A submission's `is_test` and a flow's version are read where they are a boolean and a
    // whole number: putting slashes on must not make text of either.
    $id = $this->repository->create(['title' => 'A record'])->id();
    $value = ['count' => 3, 'ratio' => 1.5, 'on' => true, 'off' => false, 'nothing' => null, 'empty' => ''];

    $this->driver->set($id, 'corex_kept_details', $value);

    expect($this->driver->get($id, 'corex_kept_details'))->toBe($value);
});

it('keeps a single number and a single switch as WordPress keeps them', function () {
    // WordPress stores a value that is not a list as text, and no value for `false`. That is
    // how these were read before, and what reads them (`(int)`, `(bool)`) counts on.
    $id = $this->repository->create(['title' => 'A record'])->id();

    $this->driver->set($id, 'corex_kept_count', 3);
    $this->driver->set($id, 'corex_kept_on', true);
    $this->driver->set($id, 'corex_kept_off', false);

    expect($this->driver->get($id, 'corex_kept_count'))->toBe('3')
        ->and($this->driver->get($id, 'corex_kept_on'))->toBe('1')
        ->and($this->driver->get($id, 'corex_kept_off', 'nothing stored'))->toBe('nothing stored');
});

it('creates a record with its title, its content and its fields as they were handed over', function (string $text) {
    $record = $this->repository->create(['title' => $text, 'content' => $text, 'summary' => $text]);

    expect($record->get('title'))->toBe($text)
        ->and($record->get('content'))->toBe($text)
        ->and($record->get('summary'))->toBe($text);
})->with(BackslashedText::cases());

it('updates a record with its title, its content and its fields as they were handed over', function (string $text) {
    $id = $this->repository->create(['title' => 'A record', 'content' => 'Some text', 'summary' => 'A summary'])->id();

    $record = $this->repository->update($id, ['title' => $text, 'content' => $text, 'summary' => $text]);

    expect($record->get('title'))->toBe($text)
        ->and($record->get('content'))->toBe($text)
        ->and($record->get('summary'))->toBe($text);
})->with(BackslashedText::cases());

it('keeps a record that is read and written back, however many times', function () {
    $record = $this->repository->create(['title' => BackslashedText::ALL, 'content' => BackslashedText::ALL]);

    foreach ([1, 2, 3] as $ignored) {
        $record = $this->repository->update($record->id(), [
            'title' => $record->get('title'),
            'content' => $record->get('content'),
        ]);
    }

    expect($record->get('title'))->toBe(BackslashedText::ALL)
        ->and($record->get('content'))->toBe(BackslashedText::ALL);
});

it('finds a record by a field it was created with', function () {
    // A query looks for the text as it was handed over, so it found nothing while the text
    // was stored without its backslashes.
    $record = $this->repository->create(['title' => 'A record', 'status' => 'private', 'summary' => 'C:\\offers\\new']);
    $this->repository->create(['title' => 'Another record', 'status' => 'private', 'summary' => 'C:offersnew']);

    $found = $this->repository->query()->where('post_status', 'private')->where('summary', 'C:\\offers\\new')->get();

    expect(array_map(static fn (Model $model): int => $model->id(), iterator_to_array($found)))->toBe([$record->id()]);
});

it('keeps the status, the slug and the parent of a record', function () {
    // The other post fields a repository writes: a fixed word, an address and a whole number.
    $parent = $this->repository->create(['title' => 'A parent'])->id();

    $record = $this->repository->create(['title' => 'A record', 'status' => 'private', 'slug' => 'a-kept-record', 'parent' => $parent]);

    expect($record->get('status'))->toBe('private')
        ->and($record->get('slug'))->toBe('a-kept-record')
        ->and($record->get('parent'))->toBe($parent);
});

it('keeps the title and the content of a record made in a request nobody is signed in for', function () {
    // A visitor's form is stored in such a request, and WordPress cleans a post's content as
    // markup for a person who may not post any: the cleaning is done on slashed text.
    wp_set_current_user(0);

    $record = $this->repository->create(['title' => 'C:\\offers\\new', 'content' => BackslashedText::ALL]);

    expect($record->get('title'))->toBe('C:\\offers\\new')
        ->and($record->get('content'))->toBe(BackslashedText::ALL);
});
