<?php

namespace SmartCustomer\Reviews\Test\Unit\Cron;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Cron\ProcessOutbox;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\SyncOrders;
use SmartCustomer\Reviews\Model\Outbox;

class ProcessOutboxTest extends TestCase
{
    #[DataProvider('failedAttempts')]
    public function testFailedDeliveryIsRetriedUntilTheAttemptLimit(int $attempts, bool $discarded): void
    {
        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturn('1');
        $data->method('encrypt')->willReturn('sj#secret');

        $sync = $this->createStub(SyncOrders::class);
        $sync->method('syncOrders')->willReturn(false);

        $outbox = $this->createMock(Outbox::class);
        $outbox->method('getReadyIds')->willReturn([10]);
        $outbox->method('claim')->willReturn(['entity_id' => 10, 'order_id' => 5, 'store_id' => 1, 'attempts' => $attempts]);
        $outbox->expects($discarded ? $this->never() : $this->once())->method('markRetry');
        $outbox->expects($discarded ? $this->once() : $this->never())->method('markDiscarded');
        $outbox->expects($this->once())->method('purgeProcessed');

        (new ProcessOutbox($data, $sync, $outbox, $this->createStub(LoggerInterface::class)))->execute();
    }

    public static function failedAttempts(): array
    {
        return [
            'first failure' => [1, false],
            'last retry' => [Outbox::MAX_ATTEMPTS - 1, false],
            'limit reached' => [Outbox::MAX_ATTEMPTS, true],
        ];
    }
}
