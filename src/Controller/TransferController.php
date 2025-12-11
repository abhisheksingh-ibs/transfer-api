<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\TransferService;
use Symfony\Component\HttpFoundation\Response;


#[Route('/api/v1')]
class TransferController extends AbstractController
{
private TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    #[Route('/transfers', name: 'api_transfers_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        // Basic validation
        $from = (int)($data['from_account_id'] ?? 0);
        $to = (int)($data['to_account_id'] ?? 0);
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? 'INR';

        if (!$from || !$to || !$amount) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        // amount as decimal string or numeric — convert to cents
        // Expect amount in rupees as decimal string: "123.45" or numeric 123.45
        $amountCents = $this->toCents($amount);

        try {
            $transfer = $this->transferService->transfer($idempotencyKey, $from, $to, $amountCents, $currency, $data['metadata'] ?? []);
            // map transfer to response
            return $this->json([
                'id' => $transfer->getId(),
                'from_account_id' => $transfer->getFromAccountId(),
                'to_account_id' => $transfer->getToAccountId(),
                'amount' => number_format($transfer->getAmount() / 100, 2, '.', ''),
                'currency' => $transfer->getCurrency(),
                'status' => $transfer->getStatus(),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function toCents($amount): int
    {
        // accept string or float; handle safely
        if (is_string($amount)) {
            // normalize, remove commas
            $amount = str_replace(',', '', $amount);
        }
        // use BCMath if available for precise multiplication
        $cents = (int) round((float)$amount * 100);
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Invalid amount');
        }
        return $cents;
    }
}
