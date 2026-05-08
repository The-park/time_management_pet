<?php

use App\Models\User;
use App\Services\ActivityClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Each test gets its own isolated model directory under sys_get_temp_dir
    // so concurrent tests don't fight over the same files and the persisted
    // model from the production storage_path() doesn't pollute assertions.
    $this->tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'classifier_test_'.uniqid();
    @mkdir($this->tmpDir, 0775, true);
    $this->svc = new ActivityClassifierService($this->tmpDir);
});

afterEach(function () {
    // Best-effort cleanup. Tests still pass if this fails.
    foreach (glob($this->tmpDir.'/classifier/*') ?: [] as $f) @unlink($f);
    @rmdir($this->tmpDir.'/classifier');
    @rmdir($this->tmpDir);
});

test('classifier predicts obvious productive examples correctly', function () {
    $this->svc->train();

    expect($this->svc->predict('finished the sprint backlog'))->toBe(ActivityClassifierService::PRODUCTIVE);
    expect($this->svc->predict('studied for the certification exam'))->toBe(ActivityClassifierService::PRODUCTIVE);
});

test('classifier predicts obvious unproductive examples correctly', function () {
    $this->svc->train();

    expect($this->svc->predict('binge watched netflix all afternoon'))->toBe(ActivityClassifierService::UNPRODUCTIVE);
    expect($this->svc->predict('doomscrolling reels for hours'))->toBe(ActivityClassifierService::UNPRODUCTIVE);
});

test('classifier handles the wasted-day full-review pattern', function () {
    $this->svc->train();

    $line = 'wake up and eat breakfast and roaming in the end whole day got wasted';
    expect($this->svc->predict($line))->toBe(ActivityClassifierService::UNPRODUCTIVE);
});

test('classifier handles mixed-sentiment with productive outcome', function () {
    $this->svc->train();

    $line = 'played pubg for 20 mins as a break then coded for 4 hours';
    expect($this->svc->predict($line))->toBe(ActivityClassifierService::PRODUCTIVE);
});

test('classifier persists model to disk and predicts without retraining', function () {
    // First service instance: train + persist.
    $svc1 = new ActivityClassifierService($this->tmpDir);
    $svc1->train();

    // Second instance: should load from disk, not retrain.
    $svc2 = new ActivityClassifierService($this->tmpDir);
    expect($svc2->predict('shipped the feature to staging'))
        ->toBe(ActivityClassifierService::PRODUCTIVE);

    // Confirm the artefacts exist.
    expect(is_file($this->tmpDir.'/classifier/model.bin'))->toBeTrue();
    expect(is_file($this->tmpDir.'/classifier/vectorizer.bin'))->toBeTrue();
    expect(is_file($this->tmpDir.'/classifier/transformer.bin'))->toBeTrue();
});

test('feedback loop adds example, retrains, and changes future prediction', function () {
    $this->svc->train();

    // Pick a sample where the default corpus might lean one way…
    $sample = 'played a quick chess puzzle then a long debugging session';

    // …record the opposite label and retrain via recordFeedback.
    $this->svc->recordFeedback($sample, ActivityClassifierService::UNPRODUCTIVE);

    expect($this->svc->predict($sample))
        ->toBe(ActivityClassifierService::UNPRODUCTIVE);

    expect($this->svc->corpusStats()['feedback_count'])->toBe(1);
});

test('classify endpoint returns label for authenticated user', function () {
    $user = User::factory()->create();

    // Make sure the production-storage model exists so the request doesn't
    // try to retrain mid-test (which would be slow and noisy).
    (new ActivityClassifierService())->loadOrTrain();

    $this->actingAs($user)
        ->postJson('/classify', ['text' => 'studied for finals'])
        ->assertOk()
        ->assertJson(['ok' => true, 'label' => ActivityClassifierService::PRODUCTIVE]);
});

test('classify endpoint requires authentication', function () {
    // postJson sends Accept: application/json so unauth gets 401, not the
    // browser-redirect 302. Either is correct guarding behavior.
    $this->postJson('/classify', ['text' => 'whatever'])
        ->assertStatus(401);
});

test('feedback endpoint requires a valid label', function () {
    $user = User::factory()->create();
    (new ActivityClassifierService())->loadOrTrain();

    $this->actingAs($user)
        ->postJson('/classify/feedback', ['text' => 'foo', 'expected' => 'bogus'])
        ->assertStatus(422);
});
