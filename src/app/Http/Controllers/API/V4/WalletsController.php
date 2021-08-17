<?php

namespace App\Http\Controllers\API\V4;

use App\Transaction;
use App\Wallet;
use App\Http\Controllers\Controller;
use App\Providers\PaymentProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
     * Return data of the specified wallet.
     *
     * @param string $id A wallet identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function show($id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        // Only owner (or admin) has access to the wallet
        if (!$this->guard()->user()->canRead($wallet)) {
            return $this->errorResponse(403);
        }

        $result = $wallet->toArray();

        $provider = \App\Providers\PaymentProvider::factory($wallet);

        $result['provider'] = $provider->name();
        $result['notice'] = $this->getWalletNotice($wallet);

        return response()->json($result);
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
     * @param string                   $id
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
     * Download a receipt in pdf format.
     *
     * @param string $id      Wallet identifier
     * @param string $receipt Receipt identifier (YYYY-MM)
     *
     * @return \Illuminate\Http\Response
     */
    public function receiptDownload($id, $receipt)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            abort(404);
        }

        // Only owner (or admin) has access to the wallet
        if (!$this->guard()->user()->canRead($wallet)) {
            abort(403);
        }

        list ($year, $month) = explode('-', $receipt);

        if (empty($year) || empty($month) || $year < 2000 || $month < 1 || $month > 12) {
            abort(404);
        }

        if ($receipt >= date('Y-m')) {
            abort(404);
        }

        $params = [
            'id' => sprintf('%04d-%02d', $year, $month),
            'site' => \config('app.name')
        ];

        $filename = \trans('documents.receipt-filename', $params);

        $receipt = new \App\Documents\Receipt($wallet, (int) $year, (int) $month);

        $content = $receipt->pdfOutput();

        return response($content)
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>  'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
            ]);
    }

    /**
     * Fetch wallet receipts list.
     *
     * @param string $id Wallet identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function receipts($id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        // Only owner (or admin) has access to the wallet
        if (!$this->guard()->user()->canRead($wallet)) {
            return $this->errorResponse(403);
        }

        $result = $wallet->payments()
            ->selectRaw('distinct date_format(updated_at, "%Y-%m") as ident')
            ->where('status', PaymentProvider::STATUS_PAID)
            ->where('amount', '<>', 0)
            ->orderBy('ident', 'desc')
            ->get()
            ->whereNotIn('ident', [date('Y-m')]) // exclude current month
            ->pluck('ident');

        return response()->json([
                'status' => 'success',
                'list' => $result,
                'count' => count($result),
                'hasMore' => false,
                'page' => 1,
        ]);
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

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        // Only owner (or admin) has access to the wallet
        if (!$this->guard()->user()->canRead($wallet)) {
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

        $result = $result->map(function ($item) use ($isAdmin, $wallet) {
            $entry = [
                'id' => $item->id,
                'createdAt' => $item->created_at->format('Y-m-d H:i'),
                'type' => $item->type,
                'description' => $item->shortDescription(),
                'amount' => $item->amount,
                'currency' => $wallet->currency,
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

    /**
     * Returns human readable notice about the wallet state.
     *
     * @param \App\Wallet $wallet The wallet
     */
    protected function getWalletNotice(Wallet $wallet): ?string
    {
        // there is no credit
        if ($wallet->balance < 0) {
            return \trans('app.wallet-notice-nocredit');
        }

        // the discount is 100%, no credit is needed
        if ($wallet->discount && $wallet->discount->discount == 100) {
            return null;
        }

        // the owner was created less than a month ago
        if ($wallet->owner->created_at > Carbon::now()->subMonthsWithoutOverflow(1)) {
            // but more than two weeks ago, notice of trial ending
            if ($wallet->owner->created_at <= Carbon::now()->subWeeks(2)) {
                return \trans('app.wallet-notice-trial-end');
            }

            return \trans('app.wallet-notice-trial');
        }

        if ($until = $wallet->balanceLastsUntil()) {
            if ($until->isToday()) {
                return \trans('app.wallet-notice-today');
            }

            // Once in a while we got e.g. "3 weeks" instead of expected "4 weeks".
            // It's because $until uses full seconds, but $now is more precise.
            // We make sure both have the same time set.
            $now = Carbon::now()->setTimeFrom($until);

            $diffOptions = [
                'syntax' => Carbon::DIFF_ABSOLUTE,
                'parts' => 1,
            ];

            if ($now->diff($until)->days > 31) {
                $diffOptions['parts'] = 2;
            }

            $params = [
                'date' => $until->toDateString(),
                'days' => $now->diffForHumans($until, $diffOptions),
            ];

            return \trans('app.wallet-notice-date', $params);
        }

        return null;
    }
}
