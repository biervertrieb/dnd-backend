<?php

namespace App;

/**
 * file based compendium service
 */
class CompendiumService
{
    private array $entries = [];
    private string $file;

    public function __construct(string $file = __DIR__ . '/../data/compendium.json')
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
    public function addEntry(string $title, string $body, string $tags): array
    {
        if ($title === null || $title === '')
            throw new \RuntimeException('Title is empty');
        if ($body === null || $body === '')
            throw new \RuntimeException('Body is empty');
        $entries = $this->load();
        $entry = [
            'id' => uniqid(),
            'title' => $title,
            'body' => $body,
            'tags' => $tags,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        array_unshift($entries, $entry);
        $this->save($entries);
        return $entry;
    }

    public function getEntries(bool $archive = false): array
    {
        $allEntries = $this->load();
        $returnEntries = [];
        foreach ($allEntries as $entry) {
            $isArchived = array_key_exists('archived', $entry) && $entry['archived'] == 'true';
            if ($archive === $isArchived)
                $returnEntries[] = $entry;
        }
        return $returnEntries;
    }

    /**
     * @return array<string,mixed>
     */
    public function updateEntry(string $id, ?string $title, ?string $body, ?string $tags)
    {
        if ($title === null || $title === '')
            throw new \RuntimeException('Title is empty');
        if ($body === null || $body === '')
            throw new \RuntimeException('Body is empty');
        $entries = $this->load();
        foreach ($entries as &$entry) {
            if ($entry['id'] === $id) {
                $entry['title'] = $title;
                $entry['tags'] = $tags;
                $entry['body'] = $body;
                $entry['updated_at'] = date('c');
                $this->save($entries);
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }

    public function deleteEntry(string $id)
    {
        $entries = $this->load();
        foreach ($entries as &$entry) {
            if ($entry['id'] === $id) {
                $entry['archived'] = 'true';
                $entry['updated_at'] = date('c');
                $this->save($entries);
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }
}
