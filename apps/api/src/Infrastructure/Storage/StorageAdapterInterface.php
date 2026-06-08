<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

interface StorageAdapterInterface
{
    public function generatePresignedPutUrl(string $key): string;

    public function getPublicUrl(string $key): string;

    public function delete(string $key): void;

    public function uploadBinary(string $key, string $data, string $contentType): void;
}
