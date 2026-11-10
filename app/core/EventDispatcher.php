<?php
/**
 * Raptor Core Event Dispatcher
 * -------------------------------------------------------------------------
 * Lightweight, zero-dependency, PSR-14 inspired synchronous & asynchronous
 * event distribution pipeline for decoupled module architecture.
 *
 * Example Usage:
 *   EventDispatcher::addListener('payroll.approved', function($event) { ... });
 *   EventDispatcher::dispatch('payroll.approved', ['payroll_id' => 12]);
 */

declare(strict_types=1);

class EventDispatcher {
    /** @var array<string, array<int, array{callable: callable, priority: int}>> */
    private static array $listeners = [];

    /** @var array<string, array<int, mixed>> Recorded event dispatch history for testing/telemetry */
    private static array $dispatchedEvents = [];

    /**
     * Subscribe a listener callback to an event.
     * Higher priority integers execute earlier (default: 0).
     */
    public static function addListener(string $eventName, callable $listener, int $priority = 0): void {
        self::$listeners[$eventName][] = [
            'callable' => $listener,
            'priority' => $priority
        ];

        // Sort listeners descending by priority
        usort(self::$listeners[$eventName], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Dispatch an event with arbitrary payload data.
     * Returns true if all listeners executed without propagation stop.
     */
    public static function dispatch(string $eventName, array $payload = []): bool {
        self::$dispatchedEvents[] = [
            'event'     => $eventName,
            'payload'   => $payload,
            'timestamp' => microtime(true)
        ];

        if (empty(self::$listeners[$eventName])) {
            return true;
        }

        foreach (self::$listeners[$eventName] as $entry) {
            try {
                $result = call_user_func($entry['callable'], $payload);
                // Return false from listener to stop propagation
                if ($result === false) {
                    return false;
                }
            } catch (Throwable $e) {
                // Log exception and continue executing remaining listeners
                error_log("EventDispatcher exception in listener for [{$eventName}]: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public static function hasListeners(string $eventName): bool {
        return !empty(self::$listeners[$eventName]);
    }

    /**
     * Clear all registered listeners (useful for isolated tests).
     */
    public static function reset(): void {
        self::$listeners = [];
        self::$dispatchedEvents = [];
    }

    /**
     * Get recent dispatched events list.
     */
    public static function getDispatchedEvents(): array {
        return self::$dispatchedEvents;
    }
}
