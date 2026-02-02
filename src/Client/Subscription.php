<?php

declare(strict_types=1);

namespace TechDock\OpcUa\Client;

use RuntimeException;
use TechDock\OpcUa\Core\Encoding\BinaryDecoder;
use TechDock\OpcUa\Core\Messages\CreateMonitoredItemsRequest;
use TechDock\OpcUa\Core\Messages\CreateMonitoredItemsResponse;
use TechDock\OpcUa\Core\Messages\CreateSubscriptionRequest;
use TechDock\OpcUa\Core\Messages\CreateSubscriptionResponse;
use TechDock\OpcUa\Core\Messages\DeleteSubscriptionsRequest;
use TechDock\OpcUa\Core\Messages\DeleteSubscriptionsResponse;
use TechDock\OpcUa\Core\Messages\ModifySubscriptionRequest;
use TechDock\OpcUa\Core\Messages\ModifySubscriptionResponse;
use TechDock\OpcUa\Core\Messages\RequestHeader;
use TechDock\OpcUa\Core\Security\SecureChannel;
use TechDock\OpcUa\Core\Types\DataChangeNotification;
use TechDock\OpcUa\Core\Types\EventNotificationList;
use TechDock\OpcUa\Core\Types\ExtensionObject;
use TechDock\OpcUa\Core\Types\NotificationMessage;
use TechDock\OpcUa\Core\Types\TimestampsToReturn;
use Throwable;

/**
 * Subscription - manages a subscription and its monitored items.
 *
 * A subscription groups multiple monitored items and manages their lifecycle.
 */
final class Subscription
{
    private ?int $subscriptionId = null;
    private bool $created = false;

    private float $currentPublishingInterval;
    private int $currentLifetimeCount;
    private int $currentMaxKeepAliveCount;
    private bool $currentPublishingEnabled;

    /** @var array<int, MonitoredItem> Indexed by client handle */
    private array $monitoredItems = [];

    /** @var callable|null Callback: function(Subscription, NotificationMessage): void */
    private $onNotification = null;

    public function __construct(
        private readonly SecureChannel $secureChannel,
        private readonly float $requestedPublishingInterval = 1000.0,
        private readonly int $requestedLifetimeCount = 10000,
        private readonly int $requestedMaxKeepAliveCount = 10,
        private readonly int $maxNotificationsPerPublish = 0,
        private readonly bool $publishingEnabled = true,
        private readonly int $priority = 0,
    ) {
        $this->currentPublishingInterval = $requestedPublishingInterval;
        $this->currentLifetimeCount = $requestedLifetimeCount;
        $this->currentMaxKeepAliveCount = $requestedMaxKeepAliveCount;
        $this->currentPublishingEnabled = $publishingEnabled;
    }

    /**
     * Create the subscription on the server.
     */
    public function create(): void
    {
        if ($this->created) {
            throw new RuntimeException('Subscription already created');
        }

        $request = CreateSubscriptionRequest::create(
            requestHeader: RequestHeader::create(),
            requestedPublishingInterval: $this->requestedPublishingInterval,
            requestedLifetimeCount: $this->requestedLifetimeCount,
            requestedMaxKeepAliveCount: $this->requestedMaxKeepAliveCount,
            maxNotificationsPerPublish: $this->maxNotificationsPerPublish,
            publishingEnabled: $this->publishingEnabled,
            priority: $this->priority,
        );

        /** @var CreateSubscriptionResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, CreateSubscriptionResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "CreateSubscription failed: {$response->responseHeader->serviceResult}"
            );
        }

        $this->subscriptionId = $response->subscriptionId;
        $this->currentPublishingInterval = $response->revisedPublishingInterval;
        $this->currentLifetimeCount = $response->revisedLifetimeCount;
        $this->currentMaxKeepAliveCount = $response->revisedMaxKeepAliveCount;
        $this->created = true;
    }

    /**
     * Add a monitored item to the subscription.
     */
    public function addItem(MonitoredItem $item): void
    {
        $this->monitoredItems[$item->getClientHandle()] = $item;
    }

    /**
     * Add multiple monitored items to the subscription.
     *
     * @param MonitoredItem[] $items
     */
    public function addItems(array $items): void
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    /**
     * Create monitored items on the server.
     *
     * @param MonitoredItem[] $items
     */
    public function createMonitoredItems(
        array $items,
        TimestampsToReturn $timestampsToReturn = TimestampsToReturn::Both,
    ): void {
        if (!$this->created) {
            throw new RuntimeException('Subscription must be created before adding monitored items');
        }

        if ($items === []) {
            return;
        }

        // Build request with items to create
        $itemsToCreate = [];
        foreach ($items as $item) {
            $itemsToCreate[] = $item->getCreateRequest();
            $this->addItem($item);
        }

        if ($this->subscriptionId === null) {
            throw new RuntimeException('Subscription ID is null');
        }

        $request = CreateMonitoredItemsRequest::create(
            subscriptionId: $this->subscriptionId,
            itemsToCreate: $itemsToCreate,
            requestHeader: RequestHeader::create(),
            timestampsToReturn: $timestampsToReturn,
        );

        /** @var CreateMonitoredItemsResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, CreateMonitoredItemsResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "CreateMonitoredItems failed: {$response->responseHeader->serviceResult}"
            );
        }

        // Apply results to items
        foreach ($items as $index => $item) {
            if (isset($response->results[$index])) {
                $item->setCreateResult($response->results[$index]);
            }
        }
    }

    /**
     * Modify subscription parameters.
     */
    public function modify(
        ?float $requestedPublishingInterval = null,
        ?int $requestedLifetimeCount = null,
        ?int $requestedMaxKeepAliveCount = null,
        ?int $maxNotificationsPerPublish = null,
        ?int $priority = null,
    ): void {
        if (!$this->created) {
            throw new RuntimeException('Subscription must be created before modifying');
        }

        if ($this->subscriptionId === null) {
            throw new RuntimeException('Subscription ID is null');
        }

        $request = ModifySubscriptionRequest::create(
            subscriptionId: $this->subscriptionId,
            requestHeader: RequestHeader::create(),
            requestedPublishingInterval: $requestedPublishingInterval ?? $this->currentPublishingInterval,
            requestedLifetimeCount: $requestedLifetimeCount ?? $this->currentLifetimeCount,
            requestedMaxKeepAliveCount: $requestedMaxKeepAliveCount ?? $this->currentMaxKeepAliveCount,
            maxNotificationsPerPublish: $maxNotificationsPerPublish ?? $this->maxNotificationsPerPublish,
            priority: $priority ?? $this->priority,
        );

        /** @var ModifySubscriptionResponse $response */
        $response = $this->secureChannel->sendServiceRequest($request, ModifySubscriptionResponse::class);

        if (!$response->responseHeader->serviceResult->isGood()) {
            throw new RuntimeException(
                "ModifySubscription failed: {$response->responseHeader->serviceResult}"
            );
        }

        $this->currentPublishingInterval = $response->revisedPublishingInterval;
        $this->currentLifetimeCount = $response->revisedLifetimeCount;
        $this->currentMaxKeepAliveCount = $response->revisedMaxKeepAliveCount;
    }

    /**
     * Delete the subscription from the server.
     */
    public function delete(): void
    {
        if (!$this->created) {
            return;
        }

        if ($this->subscriptionId === null) {
            throw new RuntimeException('Subscription ID is null');
        }

        try {
            $request = DeleteSubscriptionsRequest::create(
                subscriptionIds: [$this->subscriptionId],
                requestHeader: RequestHeader::create(),
            );

            /** @var DeleteSubscriptionsResponse $response */
            $response = $this->secureChannel->sendServiceRequest($request, DeleteSubscriptionsResponse::class);

            // Check if deletion was successful
            if (isset($response->results[0]) && !$response->results[0]->isGood()) {
                throw new RuntimeException(
                    "DeleteSubscription failed: {$response->results[0]}"
                );
            }
        } finally {
            $this->created = false;
            $this->subscriptionId = null;
            $this->monitoredItems = [];
        }
    }

    /**
     * Process a notification message from the server.
     *
     * Called by Session when PublishResponse arrives.
     */
    public function processNotificationMessage(NotificationMessage $message): void
    {
        // Process each notification data item
        foreach ($message->notificationData as $extensionObject) {
            $this->processNotificationData($extensionObject);
        }

        // Invoke subscription-level callback
        if ($this->onNotification !== null) {
            ($this->onNotification)($this, $message);
        }
    }

    /**
     * Process notification data from an extension object.
     */
    private function processNotificationData(ExtensionObject $extensionObject): void
    {
        // Check if this is a DataChangeNotification
        if ($extensionObject->typeId->equals(DataChangeNotification::getTypeId())) {
            $this->processDataChangeNotification($extensionObject);
            return;
        }

        // Check if this is an EventNotificationList
        if ($extensionObject->typeId->equals(EventNotificationList::getTypeId())) {
            $this->processEventNotificationList($extensionObject);
            return;
        }

        // Future: Handle StatusChangeNotification, etc.
    }

    /**
     * Process data change notifications.
     */
    private function processDataChangeNotification(ExtensionObject $extensionObject): void
    {
        // Decode the DataChangeNotification from the extension object body
        if ($extensionObject->body === null) {
            return;
        }

        // Decode the binary body into a DataChangeNotification object
        $decoder = new BinaryDecoder($extensionObject->body);
        $notification = DataChangeNotification::decode($decoder);

        // Route each monitored item notification to the appropriate item
        foreach ($notification->monitoredItems as $itemNotification) {
            $clientHandle = $itemNotification->clientHandle;

            if (isset($this->monitoredItems[$clientHandle])) {
                $this->monitoredItems[$clientHandle]->saveNotification($itemNotification->value);
            }
        }
    }

    /**
     * Process event notifications.
     */
    private function processEventNotificationList(ExtensionObject $extensionObject): void
    {
        // Decode the EventNotificationList from the extension object body
        if ($extensionObject->body === null) {
            return;
        }

        // Decode the binary body into an EventNotificationList object
        $decoder = new BinaryDecoder($extensionObject->body);
        $notification = EventNotificationList::decode($decoder);

        // Route each event to the appropriate monitored item
        foreach ($notification->events as $eventFieldList) {
            $clientHandle = $eventFieldList->clientHandle;

            if (isset($this->monitoredItems[$clientHandle])) {
                $this->monitoredItems[$clientHandle]->saveEventNotification($eventFieldList);
            }
        }
    }

    /**
     * Set callback for subscription notifications.
     *
     * @param callable $callback function(Subscription, NotificationMessage): void
     */
    public function setNotificationCallback(callable $callback): void
    {
        $this->onNotification = $callback;
    }

    /**
     * Find monitored item by client handle.
     */
    public function findItemByClientHandle(int $clientHandle): ?MonitoredItem
    {
        return $this->monitoredItems[$clientHandle] ?? null;
    }

    /**
     * Get all monitored items.
     *
     * @return MonitoredItem[]
     */
    public function getMonitoredItems(): array
    {
        return array_values($this->monitoredItems);
    }

    /**
     * Get subscription ID.
     */
    public function getSubscriptionId(): ?int
    {
        return $this->subscriptionId;
    }

    /**
     * Check if subscription is created.
     */
    public function isCreated(): bool
    {
        return $this->created;
    }

    /**
     * Get current publishing interval.
     */
    public function getCurrentPublishingInterval(): float
    {
        return $this->currentPublishingInterval;
    }

    /**
     * Get current keep-alive count.
     */
    public function getCurrentMaxKeepAliveCount(): int
    {
        return $this->currentMaxKeepAliveCount;
    }

    /**
     * Get current publishing enabled status.
     */
    public function getCurrentPublishingEnabled(): bool
    {
        return $this->currentPublishingEnabled;
    }

    public function __destruct()
    {
        if ($this->created) {
            try {
                $this->delete();
            } catch (Throwable $e) {
                // Ignore errors during cleanup
            }
        }
    }
}
