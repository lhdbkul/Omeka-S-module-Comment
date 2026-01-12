<?php declare(strict_types=1);

namespace CommentTest\View\Helper;

use Comment\View\Helper\CommentForm;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentFormHelperTest extends AbstractHttpControllerTestCase
{
    public function testHelperHasDefaultPartialName(): void
    {
        $this->assertEquals('common/comment-form', CommentForm::PARTIAL_NAME);
    }

    public function testHelperIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelpers->has('commentForm'));
        $helper = $viewHelpers->get('commentForm');
        $this->assertInstanceOf(CommentForm::class, $helper);
    }
}
