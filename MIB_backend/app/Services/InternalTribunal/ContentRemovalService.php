<?php

namespace App\Services\InternalTribunal;

use App\Models\TestamentResourceNote;
use App\Models\User;

class ContentRemovalService
{
    public const TYPE_TESTAMENT_NOTE = 'testament_note';

    /**
     * Strict allowlist of supported content types mapped to their Eloquent models.
     */
    protected const SUPPORTED_TYPES = [
        self::TYPE_TESTAMENT_NOTE => TestamentResourceNote::class,
    ];

    /**
     * Validate whether a content type is supported.
     */
    public function isSupportedType(string $contentType): bool
    {
        return array_key_exists($contentType, self::SUPPORTED_TYPES);
    }

    /**
     * Parse a penalty_value into contentType and contentId.
     *
     * @return array{content_type: string, content_id: int}
     */
    public function parseIdentifier(string $penaltyValue): array
    {
        $parts = explode(':', $penaltyValue, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            throw new \DomainException("Invalid content identifier format: '{$penaltyValue}'. Expected 'content_type:content_id'.");
        }

        $contentType = $parts[0];
        $contentId = (int) $parts[1];

        if (!$this->isSupportedType($contentType)) {
            throw new \DomainException("Unsupported content type: '{$contentType}'. Only 'testament_note' is supported.");
        }

        return [
            'content_type' => $contentType,
            'content_id' => $contentId,
        ];
    }

    /**
     * Normalize contentType and contentId into standard penalty_value string.
     */
    public function formatIdentifier(string $contentType, int $contentId): string
    {
        if (!$this->isSupportedType($contentType)) {
            throw new \DomainException("Unsupported content type: '{$contentType}'. Only 'testament_note' is supported.");
        }

        if ($contentId <= 0) {
            throw new \DomainException("Content ID must be a positive integer.");
        }

        return "{$contentType}:{$contentId}";
    }

    /**
     * Validate and remove the specified content item.
     * Sets status to STATUS_REMOVED (never hard-deletes).
     *
     * @return array Metadata for private audit logging
     */
    public function remove(string $contentType, int $contentId, User $reportedUser): array
    {
        $note = $this->validateCanRemove($contentType, $contentId, $reportedUser);

        $metadata = [
            'content_type' => $contentType,
            'content_id' => $note->id,
            'original_title' => $note->title,
            'category' => $note->category,
            'previous_status' => $note->status,
        ];

        $note->update([
            'status' => TestamentResourceNote::STATUS_REMOVED,
        ]);

        return $metadata;
    }

    /**
     * Validate and restore the specified content item.
     * Sets status back to STATUS_ACTIVE.
     *
     * @return array Metadata for private audit logging
     */
    public function restore(string $contentType, int $contentId, User $user): array
    {
        $note = $this->validateCanRestore($contentType, $contentId, $user);

        $metadata = [
            'content_type' => $contentType,
            'content_id' => $note->id,
            'original_title' => $note->title,
            'category' => $note->category,
            'previous_status' => $note->status,
        ];

        $note->update([
            'status' => TestamentResourceNote::STATUS_ACTIVE,
        ]);

        return $metadata;
    }

    /**
     * Validate that the content item can be removed.
     */
    public function validateCanRemove(string $contentType, int $contentId, User $reportedUser): TestamentResourceNote
    {
        if (!$this->isSupportedType($contentType)) {
            throw new \DomainException("Unsupported content type: '{$contentType}'. Only 'testament_note' is supported.");
        }

        $note = TestamentResourceNote::find($contentId);
        if (!$note) {
            throw new \DomainException("Community resource note with ID {$contentId} was not found.");
        }

        if ((int) $note->user_id !== (int) $reportedUser->id) {
            throw new \DomainException("Ownership validation failed: Community resource note #{$contentId} does not belong to the reported user.");
        }

        if ($note->status === TestamentResourceNote::STATUS_REMOVED) {
            throw new \DomainException("Community resource note #{$contentId} has already been removed.");
        }

        if ($note->status !== TestamentResourceNote::STATUS_ACTIVE) {
            throw new \DomainException("Community resource note #{$contentId} cannot be removed because its status is '{$note->status}' (expected 'active').");
        }

        return $note;
    }

    /**
     * Validate that the content item can be restored.
     */
    public function validateCanRestore(string $contentType, int $contentId, User $user): TestamentResourceNote
    {
        if (!$this->isSupportedType($contentType)) {
            throw new \DomainException("Unsupported content type: '{$contentType}'. Only 'testament_note' is supported.");
        }

        $note = TestamentResourceNote::find($contentId);
        if (!$note) {
            throw new \DomainException("Community resource note with ID {$contentId} was not found.");
        }

        if ((int) $note->user_id !== (int) $user->id) {
            throw new \DomainException("Ownership validation failed: Community resource note #{$contentId} does not belong to the penalized user.");
        }

        if ($note->status !== TestamentResourceNote::STATUS_REMOVED) {
            throw new \DomainException("Cannot restore community resource note #{$contentId}: Current status is '{$note->status}', expected 'removed'.");
        }

        return $note;
    }
}
