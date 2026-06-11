<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Presentation\Api\Photo\Payload\PrepareUploadPayload;
use App\Presentation\Api\Photo\Processor\PrepareUploadProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/photos/prepare',
            input: PrepareUploadPayload::class,
            processor: PrepareUploadProcessor::class,
            status: 200,
        ),
    ],
)]
final class PrepareUploadResource
{
    public function __construct(
        public readonly string $uploadUrl,
        public readonly string $remoteKey,
    ) {
    }
}
