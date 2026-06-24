<?php

declare(strict_types=1);

use App\DTO\AuditEntry;
use App\Services\Invoice\AuditLog;

describe('AuditEntry', function () {
    it('is a JSON-serializable value object with required fields', function () {
        $entry = new AuditEntry(
            step: 'ExtractionService',
            status: 'ok',
            duration: 1.234,
        );

        $arr = $entry->toArray();

        expect($arr['step'])->toBe('ExtractionService');
        expect($arr['status'])->toBe('ok');
        expect($arr['duration'])->toBe(1.234);
    });

    it('LLM-step entry carries model id and token usage', function () {
        $entry = new AuditEntry(
            step: 'ExtractionService',
            status: 'ok',
            duration: 0.8,
            modelId: 'claude-opus-4-8',
            inputTokens: 512,
            outputTokens: 256,
        );

        $arr = $entry->toArray();

        expect($arr['model_id'])->toBe('claude-opus-4-8');
        expect($arr['input_tokens'])->toBe(512);
        expect($arr['output_tokens'])->toBe(256);
    });

    it('deterministic-step entry carries error count', function () {
        $entry = new AuditEntry(
            step: 'ValidationService',
            status: 'ok',
            duration: 0.01,
            errorCount: 3,
        );

        $arr = $entry->toArray();

        expect($arr['error_count'])->toBe(3);
    });

    it('implements JsonSerializable and encodes cleanly', function () {
        $entry = new AuditEntry(
            step: 'ConfidenceScorer',
            status: 'ok',
            duration: 0.05,
        );

        $json = json_encode($entry);
        $decoded = json_decode($json, true);

        expect($decoded['step'])->toBe('ConfidenceScorer');
        expect($decoded['status'])->toBe('ok');
        expect($decoded['duration'])->toBe(0.05);
    });

    it('records a failed step without throwing', function () {
        $entry = new AuditEntry(
            step: 'ExtractionService',
            status: 'failed',
            duration: 0.0,
        );

        expect($entry->status)->toBe('failed');
        expect($entry->toArray()['status'])->toBe('failed');
    });
});

describe('AuditLog', function () {
    it('starts empty', function () {
        $log = new AuditLog();
        expect($log->entries())->toBe([]);
    });

    it('accumulates entries in order', function () {
        $log = new AuditLog();

        $log->recordDeterministic(step: 'FileIntake', status: 'ok', duration: 0.01, errorCount: 0);
        $log->recordLlm(step: 'ExtractionService', status: 'ok', duration: 1.2, modelId: 'claude-opus-4-8', inputTokens: 400, outputTokens: 200);
        $log->recordDeterministic(step: 'ValidationService', status: 'ok', duration: 0.05, errorCount: 2);

        $entries = $log->entries();

        expect(count($entries))->toBe(3);
        expect($entries[0]['step'])->toBe('FileIntake');
        expect($entries[1]['step'])->toBe('ExtractionService');
        expect($entries[2]['step'])->toBe('ValidationService');
    });

    it('LLM entry in list includes model id and token usage', function () {
        $log = new AuditLog();
        $log->recordLlm(step: 'ExtractionService', status: 'ok', duration: 0.9, modelId: 'claude-opus-4-8', inputTokens: 300, outputTokens: 150);

        $entries = $log->entries();

        expect($entries[0]['model_id'])->toBe('claude-opus-4-8');
        expect($entries[0]['input_tokens'])->toBe(300);
        expect($entries[0]['output_tokens'])->toBe(150);
    });

    it('deterministic entry in list includes error count', function () {
        $log = new AuditLog();
        $log->recordDeterministic(step: 'ValidationService', status: 'ok', duration: 0.03, errorCount: 5);

        $entries = $log->entries();

        expect($entries[0]['error_count'])->toBe(5);
    });

    it('entries() returns JSON-friendly ordered list', function () {
        $log = new AuditLog();
        $log->recordDeterministic(step: 'FileIntake', status: 'ok', duration: 0.01, errorCount: 0);
        $log->recordLlm(step: 'ExtractionService', status: 'ok', duration: 1.1, modelId: 'claude-opus-4-8', inputTokens: 100, outputTokens: 50);

        $json = json_encode($log->entries());
        $decoded = json_decode($json, true);

        expect(count($decoded))->toBe(2);
        expect($decoded[0]['step'])->toBe('FileIntake');
        expect($decoded[1]['step'])->toBe('ExtractionService');
    });

    it('records a failed step with status=failed and does not throw', function () {
        $log = new AuditLog();

        // Should not throw
        $log->recordLlm(step: 'ExtractionService', status: 'failed', duration: 0.0, modelId: 'claude-opus-4-8', inputTokens: 0, outputTokens: 0);

        $entries = $log->entries();
        expect($entries[0]['status'])->toBe('failed');
    });

    it('records a failed deterministic step without throwing', function () {
        $log = new AuditLog();

        $log->recordDeterministic(step: 'ValidationService', status: 'failed', duration: 0.0, errorCount: 0);

        $entries = $log->entries();
        expect($entries[0]['status'])->toBe('failed');
    });
});
