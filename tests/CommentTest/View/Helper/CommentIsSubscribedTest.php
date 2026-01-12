<?php declare(strict_types=1);

namespace CommentTest\View\Helper;

use Comment\View\Helper\CommentIsSubscribed;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentIsSubscribedTest extends AbstractHttpControllerTestCase
{
    public function testHelperIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelpers->has('commentIsSubscribed'));
        $helper = $viewHelpers->get('commentIsSubscribed');
        $this->assertInstanceOf(CommentIsSubscribed::class, $helper);
    }

    public function testHelperReturnsFalseForNullResource(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');
        $helper = $viewHelpers->get('commentIsSubscribed');

        $this->assertFalse($helper(null));
    }
}
