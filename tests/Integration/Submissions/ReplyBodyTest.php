<?php

/**
 * What is posted as a reply is reduced to the formatting a reply has (spec 106, FR-004).
 *
 * Against real WordPress because the reduction is WordPress's own allowlist and its own reading
 * of markup: a stub of either would prove nothing about what reaches a customer. The typed
 * lines that become paragraphs are slice 0's fix, kept here.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\ReplyBody;

function reducedReply(string $posted): string
{
    return (string) preg_replace('/\s+/', ' ', ReplyBody::reduce($posted));
}

it('keeps paragraphs, emphasis, lists and links', function () {
    $posted = '<p>Hello <strong>Salma</strong>, <em>thank you</em>.</p>'
        . '<ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol>'
        . '<p><a href="https://example.com/offer">The offer</a><br>Regards</p>';

    expect(reducedReply($posted))
        ->toContain('<p>Hello <strong>Salma</strong>, <em>thank you</em>.</p>')
        ->toContain('<ul>')->toContain('<li>One</li>')->toContain('<ol>')
        ->toContain('<a href="https://example.com/offer">The offer</a>')
        ->toContain('Regards</p>');
});

it('removes what a reply does not have, and keeps the words', function () {
    $posted = '<h1 style="color:red">Big</h1><p class="lead" onclick="steal()">Hello</p>'
        . '<img src="https://example.com/pixel.gif" alt="x"><table><tr><td>Cell</td></tr></table>'
        . '<span style="font-size:40px">loud</span>';
    $reduced = reducedReply($posted);

    expect($reduced)->toContain('Big')->toContain('<p>Hello</p>')->toContain('Cell')->toContain('loud')
        ->not->toContain('<h1')->not->toContain('style=')->not->toContain('class=')
        ->not->toContain('onclick')->not->toContain('<img')->not->toContain('<table')->not->toContain('<span');
});

it('removes a script and a style sheet with what is inside them', function () {
    $reduced = reducedReply('<p>Before</p><script>alert("x")</script><style>p{color:red}</style><p>After</p>');

    expect($reduced)->toBe('<p>Before</p> <p>After</p>');
});

it('keeps web, mail and telephone links and removes any other, with its text kept', function (string $href, bool $kept) {
    $reduced = reducedReply('<p>See <a href="' . $href . '">this</a>.</p>');

    expect(str_contains($reduced, '<a href='))->toBe($kept)
        ->and($reduced)->toContain('this')
        ->and($reduced)->not->toContain('javascript')->not->toContain('data:');
})->with([
    'https' => ['https://example.com/a?b=1', true],
    'http' => ['http://example.com', true],
    'mail' => ['mailto:sam@example.com', true],
    'telephone' => ['tel:+201001234567', true],
    'a script' => ['javascript:alert(1)', false],
    'a script, entity-encoded' => ['java&#115;cript:alert(1)', false],
    'a script with a tab in its scheme' => ["java\tscript:alert(1)", false],
    'data' => ['data:text/html;base64,PHNjcmlwdD4=', false],
    'a file' => ['file:///etc/passwd', false],
]);

it('makes a link with no scheme a web link, and a bare address a mail link', function () {
    expect(reducedReply('<p><a href="example.com/pricing">Pricing</a></p>'))
        ->toContain('<a href="https://example.com/pricing">Pricing</a>')
        ->and(reducedReply('<p><a href="//example.com/x">X</a></p>'))
        ->toContain('<a href="https://example.com/x">X</a>')
        ->and(reducedReply('<p><a href="sales@example.com">Write to us</a></p>'))
        ->toContain('<a href="mailto:sales@example.com">Write to us</a>');
});

it('drops every attribute of a link but where it goes', function () {
    $reduced = reducedReply('<p><a href="https://example.com" target="_blank" style="color:red" onmouseover="x()">Go</a></p>');

    expect($reduced)->toBe('<p><a href="https://example.com">Go</a></p>');
});

it('turns lines typed in a plain box into paragraphs and line breaks', function () {
    $reduced = ReplyBody::reduce("Hello Salma,\n\nThank you for writing.\nWe will call you tomorrow.");

    expect($reduced)->toContain('<p>Hello Salma,</p>')
        ->toContain("<p>Thank you for writing.<br />\nWe will call you tomorrow.</p>");
});

it('keeps an Arabic reply as it was written', function () {
    $reduced = ReplyBody::reduce("مرحبا سلمى،\n\nسنتصل بك <strong>غدا</strong>.\nمع التحية");

    expect(mb_check_encoding($reduced, 'UTF-8'))->toBeTrue()
        ->and($reduced)->toContain('<p>مرحبا سلمى،</p>')
        ->and($reduced)->toContain("<p>سنتصل بك <strong>غدا</strong>.<br />\nمع التحية</p>");
});

it('answers nothing for a reply with no words in it', function (mixed $posted) {
    expect(ReplyBody::reduce($posted))->toBe('');
})->with([
    'spaces' => [" \n\n "],
    'an empty paragraph' => ['<p> </p><p>&nbsp;</p>'],
    'an empty list' => ['<ul><li></li><li> </li></ul>'],
    'only a script' => ['<script>alert(1)</script>'],
    'only a comment' => ['<!-- nothing to see -->'],
    'a list, where a message was expected' => [['x']],
    'a number' => [5],
    'something far too long to be a reply' => [str_repeat('<p>word</p>', 30_000)],
]);

// Each of these reached a customer, or would have, before it was a test: found by a review
// that ran them, not by reading.
it('removes a comment with what is inside it, and does not let one hide the rest', function () {
    expect(reducedReply('<p>Hello</p><!-- a note to myself --><p>Bye</p>'))->toBe('<p>Hello</p> <p>Bye</p>')
        ->and(reducedReply('Hello <!--> the rest of my message'))->toBe('<p>Hello</p>')
        ->and(reducedReply('<p>Hello</p><!--[if gte mso 9]><xml><o:p></o:p></xml><![endif]--><p>Bye</p>'))
        ->toBe('<p>Hello</p> <p>Bye</p>');
});

it('leaves no link to anywhere but the web, a mailbox or a telephone, wherever the link was written', function (string $posted) {
    $reduced = reducedReply($posted);

    expect($reduced)->not->toContain('<a')
        ->and($reduced)->not->toContain('href')
        ->and($reduced)->toContain('click');
})->with([
    'inside xmp' => ['<xmp><a href="javascript:alert(1)">click</a></xmp>'],
    'inside a textarea' => ['<textarea><a href="javascript:alert(1)">click</a></textarea>'],
    'inside a title' => ['<title><a href="javascript:alert(1)">click</a></title>'],
    'inside an iframe' => ['<iframe><a href="javascript:alert(1)">click</a></iframe>'],
    'inside noembed' => ['<noembed><a href="javascript:alert(1)">click</a></noembed>'],
    'inside a CDATA section' => ['<![CDATA[<a href="javascript:alert(1)">click</a>]]>'],
    'data' => ['<a href="data:text/html,x">click</a>'],
    'a host with a port and no scheme, which reads as a scheme' => ['<a href="example.com:8080/x">click</a>'],
    'to a place on a page' => ['<a href="#top">click</a>'],
    'to a path' => ['<a href="/pricing">click</a>'],
    'to a word' => ['<a href="pricing">click</a>'],
    'with no address' => ['<a>click</a>'],
    'not closed' => ['<a href="javascript:alert(1)">click'],
]);

it('reads a tag written with a slash where a space belongs as the text it is', function () {
    $reduced = reducedReply('<a/href="javascript:alert(1)">click</a>');

    expect($reduced)->not->toContain('<a')
        ->and($reduced)->toStartWith('<p>&lt;a/href=');
});

it('keeps where a link goes as it was written', function (string $written, string $kept) {
    expect(reducedReply('<p><a href="' . $written . '">here</a></p>'))->toBe('<p><a href="' . $kept . '">here</a></p>');
})->with([
    'a space in a file name' => ['https://example.com/a b.pdf', 'https://example.com/a%20b.pdf'],
    'a host with a port' => ['https://example.com:8080/x', 'https://example.com:8080/x'],
    'an address with a subject' => ['sam@example.com?subject=Hi', 'mailto:sam@example.com?subject=Hi'],
    'a query' => ['https://example.com/?a=1&amp;b=2', 'https://example.com/?a=1&#038;b=2'],
]);

it('keeps what somebody typed between angle brackets', function () {
    expect(reducedReply('Contact Sam <sam@example.com> for details.'))
        ->toBe('<p>Contact Sam &lt;sam@example.com&gt; for details.</p>')
        ->and(reducedReply('Our price is <500 EGP and >300 EGP.'))
        ->toBe('<p>Our price is &lt;500 EGP and &gt;300 EGP.</p>');
});
