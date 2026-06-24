<?php

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use App\Services\Claude\DocumentBlock;
use Tests\Fakes\FakeClaudeClient;
use Tests\TestCase;

// These tests exercise the service-provider container binding, so they boot
// the full framework. They still never touch the network — the binding is
// swapped for an in-memory fake.
uses(TestCase::class);

it('resolves the bound fake from the container', function () {
    $fake = new FakeClaudeClient;
    $this->app->instance(ClaudeClient::class, $fake);

    $resolved = $this->app->make(ClaudeClient::class);

    expect($resolved)->toBe($fake);
    expect($resolved)->toBeInstanceOf(ClaudeClient::class);
});

it('lets the fake return a canned ClaudeResponse with content and usage', function () {
    $this->app->instance(ClaudeClient::class, new FakeClaudeClient);

    $response = $this->app->make(ClaudeClient::class)->message('hello');

    expect($response)->toBeInstanceOf(ClaudeResponse::class);
    expect($response->content)->toBe([['type' => 'text', 'text' => 'canned reply']]);
    expect($response->inputTokens)->toBe(12);
    expect($response->outputTokens)->toBe(7);
    expect($response->totalTokens())->toBe(19);
    expect($response->text())->toBe('canned reply');
});

it('captures document, tool, and tool_choice arguments passed by callers', function () {
    $fake = new FakeClaudeClient;
    $this->app->instance(ClaudeClient::class, $fake);

    $doc = DocumentBlock::image('base64data', 'image/png');
    $tool = [
        'name' => 'extract_invoice',
        'description' => 'Extract structured invoice fields',
        'input_schema' => ['type' => 'object', 'properties' => []],
    ];

    $this->app->make(ClaudeClient::class)->message(
        prompt: 'extract this',
        documents: [$doc],
        tool: $tool,
        toolChoice: 'extract_invoice',
    );

    expect($fake->lastPrompt)->toBe('extract this');
    expect($fake->lastDocuments)->toBe([$doc]);
    expect($fake->lastTool)->toBe($tool);
    expect($fake->lastToolChoice)->toBe('extract_invoice');
});

it('builds image and pdf document blocks with the right media kind', function () {
    $image = DocumentBlock::image('imgdata', 'image/jpeg');
    $pdf = DocumentBlock::pdf('pdfdata');

    expect($image->kind)->toBe('image');
    expect($image->mediaType)->toBe('image/jpeg');
    expect($image->data)->toBe('imgdata');

    expect($pdf->kind)->toBe('pdf');
    expect($pdf->mediaType)->toBe('application/pdf');

    expect($image->toAnthropicBlock())->toBe([
        'type' => 'image',
        'source' => [
            'type' => 'base64',
            'media_type' => 'image/jpeg',
            'data' => 'imgdata',
        ],
    ]);

    expect($pdf->toAnthropicBlock())->toBe([
        'type' => 'document',
        'source' => [
            'type' => 'base64',
            'media_type' => 'application/pdf',
            'data' => 'pdfdata',
        ],
    ]);
});
