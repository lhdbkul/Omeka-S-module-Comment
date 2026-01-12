<?php declare(strict_types=1);

namespace CommentTest\Entity;

use Comment\Entity\Comment;
use DateTime;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    protected $comment;

    public function setUp(): void
    {
        $this->comment = new Comment();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->comment->getId());
        $this->assertNull($this->comment->getOwner());
        $this->assertNull($this->comment->getResource());
        $this->assertNull($this->comment->getSite());
        $this->assertFalse($this->comment->isApproved());
        $this->assertFalse($this->comment->isFlagged());
        $this->assertFalse($this->comment->isSpam());
        $this->assertNull($this->comment->getParent());
        $this->assertCount(0, $this->comment->getChildren());
    }

    public function testSetApproved(): void
    {
        $this->comment->setApproved(true);
        $this->assertTrue($this->comment->isApproved());

        $this->comment->setApproved(false);
        $this->assertFalse($this->comment->isApproved());

        // Test type coercion
        $this->comment->setApproved(1);
        $this->assertTrue($this->comment->isApproved());

        $this->comment->setApproved(0);
        $this->assertFalse($this->comment->isApproved());
    }

    public function testSetFlagged(): void
    {
        $this->comment->setFlagged(true);
        $this->assertTrue($this->comment->isFlagged());

        $this->comment->setFlagged(false);
        $this->assertFalse($this->comment->isFlagged());
    }

    public function testSetSpam(): void
    {
        $this->comment->setSpam(true);
        $this->assertTrue($this->comment->isSpam());

        $this->comment->setSpam(false);
        $this->assertFalse($this->comment->isSpam());
    }

    public function testSetPath(): void
    {
        $path = '/s/site/item/123';
        $this->comment->setPath($path);
        $this->assertEquals($path, $this->comment->getPath());
    }

    public function testSetEmail(): void
    {
        $email = 'test@example.com';
        $this->comment->setEmail($email);
        $this->assertEquals($email, $this->comment->getEmail());

        $this->comment->setEmail(null);
        $this->assertNull($this->comment->getEmail());
    }

    public function testSetName(): void
    {
        $name = 'John Doe';
        $this->comment->setName($name);
        $this->assertEquals($name, $this->comment->getName());

        $this->comment->setName(null);
        $this->assertNull($this->comment->getName());
    }

    public function testSetWebsite(): void
    {
        $website = 'https://example.com';
        $this->comment->setWebsite($website);
        $this->assertEquals($website, $this->comment->getWebsite());

        $this->comment->setWebsite(null);
        $this->assertNull($this->comment->getWebsite());
    }

    public function testSetIp(): void
    {
        $ip = '192.168.1.1';
        $this->comment->setIp($ip);
        $this->assertEquals($ip, $this->comment->getIp());

        // IPv6
        $ipv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $this->comment->setIp($ipv6);
        $this->assertEquals($ipv6, $this->comment->getIp());
    }

    public function testSetUserAgent(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $this->comment->setUserAgent($userAgent);
        $this->assertEquals($userAgent, $this->comment->getUserAgent());

        $this->comment->setUserAgent(null);
        $this->assertNull($this->comment->getUserAgent());
    }

    public function testSetBody(): void
    {
        $body = 'This is a test comment with some content.';
        $this->comment->setBody($body);
        $this->assertEquals($body, $this->comment->getBody());
    }

    public function testSetParent(): void
    {
        $parent = new Comment();
        $this->comment->setParent($parent);
        $this->assertSame($parent, $this->comment->getParent());

        $this->comment->setParent(null);
        $this->assertNull($this->comment->getParent());
    }

    public function testSetCreated(): void
    {
        $created = new DateTime('2024-01-15 10:30:00');
        $this->comment->setCreated($created);
        $this->assertSame($created, $this->comment->getCreated());
    }

    public function testSetModified(): void
    {
        $modified = new DateTime('2024-01-16 14:00:00');
        $this->comment->setModified($modified);
        $this->assertSame($modified, $this->comment->getModified());

        $this->comment->setModified(null);
        $this->assertNull($this->comment->getModified());
    }

    public function testHistory(): void
    {
        $this->assertNull($this->comment->getHistory());

        $this->comment->setHistory([['action' => 'test', 'date' => '2024-01-17T09:00:00+00:00']]);
        $this->assertCount(1, $this->comment->getHistory());

        $this->comment->addHistoryEntry('edit', ['previous_body' => 'old text'], 1);
        $this->assertCount(2, $this->comment->getHistory());

        $history = $this->comment->getHistory();
        $lastEntry = end($history);
        $this->assertSame('edit', $lastEntry['action']);
        $this->assertSame(1, $lastEntry['user_id']);
        $this->assertSame('old text', $lastEntry['data']['previous_body']);
    }

    public function testFluentInterface(): void
    {
        $result = $this->comment
            ->setApproved(true)
            ->setFlagged(false)
            ->setSpam(false)
            ->setPath('/test')
            ->setEmail('test@example.com')
            ->setName('Test User')
            ->setWebsite('https://example.com')
            ->setIp('127.0.0.1')
            ->setUserAgent('Test Agent')
            ->setBody('Test body')
            ->setCreated(new DateTime());

        $this->assertSame($this->comment, $result);
    }

    public function testChildrenIsCollection(): void
    {
        $children = $this->comment->getChildren();
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $children);
    }
}
