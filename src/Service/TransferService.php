<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Entity\IdempotencyKey;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Psr\Log\LoggerInterface;
use Predis\Client as RedisClient;

class TransferService
{
private EntityManagerInterface $em;
private RedisClient $redis;
private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, RedisClient $redis, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * Perform a transfer. Uses DB row-level locking to avoid race conditions.
     *
     * @param string $idempotencyKey optional client idempotency token
     * @param int $fromAccountId
     * @param int $toAccountId
     * @param int $amountCents positive integer
     * @param string $currency
     * @param array $metadata
     * @return Transfer
     * @throws \Exception
     */
    public function transfer(string $idempotencyKey = null, int $fromAccountId, int $toAccountId, int $amountCents, string $currency = 'INR', array $metadata = []): Transfer
    {
        if ($amountCents <= 0) {
            throw new BadRequestHttpException('Amount must be positive');
        }
        if ($fromAccountId === $toAccountId) {
            throw new BadRequestHttpException('from and to must differ');
        }

        // Idempotency: check table first
        if ($idempotencyKey) {
            $existing = $this->em->getRepository(IdempotencyKey::class)
                ->findOneBy(['idempotencyKey' => $idempotencyKey]);

            if ($existing && $existing->getStatus() === 'completed') {
                $transfer = $this->em->getRepository(Transfer::class)->find($existing->getTransferId());
                if ($transfer) {
                    return $transfer;
                }
                // If record inconsistent, continue to process but log
                $this->logger->warning('Idempotency key points to missing transfer', ['key' => $idempotencyKey]);
            } elseif ($existing && $existing->getStatus() === 'in_progress') {
                throw new ConflictHttpException('Request is already in progress'); // client should retry later
            } elseif (!$existing) {
                $ik = new IdempotencyKey();
                $ik->setIdempotencyKey($idempotencyKey);
                $ik->setStatus('in_progress');
                $this->em->persist($ik);
                $this->em->flush(); // persist the in_progress row to prevent races
            }
        }

        // Use DB transaction and SELECT ... FOR UPDATE on both accounts.
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            // Lock ordering: always lock by account id order to avoid deadlocks
            [$firstId, $secondId] = $fromAccountId < $toAccountId ? [$fromAccountId, $toAccountId] : [$toAccountId, $fromAccountId];

            // fetch with FOR UPDATE
            $firstRow = $conn->fetchAssociative(
                'SELECT * FROM accounts WHERE id = ? FOR UPDATE',
                [$firstId]
            );
            $secondRow = $conn->fetchAssociative(
                'SELECT * FROM accounts WHERE id = ? FOR UPDATE',
                [$secondId]
            );

            if (!$firstRow || !$secondRow) {
                throw new BadRequestHttpException('Account not found');
            }

            // Map which is from/to
            $fromRow = ($firstRow['id'] == $fromAccountId) ? $firstRow : $secondRow;
            $toRow = ($fromRow['id'] == $firstRow['id']) ? $secondRow : $firstRow;

            if ($fromRow['status'] !== 'active' || $toRow['status'] !== 'active') {
                throw new BadRequestHttpException('Account not active');
            }

            if ($fromRow['currency'] !== $currency || $toRow['currency'] !== $currency) {
                throw new BadRequestHttpException('Currency mismatch');
            }

            // Check sufficient funds
            if ((int)$fromRow['balance'] < $amountCents) {
                throw new BadRequestHttpException('Insufficient balance');
            }

            // Update balances in DB
            $newFrom = (int)$fromRow['balance'] - $amountCents;
            $newTo = (int)$toRow['balance'] + $amountCents;

            $conn->update('accounts', ['balance' => $newFrom], ['id' => $fromAccountId]);
            $conn->update('accounts', ['balance' => $newTo], ['id' => $toAccountId]);

            // Create transfer row
            $this->em->getConnection()->insert('transfers', [
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $amountCents,
                'currency' => $currency,
                'status' => 'completed',
                'metadata' => json_encode($metadata),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'completed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
            $transferId = (int)$conn->lastInsertId();

            // mark idempotency completed
            if ($idempotencyKey) {
                $conn->update('idempotency_keys', [
                    'status' => 'completed',
                    'transfer_id' => $transferId,
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ], ['idempotency_key' => $idempotencyKey]);
            }

            $conn->commit();

            // load transfer entity or return basic info
            $transfer = $this->em->getRepository(Transfer::class)->find($transferId);
            return $transfer;
        } catch (\Exception $e) {
            $conn->rollBack();
            // mark idempotency failed if present
            if ($idempotencyKey) {
                try {
                    $this->em->getConnection()->update('idempotency_keys', [
                        'status' => 'failed', 'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')
                    ], ['idempotency_key' => $idempotencyKey]);
                } catch (\Exception $inner) {
                    $this->logger->error('Failed to mark idempotency failed', ['err' => $inner->getMessage()]);
                }
            }
            $this->logger->error('Transfer failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
