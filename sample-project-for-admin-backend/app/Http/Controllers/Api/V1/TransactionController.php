<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'earned', 'pending', 'withdrawn'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->transactions()->latest('created_at');

        match ($request->input('filter', 'all')) {
            'earned' => $query->where('direction', 'credit'),
            'pending' => $query->where('status', 'pending'),
            'withdrawn' => $query->where('type', TransactionType::WITHDRAWAL->value),
            default => null,
        };

        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('description', 'like', $like));
        }

        $paginator = $query->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, TransactionResource::class, 'Transactions retrieved successfully.');
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        $record = $request->user()->transactions()->whereKey($transaction)->firstOrFail();

        return ApiResponse::success('Transaction retrieved successfully.', [
            'transaction' => new TransactionResource($record),
        ]);
    }
}
