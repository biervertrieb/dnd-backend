<?php

namespace App\Services;

use App\Util\Slug;

/**
 * file based compendium service
 */
class CompendiumService extends \App\Util\Singleton
{
    private array $entries;
    private string $file;

    public function __construct(string $file = __DIR__ . '/../../data/compendium.json')
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

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $n = 2;
        $existing = array_column($this->entries, 'slug');
        while (in_array($slug, $existing, true)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        return $slug;
    }

    /**
     * @return array<string,mixed>
     */
    public function addEntry(string $title, string $body, array $tags): array
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '') {
            throw new \RuntimeException('Title is empty or whitespace only');
        }
        if (mb_strlen($title) > 255) {
            throw new \RuntimeException('Title exceeds 255 characters');
        }
        if ($body === '') {
            throw new \RuntimeException('Body is empty or whitespace only');
        }
        if (mb_strlen($body) > 10000) {
            throw new \RuntimeException('Body exceeds 10,000 characters');
        }
        if (!is_array($tags)) {
            throw new \RuntimeException('Tags must be an array');
        }
        $tags = array_map(function ($tag) {
            return is_string($tag) ? trim($tag) : $tag;
        }, $tags);
        $tags = array_values(array_filter($tags, function ($tag) {
            return is_string($tag) && $tag !== '' && mb_strlen($tag) <= 50;
        }));
        if (count($tags) > 10) {
            throw new \RuntimeException('No more than 10 tags allowed');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '' || mb_strlen($tag) > 50) {
                throw new \RuntimeException('Tag must be non-empty and ≤ 50 chars');
            }
        }
        $base = Slug::make($title);
        $slug = $this->uniqueSlug($base);
        $entry = [
            'id' => uniqid(),
            'slug' => $slug,
            'title' => $title,
            'body' => $body,
            'tags' => $tags,
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
            if ($archive === $isArchived) {
                $returnEntries[] = $entry;
            }
        }
        return $returnEntries;
    }

    public function getByID(string $id)
    {
        foreach ($this->entries as $entry) {
            $isArchived = array_key_exists('archived', $entry) && $entry['archived'] == 'true';
            if ($entry['id'] === $id && !$isArchived)
                return $entry;
        }
        throw new \RuntimeException('Entry not found');
    }

    public function getBySlug(string $slug): ?array
    {
        foreach ($this->entries as $e) {
            $isArchived = array_key_exists('archived', $e) && $e['archived'] == 'true';
            if (($e['slug'] ?? null) === $slug && !$isArchived)
                return $e;
        }
        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function updateEntry(string $id, ?string $title, ?string $body, ?array $tags)
    {
        $title = is_string($title) ? trim($title) : '';
        $body = is_string($body) ? trim($body) : '';
        if ($title === '') {
            throw new \RuntimeException('Title is empty or whitespace only');
        }
        if (mb_strlen($title) > 255) {
            throw new \RuntimeException('Title exceeds 255 characters');
        }
        if ($body === '') {
            throw new \RuntimeException('Body is empty or whitespace only');
        }
        if (mb_strlen($body) > 10000) {
            throw new \RuntimeException('Body exceeds 10,000 characters');
        }
        if (!is_array($tags)) {
            throw new \RuntimeException('Tags must be an array');
        }
        $tags = array_map(function ($tag) {
            return is_string($tag) ? trim($tag) : $tag;
        }, $tags);
        $tags = array_values(array_filter($tags, function ($tag) {
            return is_string($tag) && $tag !== '' && mb_strlen($tag) <= 50;
        }));
        if (count($tags) > 10) {
            throw new \RuntimeException('No more than 10 tags allowed');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '' || mb_strlen($tag) > 50) {
                throw new \RuntimeException('Tag must be non-empty and ≤ 50 chars');
            }
        }
        foreach ($this->entries as &$entry) {
            $isArchived = array_key_exists('archived', $entry) && $entry['archived'] == 'true';
            if ($entry['id'] === $id) {
                if ($isArchived) {
                    throw new \RuntimeException('Cannot update archived entry');
                }
                $entry['title'] = $title;
                $entry['tags'] = $tags;
                $entry['body'] = $body;
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
            $isArchived = array_key_exists('archived', $entry) && $entry['archived'] == 'true';
            if ($entry['id'] === $id) {
                if ($isArchived) {
                    throw new \RuntimeException('Entry already archived');
                }
                $entry['archived'] = 'true';
                $entry['updated_at'] = date('c');
                $this->save();
                return $entry;
            }
        }
        throw new \RuntimeException('Entry not found');
    }
}
