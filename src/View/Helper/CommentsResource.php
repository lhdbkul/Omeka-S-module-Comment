<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Service\CommentCache;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class CommentsResource extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/comments';

    /**
     * Return the partial to display the comments.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $fallbackSetting = $plugins->get('fallbackSetting');

        $resourceId = $resource->id();

        // Use cached comments if available (shared with Module).
        if (CommentCache::hasResource($resourceId)) {
            $comments = CommentCache::getByResource($resourceId);
        } else {
            $api = $plugins->get('api');
            $comments = $api->search('comments', ['resource_id' => $resourceId])->getContent();
            CommentCache::setByResource($resourceId, $comments);
        }

        $label = $options['label'] ?? (string) $fallbackSetting('comment_label', ['site', 'global']);
        $structure = $options['structure'] ?? (string) $fallbackSetting('comment_structure', ['site', 'global'], 'flat');
        $closedOnLoad = $options['closed_on_load'] ?? (bool) $fallbackSetting('comment_closed_on_load', ['site', 'global'], false);
        $maxLength = $options['max_length'] ?? (int) $fallbackSetting('comment_max_length', ['site', 'global'], 2000);
        $skipGravatar = $options['skip_gravatar'] ?? (bool) $fallbackSetting('comment_skip_gravatar', ['site', 'global'], false);
        $legalText = $options['legal_text'] ?? (string) $fallbackSetting('comment_legal_text', ['site', 'global']);

        $template = $options['template'] ?? self::PARTIAL_NAME;

        $args = [
            'site' => $view->currentSite(),
            'resource' => $resource,
            'comments' => $comments,
            'label' => $label,
            'structure' => $structure,
            'closedOnLoad' => $closedOnLoad,
            'maxLength' => $maxLength,
            'skipGravatar' => $skipGravatar,
            'legalText' => $legalText,
            'parentId' => null,
        ] + $options;

        return $view->partial($template, $args);
    }
}
