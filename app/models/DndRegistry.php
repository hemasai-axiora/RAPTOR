<?php
/**
 * DndRegistry Model — TRAI & National Do Not Call Register Scrubbing
 * -------------------------------------------------------------------------
 * Maintains list of restricted phone numbers registered with TRAI NCPR or
 * opted-out directly by customers. Prevents regulatory telemarketing violations.
 */

class DndRegistry extends Model {

    public const DND_TYPES = ['full', 'promotional_only'];
    public const SOURCES = ['trai_scrubbed', 'customer_optout', 'manual'];

    public function getAll(int $limit = 100, int $offset = 0): array {
        $this->query("SELECT * FROM dnd_registry ORDER BY registered_at DESC LIMIT :lim OFFSET :off");
        $this->bind(':lim', max(1, $limit));
        $this->bind(':off', max(0, $offset));
        return $this->resultSet() ?: [];
    }

    public function isDnd(string $phone): bool {
        $norm = TelephonyService::normalizePhone($phone);
        if (empty($norm)) return false;

        $this->query("SELECT dnd_id FROM dnd_registry WHERE phone_number = :p LIMIT 1");
        $this->bind(':p', $norm);
        $row = $this->single();
        return (bool)$row;
    }

    public function addNumber(string $phone, string $type = 'full', string $source = 'trai_scrubbed', ?string $expiresAt = null): bool {
        $norm = TelephonyService::normalizePhone($phone);
        if (empty($norm)) return false;

        $t = in_array($type, self::DND_TYPES, true) ? $type : 'full';
        $s = in_array($source, self::SOURCES, true) ? $source : 'manual';
        $now = date('Y-m-d H:i:s');

        $this->query("SELECT dnd_id FROM dnd_registry WHERE phone_number = :p LIMIT 1");
        $this->bind(':p', $norm);
        $existing = $this->single();

        if ($existing) {
            $this->query("UPDATE dnd_registry SET dnd_type = :t, source = :s, expires_at = :exp WHERE dnd_id = :id");
            $this->bind(':t', $t);
            $this->bind(':s', $s);
            $this->bind(':exp', $expiresAt);
            $this->bind(':id', (int)$existing->dnd_id);
            return $this->execute();
        }

        $this->query("INSERT INTO dnd_registry (phone_number, dnd_type, source, registered_at, expires_at)
                      VALUES (:p, :t, :s, :now, :exp)");
        $this->bind(':p', $norm);
        $this->bind(':t', $t);
        $this->bind(':s', $s);
        $this->bind(':now', $now);
        $this->bind(':exp', $expiresAt);
        return $this->execute();
    }

    public function removeNumber(string $phone): bool {
        $norm = TelephonyService::normalizePhone($phone);
        if (empty($norm)) return false;

        $this->query("DELETE FROM dnd_registry WHERE phone_number = :p");
        $this->bind(':p', $norm);
        return $this->execute();
    }

    public function scrubBatch(array $numbers): array {
        $clean = [];
        $restricted = [];

        foreach ($numbers as $num) {
            $norm = TelephonyService::normalizePhone((string)$num);
            if (empty($norm)) continue;

            if ($this->isDnd($norm)) {
                $restricted[] = $norm;
            } else {
                $clean[] = $norm;
            }
        }

        return [
            'total' => count($clean) + count($restricted),
            'clean' => array_values(array_unique($clean)),
            'restricted' => array_values(array_unique($restricted))
        ];
    }
}
