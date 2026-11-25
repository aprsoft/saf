<?php

declare(strict_types=1);

namespace App\Services\Dte\Post;

use App\Models\Document;
use App\Services\Dte\Post\Classes\DteApiFactory;
use App\Services\Dte\Post\Classes\DteApiResponse;
use App\Services\Dte\Post\Classes\DteApiSend;
use App\Services\Dte\Post\Classes\DteResponse;

class DtePostManager
{
    public function send(Document $document): DteResponse
    {
        $payload = DteApiFactory::resolve($document)->mapJsonDte();
        $response = DteApiSend::send($payload, $document->id);

        return DteApiResponse::manager($response, $document);
    }
}
