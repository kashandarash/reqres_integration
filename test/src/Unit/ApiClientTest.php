<?php

declare(strict_types=1);

namespace Drupal\Tests\reqres_integration\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\reqres_integration\Event\ApiUrlAlterEvent;
use Drupal\reqres_integration\Form\ApiSettingsForm;
use Drupal\reqres_integration\Service\ApiClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(ApiClient::class)]
#[CoversMethod(ApiClient::class, 'fetch')]
#[Group('reqres_integration')]
class ApiClientTest extends UnitTestCase {

  private Client&MockObject $httpClient;
  private LoggerInterface&MockObject $logger;
  private StateInterface&MockObject $state;
  private EventDispatcherInterface&MockObject $eventDispatcher;
  private ApiClient $apiClient;

  /** Raw fixture data decoded from mock_api.json. */
  private array $fixture;

  protected function setUp(): void {
    parent::setUp();

    $this->httpClient      = $this->createMock(Client::class);
    $this->logger          = $this->createMock(LoggerInterface::class);
    $this->state           = $this->createMock(StateInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->apiClient = new ApiClient(
      $this->httpClient,
      $this->logger,
      $this->state,
      $this->eventDispatcher,
    );

    $this->fixture = json_decode(
      file_get_contents(__DIR__ . '/../../data/mock_api.json'),
      TRUE,
    );
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /** Builds an HTTP response mock whose body returns $data as JSON. */
  private function mockResponse(array $data): ResponseInterface {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('__toString')->willReturn(json_encode($data));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($stream);

    return $response;
  }

  /** Stubs State::get() to return URL and API key. */
  private function stubState(
    string $url = 'https://reqres.in/api/users',
    string $key = 'test-key',
  ): void {
    $this->state->method('get')->willReturnMap([
      [ApiSettingsForm::STATE_API_URL, '', $url],
      [ApiSettingsForm::STATE_API_KEY, '', $key],
    ]);
  }

  /** Stubs the event dispatcher to pass the event through unchanged. */
  private function stubEventDispatcher(): void {
    $this->eventDispatcher
      ->method('dispatch')
      ->willReturnArgument(0);
  }

  // ---------------------------------------------------------------------------
  // Tests
  // ---------------------------------------------------------------------------

  /**
   *fetch() returns 'items' and 'total' populated from the mock fixture.
   */
  public function testFetchReturnsItemsAndTotal(): void {
    $this->stubState();
    $this->stubEventDispatcher();
    $this->httpClient->method('get')->willReturn($this->mockResponse($this->fixture));

    $result = $this->apiClient->fetch(0, 6);

    $this->assertArrayHasKey('items', $result);
    $this->assertArrayHasKey('total', $result);
    $this->assertCount(6, $result['items']);
    $this->assertSame(12, $result['total']);
  }

  /**
   *The user data in the returned items matches the fixture values exactly.
   */
  public function testFetchItemsMatchFixtureData(): void {
    $this->stubState();
    $this->stubEventDispatcher();
    $this->httpClient->method('get')->willReturn($this->mockResponse($this->fixture));

    $items = $this->apiClient->fetch(0, 6)['items'];

    $this->assertSame('george.bluth@reqres.in', $items[0]['email']);
    $this->assertSame('George', $items[0]['first_name']);
    $this->assertSame('Bluth', $items[0]['last_name']);

    $this->assertSame('tracey.ramos@reqres.in', $items[5]['email']);
    $this->assertSame('Tracey', $items[5]['first_name']);
    $this->assertSame('Ramos', $items[5]['last_name']);
  }

  /**
   *The x-api-key header is forwarded to the HTTP client.
   */
  public function testFetchSendsApiKeyHeader(): void {
    $this->stubState('https://reqres.in/api/users', 'secret-123');
    $this->stubEventDispatcher();

    $this->httpClient
      ->expects($this->once())
      ->method('get')
      ->with(
        $this->anything(),
        $this->callback(
          fn(array $opts) => ($opts['headers']['x-api-key'] ?? '') === 'secret-123',
        ),
      )
      ->willReturn($this->mockResponse($this->fixture));

    $this->apiClient->fetch(0, 6);
  }

  /**
   *The zero-based $page argument is converted to a 1-based page query param.
   */
  public function testFetchConvertsPageIndexToOneBased(): void {
    $this->stubState('https://reqres.in/api/users');
    $this->stubEventDispatcher();

    $this->httpClient
      ->expects($this->once())
      ->method('get')
      ->with(
        $this->logicalAnd(
          $this->stringContains('page=2'),
          $this->stringContains('per_page=6'),
        ),
        $this->anything(),
      )
      ->willReturn($this->mockResponse($this->fixture));

    // page=1 (zero-based) → page=2 in query string.
    $this->apiClient->fetch(1, 6);
  }

  /**
   *An ApiUrlAlterEvent is dispatched with the correct event name before the
   * HTTP request is sent.
   */
  public function testFetchDispatchesApiUrlAlterEvent(): void {
    $this->stubState();

    $this->eventDispatcher
      ->expects($this->once())
      ->method('dispatch')
      ->with(
        $this->isInstanceOf(ApiUrlAlterEvent::class),
        ApiUrlAlterEvent::EVENT_NAME,
      )
      ->willReturnArgument(0);

    $this->httpClient->method('get')->willReturn($this->mockResponse($this->fixture));

    $this->apiClient->fetch(0, 6);
  }

  /**
   *If a subscriber modifies the event params, the changed params appear in
   * the URL sent to the HTTP client.
   */
  public function testFetchRespectsEventParamOverride(): void {
    $this->stubState('https://reqres.in/api/users');

    $this->eventDispatcher
      ->method('dispatch')
      ->willReturnCallback(function (ApiUrlAlterEvent $event) {
        $params = $event->getParams();
        $params['filter'] = 'active';
        $event->setParams($params);
        return $event;
      });

    $this->httpClient
      ->expects($this->once())
      ->method('get')
      ->with($this->stringContains('filter=active'), $this->anything())
      ->willReturn($this->mockResponse($this->fixture));

    $this->apiClient->fetch(0, 6);
  }

  /**
   *When the HTTP client throws, the error is logged and the exception
   * is re-thrown to the caller.
   */
  public function testFetchLogsErrorAndRethrowsOnException(): void {
    $this->stubState();
    $this->stubEventDispatcher();

    $this->httpClient
      ->method('get')
      ->willThrowException(new \RuntimeException('timeout'));

    $this->logger
      ->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('API request failed'),
        $this->arrayHasKey('@message'),
      );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('timeout');

    $this->apiClient->fetch(0, 6);
  }

  /**
   *When the API response omits the 'data' key, items defaults to [].
   */
  public function testFetchDefaultsToEmptyItemsWhenDataKeyMissing(): void {
    $this->stubState();
    $this->stubEventDispatcher();
    $this->httpClient->method('get')->willReturn($this->mockResponse(['total' => 0]));

    $result = $this->apiClient->fetch(0, 6);

    $this->assertSame([], $result['items']);
    $this->assertSame(0, $result['total']);
  }

  /**
   *When the API response omits the 'total' key, total defaults to NULL.
   */
  public function testFetchDefaultsToNullTotalWhenTotalKeyMissing(): void {
    $this->stubState();
    $this->stubEventDispatcher();

    $fixtureWithoutTotal = $this->fixture;
    unset($fixtureWithoutTotal['total']);

    $this->httpClient->method('get')->willReturn($this->mockResponse($fixtureWithoutTotal));

    $result = $this->apiClient->fetch(0, 6);

    $this->assertNull($result['total']);
    $this->assertCount(6, $result['items']);
  }

}
