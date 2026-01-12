<?php declare(strict_types=1);

namespace Comment\Api\Adapter;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Uri as UriValidator;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;

class CommentAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'resource_id' => 'resource',
        'item_set_id' => 'resource',
        'item_id' => 'resource',
        'media_id' => 'resource',
        'site_id' => 'site',
        'approved' => 'approved',
        'flagged' => 'flagged',
        'spam' => 'spam',
        'path' => 'path',
        'email' => 'email',
        'website' => 'website',
        'name' => 'name',
        'ip' => 'ip',
        'user_agent' => 'user_agent',
        'parent_id' => 'parent',
        'created' => 'created',
        'modified' => 'modified',
        'edited' => 'edited',
        // For info.
        // // 'resource_title' => 'resource',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'owner' => 'owner',
        'resource' => 'resource',
        'item_set' => 'resource',
        'item' => 'resource',
        'media' => 'resource',
        'site' => 'site',
        'approved' => 'approved',
        'flagged' => 'flagged',
        'spam' => 'spam',
        'path' => 'path',
        'email' => 'email',
        'website' => 'website',
        'name' => 'name',
        'ip' => 'ip',
        'user_agent' => 'user_agent',
        'body' => 'body',
        'parent' => 'parent',
        'children' => 'children',
        'created' => 'created',
        'modified' => 'modified',
        'edited' => 'edited',
    ];

    public function getResourceName()
    {
        return 'comments';
    }

    public function getRepresentationClass()
    {
        return CommentRepresentation::class;
    }

    public function getEntityClass()
    {
        return Comment::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        // TODO Use CommonAdapterTrait.

        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        // TODO Check resource and owner visibility for public view.

        if (array_key_exists('id', $query)) {
            $this->buildQueryIdsItself($qb, $query['id'], 'id');
        }

        // All comments with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
            'site_id' => 'site',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                $this->buildQueryIds($qb, $query[$queryKey], $column, 'id');
            }
        }

        // This is "or" when multiple collections are set.
        if (array_key_exists('collection_id', $query) && !in_array($query['collection_id'], [null, '', []], true)) {
            $values = is_array($query['collection_id'])
                ? array_values(array_unique(array_map('intval', $query['collection_id'])))
                : [(int) $query['collection_id']];
            $itemAlias = $this->createAlias();
            $itemSetAlias = $this->createAlias();

            // TODO Check resource for collection_id? Add a join on resource? Check rights and visibility?
            // This feature can be used with private collections in some cases?
            $qb
                // Normally, just join "ìtem_item_set", but id does not seems to
                // be possible with doctrine orm, so use a join with item and
                // filter it with item sets below.
                ->innerJoin(
                    // 'item_item_set',
                    Item::class,
                    $itemAlias,
                    'WITH',
                    $expr->eq($alias . '.resource', $itemAlias . '.id')
                );

            if ($values === [0]) {
                // Only items with no item sets requested.
                $qb
                    ->andWhere($itemAlias . '.itemSets IS EMPTY');
            } elseif (count($values) === 1) {
                // Single collection id.
                $paramAlias = $this->createAlias();
                $qb
                    ->innerJoin($itemAlias . '.itemSets', $itemSetAlias)
                    ->andWhere($expr->eq($itemSetAlias . '.id', ':' . $paramAlias))
                    ->setParameter($paramAlias, reset($values), ParameterType::INTEGER);
            } elseif (in_array(0, $values, true)) {
                // Include items with no item sets plus specific sets: 0 mixed
                // with other ids.
                $wantedIds = array_values(array_filter($values, fn ($v) => $v !== 0));
                if ($wantedIds) {
                    $paramAlias = $this->createAlias();
                    $qb
                        // Left join to allow null (no item sets).
                        ->leftJoin($itemAlias . '.itemSets', $itemSetAlias)
                        ->andWhere($expr->orX(
                            $itemAlias . '.itemSets IS EMPTY',
                            $expr->in($itemSetAlias . '.id', ':' . $paramAlias)
                        ))
                        ->setParameter($paramAlias, $wantedIds, Connection::PARAM_INT_ARRAY);
                } else {
                    $qb->andWhere($itemAlias . '.itemSets IS EMPTY');
                }
            } else {
                // Multiple collection ids.
                $paramAlias = $this->createAlias();
                $qb->innerJoin($itemAlias . '.itemSets', $itemSetAlias);
                $qb
                    ->andWhere($expr->in($itemSetAlias . '.id', ':' . $paramAlias))
                    ->setParameter($paramAlias, $values, Connection::PARAM_INT_ARRAY);
            }
        }

        if (array_key_exists('has_resource', $query)) {
            // An empty string means true in order to manage get/post query.
            if (in_array($query['has_resource'], [false, 'false', 0, '0'], true)) {
                $qb
                    ->andWhere($expr->isNull($alias . '.resource'));
            } else {
                $qb
                    ->andWhere($expr->isNotNull($alias . '.resource'));
            }
        }

        if (array_key_exists('resource_type', $query)) {
            $mapResourceTypes = [
                // 'users' => User::class,
                // 'sites' => Site::class,
                'resources' => Resource::class,
                'item_sets' => ItemSet::class,
                'items' => Item::class,
                'media' => Media::class,
            ];
            if ($query['resource_type'] === 'resources') {
                $qb
                     ->andWhere($expr->isNotNull($alias . '.resource'));
            } elseif (isset($mapResourceTypes[$query['resource_type']])) {
                $entityAlias = $this->createAlias();
                $qb
                    ->innerJoin(
                        $mapResourceTypes[$query['resource_type']],
                        $entityAlias,
                        'WITH',
                        $expr->eq(
                            $alias . '.resource',
                            $entityAlias . '.id'
                        )
                    );
            } elseif ($query['resource_type'] !== '') {
                $qb
                    ->andWhere('1 = 0');
            }
        }

        foreach ([
            'path' => 'path',
            'email' => 'email',
            'website' => 'website',
            'name' => 'name',
            'ip' => 'ip',
            'user_agent' => 'user_agent',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query) && strlen($query[$queryKey])) {
                $qb
                    ->andWhere($expr->eq($alias . '.' . $column, $query[$queryKey]));
            }
        }

        // All comments with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'approved' => 'approved',
            'flagged' => 'flagged',
            'spam' => 'spam',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                // An empty string means true in order to manage get/post query.
                if (in_array($query[$queryKey], [false, 'false', 0, '0'], true)) {
                    $qb
                        ->andWhere($expr->eq($alias . '.' . $column, 0));
                } else {
                    $qb
                        ->andWhere($expr->eq($alias . '.' . $column, 1));
                }
            }
        }

        /** @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery() */
        $dateSearches = [
            'created_before' => ['lt', 'created'],
            'created_after' => ['gt', 'created'],
            'modified_before' => ['lt', 'modified'],
            'modified_after' => ['gt', 'modified'],
            'edited_before' => ['lt', 'edited'],
            'edited_after' => ['gt', 'edited'],
        ];
        $dateGranularities = [
            DateTime::ISO8601,
            '!Y-m-d\TH:i:s',
            '!Y-m-d\TH:i',
            '!Y-m-d\TH',
            '!Y-m-d',
            '!Y-m',
            '!Y',
        ];
        foreach ($dateSearches as $dateSearchKey => $dateSearch) {
            if (isset($query[$dateSearchKey])) {
                foreach ($dateGranularities as $dateGranularity) {
                    $date = DateTime::createFromFormat($dateGranularity, $query[$dateSearchKey]);
                    if (false !== $date) {
                        break;
                    }
                }
                $qb->andWhere($expr->{$dateSearch[0]} (
                    sprintf('omeka_root.%s', $dateSearch[1]),
                    // If the date is invalid, pass null to ensure no results.
                    $this->createNamedParameter($qb, $date ?: null)
                ));
            }
        }
    }

    // public function sortQuery(QueryBuilder $qb, array $query)
    // {
    //     if (is_string($query['sort_by'])) {
    //         switch ($query['sort_by']) {
    //             default:
    //                 parent::sortQuery($qb, $query);
    //                 break;
    //         }
    //     }
    // }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Comment\Entity\Comment $entity */

        $data = $request->getContent();

        // The owner, site and resource can be null.
        switch ($request->getOperation()) {
            case Request::CREATE:
                $this->hydrateOwner($request, $entity);

                if (isset($data['o:resource'])) {
                    if (is_object($data['o:resource'])) {
                        $resource = $data['o:resource'] instanceof Resource
                            ? $data['o:resource']
                            : null;
                    } elseif (is_array($data['o:resource'])) {
                        $resource = $this->getAdapter('resources')
                            ->findEntity(['id' => $data['o:resource']['o:id']]);
                    } else {
                        $resource = null;
                    }
                    $entity->setResource($resource);
                }

                if (isset($data['o:site'])) {
                    if (is_object($data['o:site'])) {
                        $site = $data['o:site'];
                    } elseif (is_array($data['o:site'])) {
                        $site = $this->getAdapter('sites')
                            ->findEntity(['id' => $data['o:site']['o:id']]);
                    } else {
                        $site = null;
                    }
                    $entity->setSite($site);
                }

                if (isset($data['o:parent'])) {
                    if (is_object($data['o:parent'])) {
                        $parent = $data['o:parent'];
                    } elseif (is_array($data['o:parent'])) {
                        $parent = $this
                            ->findEntity(['id' => $data['o:parent']['o:id']]);
                    } else {
                        $parent = null;
                    }
                    $entity->setParent($parent);
                }

                $entity->setPath($request->getValue('o:path', ''));

                $entity->setBody($request->getValue('o:body', ''));

                $owner = $entity->getOwner();
                if ($owner) {
                    $entity->setEmail($owner->getEmail());
                    $entity->setName($owner->getName());
                } else {
                    $entity->setEmail($request->getValue('o:email'));
                    $entity->setName($request->getValue('o:name'));
                }

                $entity->setWebsite($this->cleanWebsiteUrl($request->getValue('o:website', '')));
                $entity->setIp($this->getClientIp());
                $entity->setUserAgent($this->getUserAgent());
                break;

            case Request::UPDATE:
                if ($this->shouldHydrate($request, 'o:body')) {
                    $entity->setBody($request->getValue('o:body', ''));
                }

                if ($this->shouldHydrate($request, 'o:edited')) {
                    $edited = $request->getValue('o:edited') ?: null;
                    if ($edited && is_string($edited)) {
                        $edited = new DateTime($edited);
                    }
                    $entity->setEdited($edited);
                }
                break;
        }

        if ($this->shouldHydrate($request, 'o:approved')) {
            $entity->setApproved($request->getValue('o:approved', false));
        }
        if ($this->shouldHydrate($request, 'o:flagged')) {
            $entity->setFlagged($request->getValue('o:flagged', false));
        }
        if ($this->shouldHydrate($request, 'o:spam')) {
            $entity->setSpam($request->getValue('o:spam', false));
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        // When the user, the resource or the site are deleted, there is no
        // validation here, so it can be checked when created or updated?
        // No, because there may be multiple updates.
        // So the name and email are prefilled with current values if exist.
        $owner = $entity->getOwner();
        if (empty($owner)) {
            $email = $entity->getEmail();
            $validator = new EmailAddress();
            if (!$validator->isValid($email)) {
                $errorStore->addValidatorMessages('o:email', $validator->getMessages());
            }
        }

        // Validate website URL if provided.
        $website = $entity->getWebsite();
        if ($website !== null && $website !== '') {
            $uriValidator = new UriValidator(['allowRelative' => false]);
            if (!$uriValidator->isValid($website)) {
                $errorStore->addValidatorMessages('o:website', $uriValidator->getMessages());
            }
        }

        if ($entity->getIp() == '::') {
            $errorStore->addError('o:ip', 'The ip cannot be empty.'); // @translate
        }

        if ($entity->getUserAgent() == false) {
            $errorStore->addError('o:user_agent', 'The user agent cannot be empty.'); // @translate
        }

        $body = $entity->getBody();
        if (!is_string($body) || $body === '') {
            $errorStore->addError('o:body', 'The body cannot be empty.'); // @translate
        }

        // Prevent replying to unapproved comments.
        $parent = $entity->getParent();
        if ($parent && !$parent->isApproved()) {
            $errorStore->addError('o:parent', 'Cannot reply to a comment that is not yet approved.'); // @translate
        }
    }

    public function preprocessBatchUpdate(array $data, Request $request)
    {
        $updatables = [
            'o:approved' => true,
            'o:flagged' => true,
            'o:spam' => true,
        ];
        $rawData = $request->getContent();
        $rawData = array_intersect_key($rawData, $updatables);
        $data = $rawData + $data;
        return $data;
    }

    /**
     * Get the ip of the client.
     *
     * @return string
     */
    protected function getClientIp()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            return $ip;
        }
        return '::';
    }

    /**
     * Get the user agent.
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return @$_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Clean website URL by removing query string and fragment.
     *
     * @param string $url
     * @return string
     */
    protected function cleanWebsiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $cleanUrl = '';
        if (!empty($parts['scheme'])) {
            $cleanUrl .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $cleanUrl .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $cleanUrl .= ':' . $parts['port'];
        }
        if (!empty($parts['path'])) {
            $cleanUrl .= $parts['path'];
        }

        return $cleanUrl;
    }
}
