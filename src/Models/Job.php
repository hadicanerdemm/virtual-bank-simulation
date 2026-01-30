<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Job Model - Async Queue System
 */
class Job extends Model
{
    protected static string $table = 'jobs';
    
    protected array $fillable = [
        'type', 'payload', 'priority', 'attempts', 'max_attempts',
        'status', 'scheduled_at', 'started_at', 'completed_at', 'error_message'
    ];

    /**
     * Job types
     */
    public const TYPE_EMAIL = 'email';
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_REPORT = 'report';
    public const TYPE_CLEANUP = 'cleanup';

    /**
     * Dispatch a new job
     */
    public static function dispatch(string $type, array $payload, int $priority = 5, ?string $scheduledAt = null): self
    {
        return self::create([
            'type' => $type,
            'payload' => json_encode($payload),
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => 3,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt ?? date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Dispatch email job
     */
    public static function dispatchEmail(string $to, string $subject, string $body, array $data = []): self
    {
        return self::dispatch(self::TYPE_EMAIL, [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'data' => $data
        ], 8); // High priority for emails
    }

    /**
     * Dispatch webhook job
     */
    public static function dispatchWebhook(string $url, string $eventType, array $payload, string $secret): self
    {
        return self::dispatch(self::TYPE_WEBHOOK, [
            'url' => $url,
            'event_type' => $eventType,
            'payload' => $payload,
            'secret' => $secret
        ], 9); // Highest priority for webhooks
    }

    /**
     * Dispatch notification job
     */
    public static function dispatchNotification(string $userId, string $type, string $title, string $message): self
    {
        return self::dispatch(self::TYPE_NOTIFICATION, [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message
        ], 7);
    }

    /**
     * Get next pending job
     */
    public static function getNextPending(): ?self
    {
        $db = Database::getInstance();
        
        $row = $db->selectForUpdate(
            "SELECT * FROM jobs 
             WHERE status = 'pending' 
             AND scheduled_at <= NOW() 
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1"
        );
        
        if ($row === null) {
            return null;
        }
        
        return new self($row);
    }

    /**
     * Get pending jobs count
     */
    public static function pendingCount(): int
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'pending'");
    }

    /**
     * Mark job as processing
     */
    public function markProcessing(): bool
    {
        $this->status = 'processing';
        $this->started_at = date('Y-m-d H:i:s');
        $this->attempts = ($this->attempts ?? 0) + 1;
        return $this->save();
    }

    /**
     * Mark job as completed
     */
    public function markCompleted(): bool
    {
        $this->status = 'completed';
        $this->completed_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Mark job as failed
     */
    public function markFailed(string $errorMessage): bool
    {
        if ($this->attempts >= $this->max_attempts) {
            $this->status = 'failed';
        } else {
            $this->status = 'pending'; // Will retry
        }
        
        $this->error_message = $errorMessage;
        return $this->save();
    }

    /**
     * Get job payload
     */
    public function getPayload(): array
    {
        return json_decode($this->payload, true) ?? [];
    }

    /**
     * Check if job can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * Get failed jobs
     */
    public static function getFailedJobs(int $limit = 50): array
    {
        $db = Database::getInstance();
        
        $rows = $db->fetchAll(
            "SELECT * FROM jobs WHERE status = 'failed' ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Retry failed job
     */
    public function retry(): bool
    {
        $this->status = 'pending';
        $this->attempts = 0;
        $this->error_message = null;
        $this->scheduled_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Cleanup old completed jobs
     */
    public static function cleanup(int $daysOld = 7): int
    {
        $db = Database::getInstance();
        
        $stmt = $db->query(
            "DELETE FROM jobs WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOld]
        );
        
        return $stmt->rowCount();
    }

    /**
     * Get job statistics
     */
    public static function getStatistics(): array
    {
        $db = Database::getInstance();
        
        $stats = $db->fetchAll(
            "SELECT status, COUNT(*) as count FROM jobs GROUP BY status"
        );
        
        $result = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($stats as $stat) {
            $result[$stat['status']] = (int) $stat['count'];
        }
        
        return $result;
    }
}
