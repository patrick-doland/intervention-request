<?php
/**
 * Copyright © 2020, Ambroise Maupate
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @file InterventionRequest.php
 * @author Ambroise Maupate
 */
namespace AM\InterventionRequest;

use AM\InterventionRequest\Cache\FileCache;
use AM\InterventionRequest\Cache\PassThroughFileCache;
use AM\InterventionRequest\Event\RequestEvent;
use AM\InterventionRequest\Event\ResponseEvent;
use AM\InterventionRequest\Listener\JpegFileListener;
use AM\InterventionRequest\Listener\NoCacheImageRequestSubscriber;
use AM\InterventionRequest\Listener\PngFileListener;
use AM\InterventionRequest\Listener\QualitySubscriber;
use AM\InterventionRequest\Listener\StripExifListener;
use AM\InterventionRequest\Processor as Processor;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class InterventionRequest
 * @package AM\InterventionRequest
 */
class InterventionRequest
{
    /**
     * @var Response|null
     */
    protected $response;
    /**
     * @var null|LoggerInterface
     */
    protected $logger;
    /**
     * @var Configuration
     */
    protected $configuration;
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * Create a new InterventionRequest object.
     *
     * @param Configuration        $configuration
     * @param LoggerInterface|null $logger
     * @param array|null           $processors
     */
    public function __construct(
        Configuration $configuration,
        LoggerInterface $logger = null,
        ?array $processors = null
    ) {
        $this->dispatcher = new EventDispatcher();
        $this->logger = $logger;
        $this->configuration = $configuration;
        $chainProcessor = $this->getChainProcessor($processors);

        if (null !== $this->configuration->getJpegoptimPath()) {
            $this->addSubscriber(new JpegFileListener($this->configuration->getJpegoptimPath()));
        }
        if (null !== $this->configuration->getPngquantPath()) {
            $this->addSubscriber(new PngFileListener($this->configuration->getPngquantPath()));
        }
        $this->addSubscriber(new StripExifListener());
        $this->addSubscriber(new QualitySubscriber());
        $this->addSubscriber(new FileCache(
            $chainProcessor,
            $this->configuration->getCachePath(),
            $this->logger,
            $this->configuration->getTtl(),
            $this->configuration->getGcProbability(),
            $this->configuration->getUseFileChecksum()
        ));
        $this->addSubscriber(new PassThroughFileCache(
            $chainProcessor,
            $this->configuration->getCachePath(),
            $this->logger,
            $this->configuration->getTtl(),
            $this->configuration->getGcProbability(),
            $this->configuration->getUseFileChecksum()
        ));
        $this->addSubscriber(new NoCacheImageRequestSubscriber(
            $chainProcessor
        ));

        $this->defineTimezone();
    }

    /**
     * @return void
     */
    private function defineTimezone()
    {
        /*
         * Define a request wide timezone
         */
        date_default_timezone_set($this->configuration->getTimezone());
    }

    /**
     * @param EventSubscriberInterface $subscriber
     * @return void
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * @param array|null $processors
     *
     * @return Processor\ChainProcessor
     */
    protected function getChainProcessor(?array $processors = null): Processor\ChainProcessor
    {
        return new Processor\ChainProcessor(
            $this->configuration,
            $this->dispatcher,
            $processors ?? [
                new Processor\RotateProcessor(),
                new Processor\FlipProcessor(),
                new Processor\CropResizedProcessor(),
                new Processor\FitProcessor(),
                new Processor\CropProcessor(),
                new Processor\WidenProcessor(),
                new Processor\HeightenProcessor(),
                new Processor\LimitColorsProcessor(),
                new Processor\GreyscaleProcessor(),
                new Processor\ContrastProcessor(),
                new Processor\BlurProcessor(),
                new Processor\SharpenProcessor(),
                new Processor\ProgressiveProcessor(),
            ]
        );
    }

    /**
     * Handle request to convert it to a Response object.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function handleRequest(Request $request)
    {
        try {
            if (!$request->query->has('image')) {
                throw new \InvalidArgumentException("No valid image path found in URI");
            }

            $event = new RequestEvent($request, $this);
            $this->dispatcher->dispatch($event);
            if (null === $this->response = $event->getResponse()) {
                $this->response = $this->getBadRequestResponse('No listener was configured for current request');
            }
        } catch (FileNotFoundException $e) {
            $this->response = $this->getNotFoundResponse($e->getMessage());
        } catch (\RuntimeException $e) {
            $this->response = $this->getBadRequestResponse($e->getMessage());
        }
    }

    /**
     * @param string $message
     * @return Response
     */
    protected function getNotFoundResponse($message = "")
    {
        $body = '<h1>404 Error: File not found</h1>';
        if ($message != '') {
            $body .= '<p>' . $message . '</p>';
        }
        $body = '<!DOCTYPE html><html lang="en"><body>' . $body . '</body></html>';

        return new Response(
            $body,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * @param string $message
     * @return Response
     */
    protected function getBadRequestResponse($message = "")
    {
        $body = '<h1>400 Error: Bad Request</h1>';
        if ($message != '') {
            $body .= '<p>' . $message . '</p>';
        }
        $body = '<!DOCTYPE html><html lang="en"><body>' . $body . '</body></html>';

        return new Response(
            $body,
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function getResponse(Request $request)
    {
        if (null !== $this->response) {
            $this->response->setPublic();
            $this->response->setMaxAge($this->configuration->getResponseTtl());
            $this->response->setSharedMaxAge($this->configuration->getResponseTtl());
            $this->response->setCharset('UTF-8');

            $responseEvent = new ResponseEvent($this->response);
            $this->dispatcher->dispatch($responseEvent);
            $this->response = $responseEvent->getResponse();
            $this->response->prepare($request);

            return $this->response;
        } else {
            throw new \RuntimeException("Request had not been handled. Use handle() method before getResponse()", 1);
        }
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }
}
