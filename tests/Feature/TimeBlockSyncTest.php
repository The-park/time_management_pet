<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('time-blocks/sync rejects unknown category values', function () {
    $user = User::factory()->create();
    $payload = [
        'blocks' => [[
            'id'           => 'test_unknown_cat_1',
            'date'         => '2026-05-09',
            'start'        => '09:00',
            'end'          => '10:00',
            'durationMs'   => 3600000,
            'label'        => 'studied for an hour',
            'category'     => 'mystery',           // not in {productive, wasted, neutral}
            'auto_filled'  => false,
        ]],
    ];

    $this->actingAs($user)
        ->postJson('/time-blocks/sync', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['blocks.0.category']);
});

test('time-blocks/sync accepts the three valid categories', function () {
    $user = User::factory()->create();

    foreach (['productive', 'wasted', 'neutral'] as $i => $cat) {
        $payload = [
            'blocks' => [[
                'id'           => "test_valid_{$cat}",
                'date'         => '2026-05-09',
                'start'        => sprintf('%02d:00', 9 + $i),
                'end'          => sprintf('%02d:00', 10 + $i),
                'durationMs'   => 3600000,
                'label'        => "block tagged {$cat}",
                'category'     => $cat,
                'auto_filled'  => false,
            ]],
        ];

        $this->actingAs($user)
            ->postJson('/time-blocks/sync', $payload)
            ->assertOk();
    }
});

test('time-blocks/sync accepts null category (unset)', function () {
    $user = User::factory()->create();
    $payload = [
        'blocks' => [[
            'id'           => 'test_null_cat',
            'date'         => '2026-05-09',
            'start'        => '11:00',
            'end'          => '12:00',
            'durationMs'   => 3600000,
            'label'        => 'no category set',
            'category'     => null,
            'auto_filled'  => false,
        ]],
    ];

    $this->actingAs($user)
        ->postJson('/time-blocks/sync', $payload)
        ->assertOk();
});
