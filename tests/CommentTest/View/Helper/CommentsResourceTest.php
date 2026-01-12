<?php declare(strict_types=1);

namespace CommentTest\View\Helper;

use Comment\Service\CommentCache;
use Comment\View\Helper\CommentsResource;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentsResourceTest extends AbstractHttpControllerTestCase
{
    public function tearDown(): void
    {
        CommentCache::clear();
        parent::tearDown();
    }

    public function testHelperHasDefaultPartialName(): void
    {
        $this->assertEquals('common/comments', CommentsResource::PARTIAL_NAME);
    }

    public function testHelperIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelpers->has('commentsResource'));
        $helper = $viewHelpers->get('commentsResource');
        $this->assertInstanceOf(CommentsResource::class, $helper);
    }

    public function testStaticCacheWorks(): void
    {
        $this->assertFalse(CommentCache::hasResource(999));

        CommentCache::setByResource(999, ['test']);

        $this->assertTrue(CommentCache::hasResource(999));
        $this->assertEquals(['test'], CommentCache::getByResource(999));
    }
}
