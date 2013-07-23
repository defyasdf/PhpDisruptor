<?php

namespace PhpDisruptor;

interface EventTranslatorInterface
{
    /**
     * Translate a data representation into fields set in given event
     *
     * @param EventInterface $event into which the data should be translated.
     * @param int $sequence that is assigned to event.
     * @return void
     */
    public function translateTo(EventTranslatorInterface $event, $sequence);
}