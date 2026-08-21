<?php

use App\Enums\WorkerStatus;
use App\Models\User;
use App\Models\Worker;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates updates lists and soft deletes workers with generated codes', function () {
    $this->post(route('workers.store'), ['name' => 'Ahmed Ali', 'phone' => '01000000000', 'specialization' => 'Cutting', 'status' => 'active'])->assertRedirect();
    $worker = Worker::firstOrFail();
    expect($worker->code)->toBe('WRK-000001')->and($worker->status)->toBe(WorkerStatus::ACTIVE);

    $this->put(route('workers.update', $worker), ['name' => 'Ahmed Hassan', 'phone' => '01000000000', 'specialization' => 'Cutting', 'status' => 'inactive'])->assertRedirect();
    expect($worker->fresh()->name)->toBe('Ahmed Hassan')->and($worker->fresh()->status)->toBe(WorkerStatus::INACTIVE);
    $this->get(route('workers.index'))->assertOk()->assertSee('Ahmed Hassan');
    $this->delete(route('workers.destroy', $worker))->assertRedirect(route('workers.index'));
    expect(Worker::withTrashed()->find($worker->id)->trashed())->toBeTrue();
});
