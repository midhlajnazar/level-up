<?php

use Carbon\Carbon;
use LevelUp\Experience\Events\PointsDecreasedEvent;
use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

test(description: 'giving points to a User without an experience Model, creates a new experience Model', closure: function (): void {
    // an Experience Model doesn't exist for the User, so this should create one.
    $this->user->addPoints(amount: 10);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 10)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 10,
    ]);
});

test(description: 'giving points to a User with an experience Model, updates the experience Model', closure: function (): void {
    Event::fake();

    // this creates the experience Model
    $this->user->addPoints(amount: 10);
    // so now it will increment the points, instead of creating a new experience Model
    $this->user->addPoints(amount: 10);

    Event::assertDispatched(event: PointsIncreasedEvent::class);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

it(description: 'can deduct points from a User', closure: function (): void {
    Event::fake();

    $this->user->addPoints(amount: 10);

    $this->user->deductPoints(amount: 5);

    Event::assertDispatched(event: PointsDecreasedEvent::class);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 5)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

it(description: 'can set points to a User', closure: function (): void {
    $this->user->addPoints(amount: 10);

    $this->user->setPoints(amount: 5);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 5)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

test(description: 'it throws an error if points cannot be set', closure: function () {
    $this->user->setPoints(amount: 5);
})->throws(exception: \Exception::class, exceptionMessage: 'User has no experience record.');

it(description: "can retrieve the Users' points", closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect(value: $this->user)
        ->getPoints()
        ->toBe(expected: 10);
});

test(description: 'when using a multiplier, times the points by it', closure: function (): void {
    $this->user->addPoints(amount: 10, multiplier: 2);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

test(description: 'points can be multiplied', closure: function () {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.path', value: 'tests/Fixtures/Multipliers');
    config()->set(key: 'level-up.multiplier.namespace', value: 'LevelUp\\Experience\\Tests\\Fixtures\\Multipliers\\');

    Carbon::setTestNow(Carbon::create(month: 4));

    $this->user->addPoints(amount: 10);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 50)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 50,
    ]);
});

test('a User can see how many more points are needed until they can level up', function () {
    Level::add(level: 1, pointsToNextLevel: 20);
    Level::add(level: 2, pointsToNextLevel: 40);

    $this->user->addPoints(amount: 10);

    expect($this->user)
        ->nextLevelAt()
        ->toBe(30);
});

test(description: 'when a User hits the next level threshold, their level will increase', closure: function () {
    Level::add(
        ['level' => 1, 'next_level_experience' => NULL],
        ['level' => 2, 'next_level_experience' => 100],
        ['level' => 3, 'next_level_experience' => 250],
    );

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 149);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 150)
        ->and($this->user->getLevel())->toBe(expected: 2);
});