<?php

namespace PhpDisruptor\Dsl;

use ArrayIterator;
use IteratorAggregate;
use PhpDisruptor\EventClassCapableInterface;
use PhpDisruptor\EventFactoryInterface;
use PhpDisruptor\EventHandlerInterface;
use PhpDisruptor\EventProcessor\AbstractEventProcessor;
use PhpDisruptor\Exception;
use PhpDisruptor\Pthreads\ObjectStorage;
use PhpDisruptor\Pthreads\StackableArray;
use PhpDisruptor\Sequence;
use PhpDisruptor\SequenceBarrierInterface;
use PhpDisruptor\WorkerPool;
use SplObjectStorage;
use Stackable;

/**
* Provides a repository mechanism to associate EventHandlers with EventProcessors
*/
class ConsumerRepository extends Stackable implements EventClassCapableInterface
{
    /**
     * @var ObjectStorage
     */
    public $eventProcessorInfoByEventHandler;

    /**
     * @var ObjectStorage
     */
    public $eventProcessorInfoBySequence;

    /**
     * @var ConsumerInfoInterface[]
     */
    public $consumerInfos;

    /**
     * @var string
     */
    public $eventClass;

    /**
     * Constructor
     *
     * @param EventFactoryInterface $eventFactory
     */
    public function __construct(EventFactoryInterface $eventFactory)
    {
        $this->eventClass = $eventFactory->getEventClass();
        $this->eventProcessorInfoByEventHandler = new ObjectStorage();
        $this->eventProcessorInfoBySequence = new ObjectStorage();
        $this->consumerInfos = new StackableArray();
    }

    public function run()
    {
    }

    /**
     * @inheritdoc
     */
    public function getEventClass()
    {
        return $this->eventClass;
    }

    /**
     * @return ConsumerInfoInterface[]
     */
    public function getConsumerInfos()
    {
        return $this->consumerInfos;
    }

    /**
     * Add event processor
     *
     * @param AbstractEventProcessor $eventProcessor
     * @param EventHandlerInterface $handler
     * @param SequenceBarrierInterface $barrier
     * @throws Exception\InvalidArgumentException
     */
    public function addEventProcessor(
        AbstractEventProcessor $eventProcessor,
        EventHandlerInterface $handler = null,
        SequenceBarrierInterface $barrier = null
    ) {
        if ((null === $handler && null !== $barrier)
            || (null !== $handler && null === $barrier)
        ) {
            throw new Exception\InvalidArgumentException(
                'Even $handler and $barrier are null or both of them not'
            );
        }

        $consumerInfo = new EventProcessorInfo($eventProcessor, $handler, $barrier);
        $this->eventProcessorInfoBySequence->attach($eventProcessor->getSequence(), $consumerInfo);
        $this->consumerInfos[] = $consumerInfo;

        if (null !== $handler) {
            if ($handler->getEventClass() != $this->getEventClass()) {
                throw new Exception\InvalidArgumentException(
                    'Given event handler does not match current event class, current is "'
                    . $this->getEventClass() . '", given "' . $handler->getEventClass() . '"'
                );
            }
            $this->eventProcessorInfoByEventHandler->attach($handler, $consumerInfo);
        }
    }

    /**
     * Add worker pool
     *
     * @param WorkerPool $workerPool
     * @param SequenceBarrierInterface $sequenceBarrier
     */
    public function addWorkerPool(WorkerPool $workerPool, SequenceBarrierInterface $sequenceBarrier)
    {
        $workerPoolInfo = new WorkerPoolInfo($workerPool, $sequenceBarrier);
        $this->consumerInfos[] = $workerPoolInfo;
        foreach ($workerPool->getWorkerSequences() as $sequence) {
            $this->eventProcessorInfoBySequence->attach($sequence, $workerPoolInfo);
        }
    }

    /**
     * Get last sequence in chain
     *
     * @param bool $includeStopped
     * @return Sequence[]
     */
    public function getLastSequenceInChain($includeStopped)
    {
        $includeStopped = (bool) $includeStopped;
        $lastSequences = new StackableArray();
        foreach ($this->consumerInfos as $consumerInfo) {
            if (($includeStopped || $consumerInfo->isRunning()) && $consumerInfo->isEndOfChain()) {
                $sequences = $consumerInfo->getSequences();
                foreach ($sequences as $sequence) {
                    $lastSequences[] = $sequence;
                }
            }
        }
        return $lastSequences;
    }

    /**
     * Get event processor for event handler
     *
     * @param EventHandlerInterface $handler
     * @return AbstractEventProcessor
     * @throws Exception\InvalidArgumentException
     */
    public function getEventProcessorFor(EventHandlerInterface $handler)
    {
        $eventProcessorInfo = $this->_getEventProcessorInfo($handler);
        if (null === $eventProcessorInfo) {
            throw new Exception\InvalidArgumentException(
                'The given event handler is not processing events'
            );
        }
        return $eventProcessorInfo->getEventProcessor();
    }

    /**
     * Get sequence for event handler
     *
     * @param EventHandlerInterface $handler
     * @return Sequence
     */
    public function getSequenceFor(EventHandlerInterface $handler)
    {
        return $this->getEventProcessorFor($handler)->getSequence();
    }

    /**
     * Un-mark event processors as end of chain
     *
     * @param Sequence[] $barrierEventProcessors
     * @throws Exception\InvalidArgumentException
     */
    public function unMarkEventProcessorsAsEndOfChain(StackableArray $barrierEventProcessors)
    {
        foreach ($barrierEventProcessors as $barrierEventProcessor) {
            if (!$barrierEventProcessor instanceof Sequence) {
                throw new Exception\InvalidArgumentException(
                    '$barrierEventProcessors must be an array of Sequence'
                );
            }
            $this->_getEventProcessorInfoBySequence($barrierEventProcessor)->markAsUsedInBarrier();
        }
    }

    /**
     * Get barrier for event handler
     *
     * @param EventHandlerInterface $handler
     * @return SequenceBarrierInterface|null
     */
    public function getBarrierFor(EventHandlerInterface $handler)
    {
        $consumerInfo = $this->_getEventProcessorInfo($handler);
        if (null === $consumerInfo) {
            return null;
        }
        return $consumerInfo->getBarrier();
    }

    /**
     * Get event processor info by event handler
     *
     * @param EventHandlerInterface $handler
     * @return EventProcessorInfo
     */
    public function _getEventProcessorInfo(EventHandlerInterface $handler) // public for pthreads reasons
    {
        foreach ($this->eventProcessorInfoByEventHandler->data as $key => $value) {
            if ($handler->equals($value)) {
                return $this->eventProcessorInfoByEventHandler->info[$key];
            }
        }
    }

    /**
     * Get event processor info by sequence
     *
     * @param Sequence $barrierEventProcessor
     * @return EventProcessorInfo
     */
    public function _getEventProcessorInfoBySequence(Sequence $barrierEventProcessor) // public for pthreads reasons
    {
        foreach ($this->eventProcessorInfoBySequence->data as $key => $value) {
            if ($barrierEventProcessor->equals($value)) {
                return $this->eventProcessorInfoBySequence->info[$key];
            }
        }
    }
}
