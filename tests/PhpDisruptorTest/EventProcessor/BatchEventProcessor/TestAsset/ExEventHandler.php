<?php

namespace PhpDisruptorTest\EventProcessor\BatchEventProcessor\TestAsset;

use PhpDisruptor\EventHandlerInterface;
use PhpDisruptor\Exception;
use PhpDisruptor\LifecycleAwareInterface;
use ConcurrentPhpUtils\UuidNoOpStackable;

class ExEventHandler extends EventHandler
{
    /**
     * Called when a publisher has published an event to the RingBuffer
     *
     * @param object $event published to the RingBuffer
     * @param int $sequence of the event being processed
     * @param bool $endOfBatch flag to indicate if this is the last event in a batch from the RingBuffer
     * @return void
     * @throws \Exception if the EventHandler would like the exception handled further up the chain.
     */
    public function onEvent($event, $sequence, $endOfBatch)
    {
        $this->latch->countDown();
        throw new \Exception('Throws exception');
    }
}
