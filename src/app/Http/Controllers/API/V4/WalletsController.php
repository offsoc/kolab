<?php

namespace App\Http\Controllers\API\V4;

use App\Transaction;
use App\Wallet;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API\WalletsController
 */
class WalletsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return $this->errorResponse(404);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return $this->errorResponse(404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Fetch wallet transactions.
     *
     * @param string $id Wallet identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactions($id)
    {
        $wallet = Wallet::find($id);

        // Only owner (or admin) has access to the wallet
        if (!Auth::guard()->user()->canRead($wallet)) {
            return $this->errorResponse(403);
        }

        $pageSize = 10;
        $page = intval(request()->input('page')) ?: 1;
        $hasMore = false;
        $isAdmin = $this instanceof Admin\WalletsController;

        if ($transaction = request()->input('transaction')) {
            // Get sub-transactions for the specified transaction ID, first
            // check access rights to the transaction's wallet

            $transaction = $wallet->transactions()->where('id', $transaction)->first();

            if (!$transaction) {
                return $this->errorResponse(404);
            }

            $result = Transaction::where('transaction_id', $transaction->id)->get();
        } else {
            // Get main transactions (paged)
            $result = $wallet->transactions()
                // FIXME: Do we know which (type of) transaction has sub-transactions
                //        without the sub-query?
                ->selectRaw("*, (SELECT count(*) FROM transactions sub "
                    . "WHERE sub.transaction_id = transactions.id) AS cnt")
                ->whereNull('transaction_id')
                ->latest()
                ->limit($pageSize + 1)
                ->offset($pageSize * ($page - 1))
                ->get();

            if (count($result) > $pageSize) {
                $result->pop();
                $hasMore = true;
            }
        }

        $result = $result->map(function ($item) use ($isAdmin) {
            $amount = $item->amount;

            if (in_array($item->type, [Transaction::WALLET_PENALTY, Transaction::WALLET_DEBIT])) {
                $amount *= -1;
            }

            $entry = [
                'id' => $item->id,
                'createdAt' => $item->created_at->format('Y-m-d H:i'),
                'type' => $item->type,
                'description' => $item->shortDescription(),
                'amount' => $amount,
                'hasDetails' => !empty($item->cnt),
            ];

            if ($isAdmin && $item->user_email) {
                $entry['user'] = $item->user_email;
            }

            return $entry;
        });

        return response()->json([
                'status' => 'success',
                'list' => $result,
                'count' => count($result),
                'hasMore' => $hasMore,
                'page' => $page,
        ]);
    }
}
