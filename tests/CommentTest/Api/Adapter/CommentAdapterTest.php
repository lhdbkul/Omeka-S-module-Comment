<?php declare(strict_types=1);

namespace CommentTest\Api\Adapter;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use CommentTest\CommentTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentAdapterTest extends AbstractHttpControllerTestCase
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

    public function testGetResourceName(): void
    {
        $adapter = $this->getCommentAdapter();
        $this->assertEquals('comments', $adapter->getResourceName());
    }

    public function testGetRepresentationClass(): void
    {
        $adapter = $this->getCommentAdapter();
        $this->assertEquals(CommentRepresentation::class, $adapter->getRepresentationClass());
    }

    public function testGetEntityClass(): void
    {
        $adapter = $this->getCommentAdapter();
        $this->assertEquals(Comment::class, $adapter->getEntityClass());
    }

    public function testSearchReturnsEmptyWhenNoComments(): void
    {
        $response = $this->api()->search('comments', []);
        $this->assertEquals(0, $response->getTotalResults());
        $this->assertEmpty($response->getContent());
    }

    public function testCreateComment(): void
    {
        $item = $this->createItem();
        $user = $this->getCurrentUser();

        $response = $this->api()->create('comments', [
            'o:owner' => ['o:id' => $user->getId()],
            'o:resource' => ['o:id' => $item->id()],
            'o:body' => 'This is a test comment.',
            'o:path' => '/s/test/item/' . $item->id(),
            'o:email' => $user->getEmail(),
            'o:name' => $user->getName(),
            'o:website' => '',
        ]);

        $comment = $response->getContent();
        $this->createdComments[] = $comment->id();

        $this->assertNotNull($comment->id());
        $this->assertEquals($user->getId(), $comment->owner()->id());
        $this->assertEquals($item->id(), $comment->resource()->id());
        $this->assertEquals('This is a test comment.', $comment->body());
        $this->assertFalse($comment->isApproved());
        $this->assertFalse($comment->isFlagged());
        $this->assertFalse($comment->isSpam());
    }

    public function testCreateApprovedComment(): void
    {
        $item = $this->createItem();

        $comment = $this->createComment($item->id(), ['approved' => true]);

        $this->assertTrue($comment->isApproved());
    }

    public function testSearchByResourceId(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id());
        $this->createComment($item2->id());

        $response = $this->api()->search('comments', [
            'resource_id' => $item1->id(),
        ]);

        $this->assertEquals(1, $response->getTotalResults());
        $comment = $response->getContent()[0];
        $this->assertEquals($item1->id(), $comment->resource()->id());
    }

    public function testSearchByOwnerId(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $adminUser = $this->getCurrentUser();

        // Create two comments as admin.
        $this->createComment($item1->id());
        $this->createComment($item2->id());

        // Search by admin owner_id.
        $response = $this->api()->search('comments', [
            'owner_id' => $adminUser->getId(),
        ]);

        $this->assertEquals(2, $response->getTotalResults());
        $comment = $response->getContent()[0];
        $this->assertEquals($adminUser->getId(), $comment->owner()->id());
    }

    public function testSearchByApproved(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id(), ['approved' => true]);
        $this->createComment($item2->id(), ['approved' => false]);

        // Search for approved comments.
        $response = $this->api()->search('comments', [
            'approved' => true,
        ]);
        $this->assertEquals(1, $response->getTotalResults());

        // Search for unapproved comments.
        $response = $this->api()->search('comments', [
            'approved' => false,
        ]);
        $this->assertEquals(1, $response->getTotalResults());
    }

    public function testSearchByFlagged(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id(), ['flagged' => true]);
        $this->createComment($item2->id(), ['flagged' => false]);

        $response = $this->api()->search('comments', [
            'flagged' => true,
        ]);
        $this->assertEquals(1, $response->getTotalResults());
    }

    public function testSearchBySpam(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id(), ['spam' => true]);
        $this->createComment($item2->id(), ['spam' => false]);

        $response = $this->api()->search('comments', [
            'spam' => true,
        ]);
        $this->assertEquals(1, $response->getTotalResults());
    }

    public function testSearchWithPagination(): void
    {
        // Create multiple items and comments.
        for ($i = 0; $i < 5; $i++) {
            $item = $this->createItem([
                'dcterms:title' => [['type' => 'literal', '@value' => "Test Item $i"]],
            ]);
            $this->createComment($item->id());
        }

        // Test first page.
        $response = $this->api()->search('comments', [
            'page' => 1,
            'per_page' => 2,
        ]);

        $this->assertEquals(5, $response->getTotalResults());
        $this->assertCount(2, $response->getContent());

        // Test second page.
        $response = $this->api()->search('comments', [
            'page' => 2,
            'per_page' => 2,
        ]);

        $this->assertCount(2, $response->getContent());
    }

    public function testSearchWithSorting(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id());
        usleep(100000); // 100ms delay
        $this->createComment($item2->id());

        // Sort by created DESC.
        $response = $this->api()->search('comments', [
            'sort_by' => 'created',
            'sort_order' => 'DESC',
        ]);

        $comments = $response->getContent();
        $this->assertCount(2, $comments);
        $this->assertEquals($item2->id(), $comments[0]->resource()->id());
        $this->assertEquals($item1->id(), $comments[1]->resource()->id());
    }

    public function testUpdateComment(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id());

        $this->api()->update('comments', $comment->id(), [
            'o:approved' => true,
            'o:body' => 'Updated comment body.',
        ]);

        // Re-read the comment.
        $response = $this->api()->read('comments', $comment->id());
        $updated = $response->getContent();

        $this->assertTrue($updated->isApproved());
        $this->assertEquals('Updated comment body.', $updated->body());
    }

    public function testDeleteComment(): void
    {
        $item = $this->createItem();
        $comment = $this->createComment($item->id());
        $commentId = $comment->id();

        // Remove from cleanup list since we're deleting it.
        $this->createdComments = array_filter($this->createdComments, fn($id) => $id !== $commentId);

        $this->api()->delete('comments', $commentId);

        $response = $this->api()->search('comments', ['id' => $commentId]);
        $this->assertEquals(0, $response->getTotalResults());
    }

    public function testValidationBodyRequired(): void
    {
        $this->expectException(\Omeka\Api\Exception\ValidationException::class);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        $this->api()->create('comments', [
            'o:owner' => ['o:id' => $user->getId()],
            'o:resource' => ['o:id' => $item->id()],
            'o:body' => '', // Empty body.
            'o:path' => '/test',
            'o:email' => $user->getEmail(),
            'o:name' => $user->getName(),
        ]);
    }

    public function testValidationEmailFormat(): void
    {
        // Test that entity validation rejects invalid email format for anonymous comments.
        $entityManager = $this->getEntityManager();
        $item = $this->createItem();
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $item->id());

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody('Test comment');
        $comment->setPath('/test');
        $comment->setEmail('invalid-email'); // Invalid email format.
        $comment->setName('Test User');
        $comment->setWebsite('');
        $comment->setIp('127.0.0.1');
        $comment->setUserAgent('Test Agent');
        $comment->setCreated(new \DateTime('now'));

        // Manually validate using the adapter.
        $adapter = $this->getCommentAdapter();
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $adapter->validateEntity($comment, $errorStore);

        // Should have validation error for email.
        $this->assertTrue($errorStore->hasErrors());
        $errors = $errorStore->getErrors();
        $this->assertArrayHasKey('o:email', $errors);
    }

    public function testParentChildRelationship(): void
    {
        $item = $this->createItem();

        // Create approved parent comment (replies require approved parent).
        $parent = $this->createComment($item->id(), [
            'body' => 'Parent comment',
            'approved' => true,
        ]);

        // Create child comment.
        $response = $this->api()->create('comments', [
            'o:resource' => ['o:id' => $item->id()],
            'o:body' => 'Child comment',
            'o:path' => '/test',
            'o:email' => 'test@example.com',
            'o:name' => 'Test User',
            'o:parent' => ['o:id' => $parent->id()],
        ]);

        $child = $response->getContent();
        $this->createdComments[] = $child->id();

        $this->assertEquals($parent->id(), $child->parent()->id());
    }

    public function testSearchByPath(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id(), ['path' => '/s/site1/item/1']);
        $this->createComment($item2->id(), ['path' => '/s/site2/item/2']);

        $response = $this->api()->search('comments', [
            'path' => '/s/site1/item/1',
        ]);

        $this->assertEquals(1, $response->getTotalResults());
    }

    public function testSearchByEmail(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createComment($item1->id(), ['email' => 'user1@example.com']);
        $this->createComment($item2->id(), ['email' => 'user2@example.com']);

        $response = $this->api()->search('comments', [
            'email' => 'user1@example.com',
        ]);

        $this->assertEquals(1, $response->getTotalResults());
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

    public function testValidationWebsiteUrl(): void
    {
        // Test that entity validation rejects invalid website URL.
        $entityManager = $this->getEntityManager();
        $item = $this->createItem();
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $item->id());

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody('Test comment');
        $comment->setPath('/test');
        $comment->setEmail('test@example.com');
        $comment->setName('Test User');
        $comment->setWebsite('not-a-valid-url'); // Invalid URL.
        $comment->setIp('127.0.0.1');
        $comment->setUserAgent('Test Agent');
        $comment->setCreated(new \DateTime('now'));

        // Manually validate using the adapter.
        $adapter = $this->getCommentAdapter();
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $adapter->validateEntity($comment, $errorStore);

        // Should have validation error for website.
        $this->assertTrue($errorStore->hasErrors());
        $errors = $errorStore->getErrors();
        $this->assertArrayHasKey('o:website', $errors);
    }

    public function testValidationWebsiteUrlValid(): void
    {
        // Test that valid website URLs pass validation.
        $entityManager = $this->getEntityManager();
        $item = $this->createItem();
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $item->id());

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody('Test comment');
        $comment->setPath('/test');
        $comment->setEmail('test@example.com');
        $comment->setName('Test User');
        $comment->setWebsite('https://example.com'); // Valid URL.
        $comment->setIp('127.0.0.1');
        $comment->setUserAgent('Test Agent');
        $comment->setCreated(new \DateTime('now'));

        $adapter = $this->getCommentAdapter();
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $adapter->validateEntity($comment, $errorStore);

        // Should not have validation error for website.
        $this->assertFalse($errorStore->hasErrors());
    }

    public function testWebsiteUrlQueryStringIsStripped(): void
    {
        $item = $this->createItem();
        $user = $this->getCurrentUser();

        $response = $this->api()->create('comments', [
            'o:owner' => ['o:id' => $user->getId()],
            'o:resource' => ['o:id' => $item->id()],
            'o:body' => 'Test comment with website.',
            'o:path' => '/s/test/item/' . $item->id(),
            'o:email' => $user->getEmail(),
            'o:name' => $user->getName(),
            'o:website' => 'https://example.com/page?tracking=123&utm_source=test',
        ]);

        $comment = $response->getContent();
        $this->createdComments[] = $comment->id();

        // Query string should be stripped.
        $this->assertEquals('https://example.com/page', $comment->website());
    }

    public function testReplyToUnapprovedParentFails(): void
    {
        $item = $this->createItem();

        // Create unapproved parent comment.
        $parent = $this->createComment($item->id(), [
            'body' => 'Parent comment',
            'approved' => false,
        ]);

        // Try to reply to unapproved parent - should fail validation.
        $entityManager = $this->getEntityManager();
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $item->id());
        $parentEntity = $entityManager->find(\Comment\Entity\Comment::class, $parent->id());

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody('Reply to unapproved parent');
        $comment->setPath('/test');
        $comment->setEmail('test@example.com');
        $comment->setName('Test User');
        $comment->setIp('127.0.0.1');
        $comment->setUserAgent('Test Agent');
        $comment->setCreated(new \DateTime('now'));
        $comment->setParent($parentEntity);

        $adapter = $this->getCommentAdapter();
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $adapter->validateEntity($comment, $errorStore);

        // Should have validation error for parent.
        $this->assertTrue($errorStore->hasErrors());
        $errors = $errorStore->getErrors();
        $this->assertArrayHasKey('o:parent', $errors);
    }

    public function testReplyToApprovedParentSucceeds(): void
    {
        $item = $this->createItem();

        // Create approved parent comment.
        $parent = $this->createComment($item->id(), [
            'body' => 'Parent comment',
            'approved' => true,
        ]);

        // Reply to approved parent - should pass validation.
        $entityManager = $this->getEntityManager();
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $item->id());
        $parentEntity = $entityManager->find(\Comment\Entity\Comment::class, $parent->id());

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody('Reply to approved parent');
        $comment->setPath('/test');
        $comment->setEmail('test@example.com');
        $comment->setName('Test User');
        $comment->setIp('127.0.0.1');
        $comment->setUserAgent('Test Agent');
        $comment->setCreated(new \DateTime('now'));
        $comment->setParent($parentEntity);

        $adapter = $this->getCommentAdapter();
        $errorStore = new \Omeka\Stdlib\ErrorStore();
        $adapter->validateEntity($comment, $errorStore);

        // Should not have validation errors.
        $this->assertFalse($errorStore->hasErrors());
    }
}
