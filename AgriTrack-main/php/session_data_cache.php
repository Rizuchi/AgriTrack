<?php

const SESSION_DATA_CACHE_TTL_SECONDS = 30;

function getSessionDataCache(string $key): ?array
{
    $entry = $_SESSION['agritrackDataCache'][$key] ?? null;
    if (!is_array($entry) || !isset($entry['expiresAt'], $entry['data']) || $entry['expiresAt'] <= time()) {
        unset($_SESSION['agritrackDataCache'][$key]);
        return null;
    }

    return $entry['data'];
}

function setSessionDataCache(string $key, array $data): void
{
    $now = time();
    foreach ($_SESSION['agritrackDataCache'] ?? [] as $cachedKey => $entry) {
        if (!isset($entry['expiresAt']) || $entry['expiresAt'] <= $now) {
            unset($_SESSION['agritrackDataCache'][$cachedKey]);
        }
    }

    $_SESSION['agritrackDataCache'][$key] = [
        'expiresAt' => $now + SESSION_DATA_CACHE_TTL_SECONDS,
        'data' => $data,
    ];
}

function clearSessionDataCachePrefix(string $prefix): void
{
    foreach (array_keys($_SESSION['agritrackDataCache'] ?? []) as $key) {
        if (strpos((string) $key, $prefix) === 0) {
            unset($_SESSION['agritrackDataCache'][$key]);
        }
    }
}