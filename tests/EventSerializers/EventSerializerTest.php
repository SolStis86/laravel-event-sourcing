<?php

namespace Spatie\EventSourcing\Tests\EventSerializers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Spatie\EventSourcing\EventSerializers\EventSerializer;
use Spatie\EventSourcing\Tests\TestCase;
use Spatie\EventSourcing\Tests\TestClasses\Events\EventWithArray;
use Spatie\EventSourcing\Tests\TestClasses\Events\EventWithCarbon;
use Spatie\EventSourcing\Tests\TestClasses\Events\EventWithDatetime;
use Spatie\EventSourcing\Tests\TestClasses\Events\EventWithDocblock;
use Spatie\EventSourcing\Tests\TestClasses\Events\EventWithoutSerializedModels;
use Spatie\EventSourcing\Tests\TestClasses\Events\MoneyAddedEvent;
use Spatie\EventSourcing\Tests\TestClasses\EventSerializer\UpgradeSerializer;
use Spatie\EventSourcing\Tests\TestClasses\Models\Account;

class EventSerializerTest extends TestCase
{
    protected EventSerializer $eventSerializer;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventSerializer = app(EventSerializer::class);
    }

    /** @test */
    public function it_can_serialize_a_plain_event()
    {
        $event = new EventWithoutSerializedModels('test');

        $json = $this->eventSerializer->serialize($event);

        $array = json_decode($json, true);

        $this->assertEquals([
            'value' => 'test',
        ], $array);
    }

    /** @test */
    public function it_can_serialize_an_event_containing_a_model()
    {
        $account = Account::create(['name' => 'test']);
        $event = new MoneyAddedEvent($account, 1234);

        $json = $this->eventSerializer->serialize($event);
        $event = $this->eventSerializer->deserialize(get_class($event), $json, 1);

        $this->assertEquals($account->id, $event->account->id);
        $this->assertEquals('test', $event->account->name);
        $this->assertEquals(1234, $event->amount);
    }

    /** @test */
    public function it_serializes_an_event_to_json()
    {
        $account = Account::create();

        $event = new MoneyAddedEvent($account, 1234);

        $json = $this->eventSerializer->serialize($event);

        $array = json_decode($json, true);

        $this->assertEquals(get_class($account), $array['account']['class'] ?? null);
        $this->assertEquals(1, $array['account']['id'] ?? null);
        $this->assertEquals(1234, $array['amount'] ?? null);
    }

    /** @test */
    public function it_can_deserialize_an_event_with_datetime()
    {
        $event = new EventWithDatetime(new DateTimeImmutable('now'));

        $json = $this->eventSerializer->serialize($event);

        /**
         * @var EventWithDatetime
         */
        $normalizedEvent = $this->eventSerializer->deserialize(get_class($event), $json, 1);

        $this->assertInstanceOf(DateTimeImmutable::class, $normalizedEvent->value);
    }

    /** @test */
    public function it_can_deserialize_an_event_with_carbon()
    {
        $event = new EventWithCarbon(Carbon::now());

        $json = $this->eventSerializer->serialize($event);

        $normalizedEvent = $this->eventSerializer->deserialize(get_class($event), $json, 1);

        $this->assertInstanceOf(CarbonInterface::class, $normalizedEvent->value);
    }

    /** @test */
    public function it_can_deserialize_an_event_with_an_array()
    {
        $event = new EventWithArray([Carbon::now(), Carbon::now()]);

        $json = $this->eventSerializer->serialize($event);

        $normalizedEvent = $this->eventSerializer->deserialize(get_class($event), $json, 1);

        $this->assertInstanceOf(CarbonInterface::class, $normalizedEvent->values[0]);
    }

    /** @test */
    public function it_can_deserialize_an_event_with_a_docblock()
    {
        $event = new EventWithDocblock(Carbon::now());

        $json = $this->eventSerializer->serialize($event);

        $normalizedEvent = $this->eventSerializer->deserialize(get_class($event), $json, 1);

        $this->assertInstanceOf(CarbonInterface::class, $normalizedEvent->value);
    }

    /** @test */
    public function it_can_upgrade_an_event_version()
    {
        $event = new EventWithDatetime(new DateTimeImmutable('2019-08-07T00:00:00Z'));
        $eventSerializer = app(UpgradeSerializer::class);

        $json = $eventSerializer->serialize($event);

        /**
         * @var EventWithDatetime
         */
        $normalizedEvent = $eventSerializer->deserialize(get_class($event), $json, 1, '{ "version": 1 }');

        $this->assertInstanceOf(DateTimeImmutable::class, $normalizedEvent->value);
        $this->assertEquals('UTC', $normalizedEvent->value->getTimezone()->getName());
    }
}
