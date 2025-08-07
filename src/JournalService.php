<?php

namespace App;

/**
 * in-memory journal service
 */
class JournalService
{
    private array $entries = [];

    public function addEntry(string $title, string $body): array
    {
        $entry = [
            'id' => uniqid(),
            'title' => $title,
            'body' => $body,
            'created_at' => date('c'),
        ];
        $this->entries[] = $entry;
        return $entry;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }
}
