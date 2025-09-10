<?php

namespace App\Services;

/**
 * file based journal service
 */
class JournalService extends \App\Util\Singleton
{
    private array $entries = [];
    private string $file;

    protected function __construct(string $file = __DIR__ . '/../../data/journal.json')
    {
        $this->file = $file;
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, json_encode([]));
        }
        $raw = file_get_contents($this->file);
        $this->entries = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     */
    private function save(): void
    {
        file_put_contents($this->file, json_encode($this->entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string,mixed>
     */
    public function addEntry(string $title, string $body, int $day): array
    {
        if ($title === null || $title === '')
            throw new \RuntimeException('Title is empty');
        if ($body === null || $body === '')
            throw new \RuntimeException('Body is empty');
        if ($day === null || $day === '')
            throw new \RuntimeException('Day is empty');
        $entry = [
            'id' => uniqid(),
            'title' => $title,
            'body' => $body,
            'day' => $day,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        array_unshift($this->entries, $entry);
        $this->save();
        return $entry;
    }

    public function getEntries(bool $archive = false): array
    {
        $returnEntries = [];
        foreach ($this->entries as $entry) {
            $isArchived = array_key_exists('archived', $entry) && $entry['archived'] == 'true';
            if ($archive === $isArchived)
                $returnEntries[] = $entry;
        }
        return $returnEntries;
    }

    /**
     * @return array<string,mixed>
     */
    public function updateEntry(string $id, ?string $title, ?string $body, ?int $day)
    {
        if ($title === null || $title === '')
            throw new \RuntimeException('Title is empty');
        if ($body === null || $body === '')
            throw new \RuntimeException('Body is empty');
        if ($day === null || $day === '')
            throw new \RuntimeException('Day is empty');
        foreach ($this->entries as &$entry) {
            if ($entry['id'] === $id) {
                $entry['title'] = $title;
                $entry['body'] = $body;
                $entry['day'] = $day;
                $entry['updated_at'] = date('c');
                $this->save();
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }

    public function deleteEntry(string $id)
    {
        foreach ($this->entries as &$entry) {
            if ($entry['id'] === $id) {
                $entry['archived'] = 'true';
                $entry['updated_at'] = date('c');
                $this->save();
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }
}
