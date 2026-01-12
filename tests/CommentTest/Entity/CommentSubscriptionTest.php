<?php declare(strict_types=1);

namespace CommentTest\Entity;

use Comment\Entity\CommentSubscription;
use DateTime;
use PHPUnit\Framework\TestCase;

class CommentSubscriptionTest extends TestCase
{
    protected $subscription;

    public function setUp(): void
    {
        $this->subscription = new CommentSubscription();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->subscription->getId());
        $this->assertNull($this->subscription->getOwner());
        $this->assertNull($this->subscription->getResource());
    }

    public function testSetCreated(): void
    {
        $created = new DateTime('2024-01-15 10:30:00');
        $this->subscription->setCreated($created);
        $this->assertSame($created, $this->subscription->getCreated());
    }

    public function testFluentInterface(): void
    {
        $result = $this->subscription
            ->setCreated(new DateTime());

        $this->assertSame($this->subscription, $result);
    }
}
