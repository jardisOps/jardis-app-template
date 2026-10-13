<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use JardisAdapter\DbConnection\Factory\ConnectionFactory;
use JardisCore\App\Middleware\RequireSession;
use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Contract\Auth\RequestAttribute;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The session recipe from README ("Sessions") against the installed packages:
 * token table from the auth package's own DDL, DbTokenStore on a
 * DbConnectionInterface, TokenAuthenticator behind RequireSession. SQLite
 * in memory stands in for the project database — the DDL differs per dialect
 * only in the file name.
 */
final class SessionRecipeTest extends TestCase
{
    private RequireSession $middleware;
    private string $accessToken;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec((string) file_get_contents(
            dirname(__DIR__, 3) . '/vendor/jardissupport/auth/resources/sql/sqlite.sql'
        ));

        // The kernel hands over a pool or a plain PDO; DbTokenStore wants a DbConnectionInterface.
        $connection = (new ConnectionFactory())->fromPdo($pdo);
        $store = new DbTokenStore($connection);

        $created = (new SessionManager($store))->create(Subject::from('42'), ['tenant' => 'acme']);
        $this->accessToken = $created->accessToken;

        $psr17 = new Psr17Factory();
        $this->middleware = new RequireSession(
            new TokenAuthenticator($store),
            Credential::token(...),
            $psr17,
            $psr17,
            ['/auth/login'],
        );
    }

    public function testValidTokenCarriesSubjectAndTenantAsRequestAttributes(): void
    {
        $handler = new RecordingHandler();
        $request = (new ServerRequest('GET', '/orders'))
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken);

        $response = $this->middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->seen);
        self::assertSame('user:42', $handler->seen->getAttribute(RequestAttribute::SUBJECT));
        self::assertSame('acme', $handler->seen->getAttribute(RequestAttribute::TENANT));
    }

    public function testMissingTokenIsAnswered401AndNeverReachesTheHandler(): void
    {
        $handler = new RecordingHandler();

        $response = $this->middleware->process(new ServerRequest('GET', '/orders'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertNull($handler->seen);
    }

    public function testAnonymousPathPassesWithoutSession(): void
    {
        $handler = new RecordingHandler();

        $response = $this->middleware->process(new ServerRequest('POST', '/auth/login'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($handler->seen?->getAttribute(RequestAttribute::SUBJECT));
    }
}
