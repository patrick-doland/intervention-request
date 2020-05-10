<?php
declare(strict_types=1);

namespace AM\InterventionRequest\Listener;

use AM\InterventionRequest\Event\RequestEvent;
use AM\InterventionRequest\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class QualitySubscriber implements EventSubscriberInterface
{
    private $quality;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            RequestEvent::class => ['onRequest', 100],
            ResponseEvent::class => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $response->headers->set('X-IR-Quality', $this->quality);
        $event->setResponse($response);
    }

    /**
     * @param RequestEvent $requestEvent
     */
    public function onRequest(RequestEvent $requestEvent)
    {
        $this->quality = (int) $requestEvent->getRequest()->get(
            'quality',
            $requestEvent->getInterventionRequest()->getConfiguration()->getDefaultQuality()
        );

        if ($this->quality <= 100 && $this->quality > 0) {
            $requestEvent->setQuality($this->quality);
        } else {
            $requestEvent->setQuality(
                $requestEvent->getInterventionRequest()->getConfiguration()->getDefaultQuality()
            );
        }
    }
}