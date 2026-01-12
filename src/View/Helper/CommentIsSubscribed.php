<?php declare(strict_types=1);

namespace Comment\View\Helper;

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

        $api = $plugins->get('api');

        $subscription = $api
            ->searchOne('comment_subscriptions', [
                'owner_id' => $user->getId(),
                'resource_id' => $resource->id(),
                'return_scalar' => 'id',
            ])->getContent();

        return (bool) $subscription;
    }
}
