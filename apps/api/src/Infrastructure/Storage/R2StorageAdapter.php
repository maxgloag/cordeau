<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Aws\S3\S3Client;

final class R2StorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly S3Client $s3,
        private readonly string $bucket,
        private readonly string $publicUrl,
    ) {
    }

    public function generatePresignedPutUrl(string $key): string
    {
        $cmd = $this->s3->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
        $request = $this->s3->createPresignedRequest($cmd, '+15 minutes');

        return (string) $request->getUri();
    }

    public function getPublicUrl(string $key): string
    {
        return rtrim($this->publicUrl, '/') . '/' . $key;
    }

    public function delete(string $key): void
    {
        $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
    }

    public function uploadBinary(string $key, string $data, string $contentType): void
    {
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $data,
            'ContentType' => $contentType,
        ]);
    }
}
