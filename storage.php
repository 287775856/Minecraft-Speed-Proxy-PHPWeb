<?php
require_once 'api-helper.php';
function getActivationCodesFilePath() {
    return __DIR__ . '/data/activation_codes.json';
}

function ensureActivationCodesStorage() {
    $filePath = getActivationCodesFilePath();
    $dir = dirname($filePath);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function loadActivationCodes() {
    ensureActivationCodesStorage();
    $content = file_get_contents(getActivationCodesFilePath());
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function cleanupExpiredActivations($token = null) {
    $codes = loadActivationCodes();
    $now = time();
    $remaining = [];
    $removedCodes = [];

    foreach ($codes as $record) {
        $expiresAt = $record['expires_at'] ?? null;
        $usedAt = $record['used_at'] ?? null;
        $username = $record['used_by'] ?? null;

        $isExpired = $expiresAt && $expiresAt < $now;
        if (!$isExpired) {
            $remaining[] = $record;
            continue;
        }

        $removed = false;
        if ($username && $token) {
            $response = makeApiRequest('remove_whitelist_user', 'POST', ['username' => $username], $token);
            $removed = $response['code'] == 200 && ($response['data']['status'] ?? null) == 200;
        }

        if ($removed || !$username) {
            $removedCodes[] = $record['code'] ?? '';
            continue;
        }

        $remaining[] = $record;
    }

    if (count($remaining) !== count($codes)) {
        saveActivationCodes($remaining);
    }

    return $removedCodes;
}

function saveActivationCodes(array $codes) {
    ensureActivationCodesStorage();
    file_put_contents(
        getActivationCodesFilePath(),
        json_encode($codes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function normalizeActivationCode($code) {
    return strtoupper(trim($code));
}

function generateActivationCodeValue() {
    return strtoupper(bin2hex(random_bytes(8)));
}

function createActivationCodes($quantity, $expiresInDays, $note) {
    $quantity = max(1, (int) $quantity);
    $expiresInDays = max(1, (int) $expiresInDays);
    $note = trim((string) $note);

    $codes = loadActivationCodes();
    $existing = array_column($codes, 'code');
    $existingLookup = array_flip($existing);
    $created = [];
    $now = time();
    $expiresAt = $now + ($expiresInDays * 86400);

    while (count($created) < $quantity) {
        $codeValue = generateActivationCodeValue();
        if (isset($existingLookup[$codeValue])) {
            continue;
        }

        $record = [
            'code' => $codeValue,
            'created_at' => $now,
            'expires_at' => $expiresAt,
            'note' => $note,
            'used_at' => null,
            'used_by' => null,
        ];

        $codes[] = $record;
        $created[] = $record;
        $existingLookup[$codeValue] = true;
    }

    saveActivationCodes($codes);

    return $created;
}

function findActivationCode(array $codes, $code) {
    $normalized = normalizeActivationCode($code);
    foreach ($codes as $index => $record) {
        if (($record['code'] ?? '') === $normalized) {
            return [$index, $record];
        }
    }

    return [null, null];
}

function markActivationCodeUsed($code, $username) {
    $codes = loadActivationCodes();
    list($index, $record) = findActivationCode($codes, $code);

    if ($index === null) {
        return false;
    }

    $codes[$index]['used_at'] = time();
    $codes[$index]['used_by'] = $username;
    saveActivationCodes($codes);

    return true;
}

function updateActivationCodeUser($code, $username) {
    $codes = loadActivationCodes();
    list($index, $record) = findActivationCode($codes, $code);

    if ($index === null) {
        return false;
    }

    $codes[$index]['used_by'] = $username;
    saveActivationCodes($codes);

    return true;
}
?>
