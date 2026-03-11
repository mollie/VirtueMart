<?php

namespace Mollie\Payment\WebHook\Controller;

use Mollie\BusinessLogic\WebHook\WebHookTransformer;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Infrastructure\DTO\Response;

class WebHookController
{
    private WebHookTransformer $transformer;

    public function __construct()
    {
        /** @var WebHookTransformer $transformer */
        $transformer = ServiceRegister::getService(WebHookTransformer::class);
        $this->transformer = $transformer;
    }

    /**
     * Handle incoming webhook from Mollie
     *
     * @param array $webhookData
     *
     * @return Response
     */
    public function handle(array $webhookData): Response
    {
        try {
            $rawRequest = http_build_query($webhookData);
            $this->transformer->handle($rawRequest);

            return Response::success([]);
        } catch (HttpCommunicationException $e) {
            Logger::logError($e->getMessage(), 'Webhook Controller');

            return Response::error($e->getMessage());
        }
    }
}
