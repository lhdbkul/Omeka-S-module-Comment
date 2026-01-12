<?php declare(strict_types=1);

namespace CommentTest\Form;

use Comment\Form\CommentForm;
use CommentTest\CommentTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentFormTest extends AbstractHttpControllerTestCase
{
    use CommentTestTrait;

    protected $form;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $this->form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null, // Anonymous user.
            'path' => '/s/test/item/1',
        ]);
    }

    public function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    public function testFormHasCorrectId(): void
    {
        $this->assertEquals('comment-form', $this->form->getAttribute('id'));
    }

    public function testFormHasResourceIdAttribute(): void
    {
        $this->assertEquals(1, $this->form->getAttribute('data-resource-id'));
    }

    public function testFormHasBodyElement(): void
    {
        $this->assertTrue($this->form->has('o:body'));
        $element = $this->form->get('o:body');
        $this->assertEquals('Comment', $element->getLabel());
        $this->assertEquals('textarea', $element->getAttribute('type'));
    }

    public function testFormHasResourceIdHiddenElement(): void
    {
        $this->assertTrue($this->form->has('resource_id'));
        $element = $this->form->get('resource_id');
        $this->assertEquals(1, $element->getValue());
    }

    public function testFormHasPathHiddenElement(): void
    {
        $this->assertTrue($this->form->has('path'));
        $element = $this->form->get('path');
        $this->assertEquals('/s/test/item/1', $element->getValue());
    }

    public function testFormHasParentIdHiddenElement(): void
    {
        $this->assertTrue($this->form->has('comment_parent_id'));
    }

    public function testFormHasSubmitButton(): void
    {
        $this->assertTrue($this->form->has('submit'));
        $element = $this->form->get('submit');
        $this->assertEquals('Comment it!', $element->getLabel());
    }

    public function testAnonymousFormHasNameElement(): void
    {
        $this->assertTrue($this->form->has('o:name'));
        $element = $this->form->get('o:name');
        $this->assertEquals('Name', $element->getLabel());
    }

    public function testAnonymousFormHasEmailElement(): void
    {
        $this->assertTrue($this->form->has('o:email'));
        $element = $this->form->get('o:email');
        $this->assertEquals('Email', $element->getLabel());
    }

    public function testAnonymousFormHasWebsiteElement(): void
    {
        $this->assertTrue($this->form->has('o:website'));
        $element = $this->form->get('o:website');
        $this->assertEquals('Website', $element->getLabel());
    }

    public function testLoggedInFormDoesNotHaveNameElement(): void
    {
        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        $this->assertFalse($form->has('o:name'));
        $this->assertFalse($form->has('o:email'));
        $this->assertFalse($form->has('o:website'));
    }

    public function testFormHasCustomCsrfElement(): void
    {
        // Custom CSRF with resource-specific name (timeout 1h).
        $this->assertTrue($this->form->has('csrf_1'));
    }

    public function testFormDoesNotHaveAutoAddedCsrf(): void
    {
        // The auto-added 'csrf' from Omeka initializer should be removed.
        $this->assertFalse($this->form->has('csrf'));
    }

    public function testAnonymousFormHasHoneypotWhenLegalTextSet(): void
    {
        // Set legal text to enable honeypot.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'I accept the terms.');

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        $this->assertTrue($form->has('o:check'));
        $element = $form->get('o:check');
        $this->assertEquals('display: none;', $element->getAttribute('style'));

        // Clean up.
        $settings->set('comment_legal_text', '');
    }

    public function testAnonymousFormHasLegalAgreementWhenLegalTextSet(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'I accept the terms and conditions.');

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        $this->assertTrue($form->has('legal_agreement'));
        $element = $form->get('legal_agreement');
        $this->assertEquals('Terms of service', $element->getLabel());

        // Clean up.
        $settings->set('comment_legal_text', '');
    }

    public function testAnonymousFormHasAntispamWhenEnabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'I accept the terms.');
        $settings->set('comment_antispam', true);

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        // Should have antispam fields.
        $this->assertTrue($form->has('address'));
        $this->assertTrue($form->has('address_a'));
        $this->assertTrue($form->has('address_b'));

        // Clean up.
        $settings->set('comment_legal_text', '');
        $settings->set('comment_antispam', false);
    }

    public function testAntispamValuesAreValid(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'I accept the terms.');
        $settings->set('comment_antispam', true);

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        $a = (int) $form->get('address_a')->getValue();
        $b = (int) $form->get('address_b')->getValue();

        // Values should be within expected ranges.
        $this->assertGreaterThanOrEqual(0, $a);
        $this->assertLessThanOrEqual(6, $a);
        $this->assertGreaterThanOrEqual(1, $b);
        $this->assertLessThanOrEqual(3, $b);

        // Sum should be single digit (0-9).
        $sum = $a + $b;
        $this->assertGreaterThanOrEqual(1, $sum);
        $this->assertLessThanOrEqual(9, $sum);

        // Clean up.
        $settings->set('comment_legal_text', '');
        $settings->set('comment_antispam', false);
    }

    public function testLoggedInFormDoesNotHaveLegalAgreement(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'I accept the terms.');

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        // Logged in users should not see legal agreement or antispam.
        $this->assertFalse($form->has('legal_agreement'));
        $this->assertFalse($form->has('o:check'));
        $this->assertFalse($form->has('address'));

        // Clean up.
        $settings->set('comment_legal_text', '');
    }

    public function testAnonymousFormDoesNotHaveAntispamWithoutLegalText(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', '');
        $settings->set('comment_antispam', true);

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        // Antispam is only shown if legal text is set.
        $this->assertFalse($form->has('address'));
        $this->assertFalse($form->has('o:check'));

        // Clean up.
        $settings->set('comment_antispam', false);
    }

    // =========================================================================
    // Alias Mode Tests
    // =========================================================================

    public function testLoggedInFormDoesNotHaveAliasFieldsWhenDisabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', false);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        // When alias mode is disabled, no identity mode selector.
        $this->assertFalse($form->has('comment_identity_mode'));
        $this->assertFalse($form->has('o:name'));
        $this->assertFalse($form->has('o:email'));
    }

    public function testLoggedInFormHasAliasFieldsWhenEnabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        // When alias mode is enabled, should have identity mode selector.
        $this->assertTrue($form->has('comment_identity_mode'));
        $element = $form->get('comment_identity_mode');
        $this->assertEquals('Comment as', $element->getLabel());

        // Should have alias fields.
        $this->assertTrue($form->has('o:name'));
        $nameElement = $form->get('o:name');
        $this->assertEquals('comment-alias-name', $nameElement->getAttribute('id'));
        $this->assertEquals('comment-alias-field', $nameElement->getAttribute('class'));

        $this->assertTrue($form->has('o:email'));
        $emailElement = $form->get('o:email');
        $this->assertEquals('comment-alias-email', $emailElement->getAttribute('id'));
        $this->assertEquals('comment-alias-field', $emailElement->getAttribute('class'));

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAliasIdentityModeDefaultsToAccount(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        $element = $form->get('comment_identity_mode');
        $this->assertEquals('account', $element->getValue());

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAliasIdentityModeOptionsIncludeUserInfo(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test/item/1',
        ]);

        $element = $form->get('comment_identity_mode');
        $options = $element->getValueOptions();

        // 'account' option should contain user name and email.
        $this->assertArrayHasKey('account', $options);
        $this->assertStringContainsString($user->getName(), $options['account']);
        $this->assertStringContainsString($user->getEmail(), $options['account']);

        // 'alias' option should be available.
        $this->assertArrayHasKey('alias', $options);

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAnonymousFormDoesNotHaveAliasFields(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(CommentForm::class, [
            'site_slug' => 'test',
            'resource_id' => 1,
            'user' => null,
            'path' => '/s/test/item/1',
        ]);

        // Anonymous users should not have identity mode selector.
        $this->assertFalse($form->has('comment_identity_mode'));

        // But should have regular name/email fields.
        $this->assertTrue($form->has('o:name'));
        $this->assertTrue($form->has('o:email'));

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }
}
