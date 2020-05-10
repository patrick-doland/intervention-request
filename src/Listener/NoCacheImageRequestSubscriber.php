<?php
declare(strict_types=1);

namespace AM\InterventionRequest\Listener;

use AM\InterventionRequest\Event\RequestEvent;
use AM\InterventionRequest\Processor\ChainProcessor;
use AM\InterventionRequest\WebpFile;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

class NoCacheImageRequestSubscriber implements EventSubscriberInterface
{
    /**
     * @var ChainProcessor
     */
    private $processor;

    /**
     * NoCacheImageRequestSubscriber constructor.
     *
     * @param ChainProcessor $processor
     */
    public function __construct(ChainProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            RequestEvent::class => ['onRequest', 0]
        ];
    }

    /**
     * @param RequestEvent $requestEvent
     */
    public function onRequest(RequestEvent $requestEvent)
    {
        if (false === $requestEvent->getInterventionRequest()->getConfiguration()->hasCaching()) {
            $request = $requestEvent->getRequest();
            $nativePath = $requestEvent->getInterventionRequest()->getConfiguration()->getImagesPath() .
                '/' . $request->get('image');
            $nativeImage = new WebpFile($nativePath);
            $image = $this->processor->process($nativeImage, $request);

            if ($nativeImage instanceof WebpFile) {
                $response = new Response(
                    (string) $image->encode('webp', $requestEvent->getQuality()),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => 'image/webp',
                        'Content-Disposition' => 'filename="' . $nativeImage->getRequestedFile()->getFilename() . '"',
                        'X-IR-Cached' => 0,
                    ]
                );
            } else {
                $response = new Response(
                    (string) $image->encode(null, $requestEvent->getQuality()),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => $image->mime(),
                        'Content-Disposition' => 'filename="' . $nativeImage->getFilename() . '"',
                        'X-IR-Cached' => 0,
                    ]
                );
            }
            $response->setLastModified(new \DateTime('now'));
            $requestEvent->setResponse($response);
        }
    }
}