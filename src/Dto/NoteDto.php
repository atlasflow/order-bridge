<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

final readonly class NoteDto
{
    public function __construct(
        public string $type,
        public string $note,
        public string $createdBy,
        public string $createdAt,
    ) {
    }
}
