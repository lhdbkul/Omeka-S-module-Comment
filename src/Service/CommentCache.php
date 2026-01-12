<?php declare(strict_types=1);

namespace Comment\Service;

/**
 * Simple in-memory cache for comments during a single request.
 *
 * Shared between Module (for JSON-LD) and view helpers (for HTML rendering).
 * Uses static properties so data is shared across all instances/calls.
 */
class CommentCache
{
    /**
     * @var array Cache of comments indexed by resource ID.
     */
    protected static $resources = [];

    /**
     * @var array Cache of subscription status indexed by "userId-resourceId".
     */
    protected static $subscriptions = [];

    /**
     * Get comments for a resource.
     */
    public static function getByResource(int $resourceId): ?array
    {
        return self::$resources[$resourceId] ?? null;
    }

    /**
     * Set comments for a resource.
     */
    public static function setByResource(int $resourceId, array $comments): void
    {
        self::$resources[$resourceId] = $comments;
    }

    /**
     * Check if comments are cached for a resource.
     */
    public static function hasResource(int $resourceId): bool
    {
        return isset(self::$resources[$resourceId]);
    }

    /**
     * Get subscription status.
     */
    public static function getSubscription(int $userId, int $resourceId): ?bool
    {
        $key = $userId . '-' . $resourceId;
        return self::$subscriptions[$key] ?? null;
    }

    /**
     * Set subscription status.
     */
    public static function setSubscription(int $userId, int $resourceId, bool $subscribed): void
    {
        $key = $userId . '-' . $resourceId;
        self::$subscriptions[$key] = $subscribed;
    }

    /**
     * Check if subscription status is cached.
     */
    public static function hasSubscription(int $userId, int $resourceId): bool
    {
        $key = $userId . '-' . $resourceId;
        return isset(self::$subscriptions[$key]);
    }

    /**
     * Clear all caches (useful for testing).
     */
    public static function clear(): void
    {
        self::$resources = [];
        self::$subscriptions = [];
    }
}
