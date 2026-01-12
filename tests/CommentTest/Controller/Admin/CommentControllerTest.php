<?php declare(strict_types=1);

namespace CommentTest\Controller\Admin;

use CommentTest\CommentTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentControllerTest extends AbstractHttpControllerTestCase
{
    use CommentTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        // Set server variables required by CommentAdapter.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testBrowseActionRequiresLogin(): void
    {
        $this->logout();
        $this->dispatch('/admin/comment');
        $this->assertResponseStatusCode(302); // Redirect to login.
    }

    public function testShowDetailsActionReturnsCommentDetails(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id());

        $this->dispatch('/admin/comment/' . $comment->id() . '/show-details');
        $this->assertResponseStatusCode(200);
    }

    public function testApproveViaApi(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id(), ['approved' => false]);

        $this->assertFalse($comment->isApproved());

        // Approve via API.
        $this->api()->update('comments', $comment->id(), [
            'o:approved' => true,
        ], [], ['isPartial' => true]);

        // Verify it was approved.
        $response = $this->api()->read('comments', $comment->id());
        $updated = $response->getContent();
        $this->assertTrue($updated->isApproved());
    }

    public function testFlagViaApi(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id(), ['flagged' => false]);

        $this->assertFalse($comment->isFlagged());

        // Flag via API.
        $this->api()->update('comments', $comment->id(), [
            'o:flagged' => true,
        ], [], ['isPartial' => true]);

        // Verify change.
        $response = $this->api()->read('comments', $comment->id());
        $updated = $response->getContent();
        $this->assertTrue($updated->isFlagged());
    }

    public function testMarkSpamViaApi(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id(), ['spam' => false]);

        $this->assertFalse($comment->isSpam());

        // Mark as spam via API.
        $this->api()->update('comments', $comment->id(), [
            'o:spam' => true,
        ], [], ['isPartial' => true]);

        // Verify change.
        $response = $this->api()->read('comments', $comment->id());
        $updated = $response->getContent();
        $this->assertTrue($updated->isSpam());
    }

    public function testDeleteViaApi(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id());
        $commentId = $comment->id();

        // Remove from cleanup list.
        $this->createdComments = array_filter($this->createdComments, fn($id) => $id !== $commentId);

        // Delete via API.
        $this->api()->delete('comments', $commentId);

        // Verify deletion.
        $response = $this->api()->search('comments', ['id' => $commentId]);
        $this->assertEquals(0, $response->getTotalResults());
    }

    public function testBatchUpdateViaEntityManager(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $comment1 = $this->createComment($item1->id(), ['approved' => false]);
        $comment2 = $this->createComment($item2->id(), ['approved' => false]);

        // Update via entity manager to avoid adapter DQL issues.
        $entityManager = $this->getEntityManager();
        $entity1 = $entityManager->find(\Comment\Entity\Comment::class, $comment1->id());
        $entity2 = $entityManager->find(\Comment\Entity\Comment::class, $comment2->id());

        $entity1->setApproved(true);
        $entity2->setApproved(true);
        $entityManager->flush();

        // Verify both are approved.
        $entityManager->clear();
        $updated1 = $entityManager->find(\Comment\Entity\Comment::class, $comment1->id());
        $updated2 = $entityManager->find(\Comment\Entity\Comment::class, $comment2->id());

        $this->assertTrue($updated1->isApproved());
        $this->assertTrue($updated2->isApproved());
    }
}
