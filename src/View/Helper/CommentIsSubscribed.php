<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Service\CommentCache;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class CommentIsSubscribed extends AbstractHelper
{
    /**
     * Check if the user has subscribed to a resource.
     */
    public function __invoke(?AbstractResourceEntityRepresentation $resource): bool
    {
        if (!$resource) {
            return false;
        }

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $user = $plugins->get('identity')();
        if (!$user) {
            return false;
        }

        $userId = $user->getId();
        $resourceId = $resource->id();

        // Use cached subscription status if available.
        if (CommentCache::hasSubscription($userId, $resourceId)) {
            return CommentCache::getSubscription($userId, $resourceId);
        }

        $api = $plugins->get('api');
        $subscription = $api
            ->searchOne('comment_subscriptions', [
                'owner_id' => $userId,
                'resource_id' => $resourceId,
                'return_scalar' => 'id',
            ])->getContent();

        $subscribed = (bool) $subscription;
        CommentCache::setSubscription($userId, $resourceId, $subscribed);

        return $subscribed;
    }
}
