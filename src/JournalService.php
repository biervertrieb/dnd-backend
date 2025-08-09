<?php

namespace App;

/**
 * in-memory journal service
 */
class JournalService
{
    private array $entries = [];
    private string $file;

    public function __construct(string $file = __DIR__ . '/../data/journal.json')
    {
        $this->file = $file;
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, json_encode([]));
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function load(): array
    {
        $raw = file_get_contents($this->file);
        return $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     */
    private function save(array $entries): void
    {
        file_put_contents($this->file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string,mixed>
     */
    public function addEntry(string $title, string $body): array
    {
        $entries = $this->load();
        $entry = [
            'id' => uniqid(),
            'title' => $title,
            'body' => $body,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        array_unshift($entries, $entry);
        $this->save($entries);
        return $entry;
    }

    public function getEntries(): array
    {
        return $this->load();
    }

    /**
     * @return array<string,mixed>
     */
    public function updateEntry(string $id, ?string $title, ?string $body)
    {
        $entries = $this->load();
        foreach ($entries as &$entry) {
            if ($entry['id'] === $id) {
                if ($title !== null)
                    $entry['title'] = $title;
                if ($body !== null)
                    $entry['body'] = $body;
                $entry['updated_at'] = date('c');
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }
}
