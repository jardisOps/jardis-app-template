<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Answers 200 and keeps the request it was called with. */
final class RecordingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $seen = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->seen = $request;

        return new Response(200);
    }
}
