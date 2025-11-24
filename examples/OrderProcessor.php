<?php

declare(strict_types=1);

namespace Example\App;

use PHPUnit\Framework\Attributes\Test;

/**
 * Example demonstrating advanced PHPUnit mocking features in inline tests.
 */
final class OrderProcessor
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private EmailService $emailService
    ) {
    }

    public function processOrder(Order $order): bool
    {
        if (!$this->validateOrder($order)) {
            return false;
        }

        $paymentResult = $this->paymentGateway->charge($order->getAmount());

        if (!$paymentResult) {
            return false;
        }

        $this->emailService->sendConfirmation($order->getCustomerEmail());

        return true;
    }

    private function validateOrder(Order $order): bool
    {
        return $order->getAmount() > 0 && !empty($order->getCustomerEmail());
    }

    // ==================== Inline Tests ====================

    #[Test]
    private function testProcessOrderWithSuccessfulPayment(): void
    {
        // Create mocks for dependencies
        $paymentGateway = $this->createMock(PaymentGateway::class);
        $emailService = $this->createMock(EmailService::class);

        // Set up expectations
        $paymentGateway->expects($this->once())
            ->method('charge')
            ->with(100.00)
            ->willReturn(true);

        $emailService->expects($this->once())
            ->method('sendConfirmation')
            ->with('customer@example.com');

        // Create processor with mocked dependencies
        // Note: We need to use reflection to set private properties
        $processor = new OrderProcessor($paymentGateway, $emailService);

        // Create order
        $order = new Order(100.00, 'customer@example.com');

        // Test
        $result = $processor->processOrder($order);

        $this->assertTrue($result);
    }

    #[Test]
    private function testProcessOrderWithFailedPayment(): void
    {
        $paymentGateway = $this->createMock(PaymentGateway::class);
        $emailService = $this->createMock(EmailService::class);

        $paymentGateway->expects($this->once())
            ->method('charge')
            ->willReturn(false);

        // Email should not be sent if payment fails
        $emailService->expects($this->never())
            ->method('sendConfirmation');

        $processor = new OrderProcessor($paymentGateway, $emailService);
        $order = new Order(100.00, 'customer@example.com');

        $result = $processor->processOrder($order);

        $this->assertFalse($result);
    }

    #[Test]
    private function testValidateOrderRejectsInvalidOrders(): void
    {
        // Test private method directly
        $invalidOrder1 = new Order(0, 'test@example.com');
        $this->assertFalse($this->validateOrder($invalidOrder1));

        $invalidOrder2 = new Order(100, '');
        $this->assertFalse($this->validateOrder($invalidOrder2));
    }

    #[Test]
    private function testValidateOrderAcceptsValidOrders(): void
    {
        $validOrder = new Order(100.00, 'customer@example.com');
        $this->assertTrue($this->validateOrder($validOrder));
    }

    #[Test]
    private function testCanUseCreateStub(): void
    {
        // Stubs are simpler - no expectations, just return values
        $paymentStub = $this->createStub(PaymentGateway::class);
        $paymentStub->method('charge')->willReturn(true);

        $emailStub = $this->createStub(EmailService::class);

        $processor = new OrderProcessor($paymentStub, $emailStub);
        $order = new Order(50.00, 'test@example.com');

        $result = $processor->processOrder($order);

        $this->assertTrue($result);
    }

    #[Test]
    private function testMockWithConsecutiveCalls(): void
    {
        $paymentGateway = $this->createMock(PaymentGateway::class);
        $paymentGateway->expects($this->exactly(2))
            ->method('charge')
            ->willReturnOnConsecutiveCalls(false, true);

        $emailService = $this->createStub(EmailService::class);

        $processor = new OrderProcessor($paymentGateway, $emailService);
        $order = new Order(100.00, 'test@example.com');

        // First call fails
        $this->assertFalse($processor->processOrder($order));

        // Second call succeeds
        $this->assertTrue($processor->processOrder($order));
    }
}

// Supporting classes for the example

interface PaymentGateway
{
    public function charge(float $amount): bool;
}

interface EmailService
{
    public function sendConfirmation(string $email): void;
}

final class Order
{
    public function __construct(
        private float $amount,
        private string $customerEmail
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }
}
