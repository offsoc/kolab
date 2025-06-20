<?php

namespace App\Http\Controllers\API\V4;

use App\Documents\Receipt;
use App\Http\Controllers\ResourceController;
use App\Payment;
use App\Providers\PaymentProvider;
use App\ReferralCode;
use App\ReferralProgram;
use App\Transaction;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * API\WalletsController
 */
class WalletsController extends ResourceController
{
    /**
     * Return data of the specified wallet.
     *
     * @param string $id A wallet identifier
     *
     * @return JsonResponse The response
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

        $provider = PaymentProvider::factory($wallet);

        $result['provider'] = $provider->name();
        $result['notice'] = $this->getWalletNotice($wallet);

        return response()->json($result);
    }

    /**
     * Download a receipt in pdf format.
     *
     * @param string $id      Wallet identifier
     * @param string $receipt Receipt identifier (YYYY-MM)
     *
     * @return Response
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

        [$year, $month] = explode('-', $receipt);

        if (empty($year) || empty($month) || $year < 2000 || $month < 1 || $month > 12) {
            abort(404);
        }

        if ($receipt >= date('Y-m')) {
            abort(404);
        }

        $params = [
            'id' => sprintf('%04d-%02d', $year, $month),
            'site' => \config('app.name'),
        ];

        $filename = self::trans('documents.receipt-filename', $params) . '.pdf';

        $receipt = new Receipt($wallet, (int) $year, (int) $month);

        $content = $receipt->pdfOutput();

        return response($content)
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
            ]);
    }

    /**
     * Fetch wallet receipts list.
     *
     * @param string $id Wallet identifier
     *
     * @return JsonResponse
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

        $pageSize = 10;
        $page = (int) (request()->input('page')) ?: 1;
        $hasMore = false;

        $result = $wallet->payments()
            ->selectRaw('date_format(updated_at, "%Y-%m") as ident, sum(amount) as total')
            ->where('status', Payment::STATUS_PAID)
            ->where('amount', '<>', 0)
            ->orderBy('ident', 'desc')
            ->groupBy('ident')
            ->havingRaw('ident <> ?', [date('Y-m')]) // exclude current month
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        // @phpstan-ignore argument.unresolvableType
        $result = $result->map(static function ($item) use ($wallet) {
            $entry = [
                'period' => $item->ident, // @phpstan-ignore-line
                'amount' => $item->total, // @phpstan-ignore-line
                'currency' => $wallet->currency,
            ];
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
     * Fetch active referral programs list.
     *
     * @param string $id Wallet identifier
     *
     * @return JsonResponse
     */
    public function referralPrograms($id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        // Only owner (or admin) has access to the wallet
        if (!$this->guard()->user()->canRead($wallet)) {
            return $this->errorResponse(403);
        }

        $raw_count = DB::raw('(select count(*) from referrals where referrals.code = code) as refcount');
        $codes = ReferralCode::where('user_id', $wallet->user_id)->select('code', 'program_id', $raw_count);

        $result = ReferralProgram::withObjectTenantContext($wallet->owner)
            ->where('active', true)
            ->leftJoinSub($codes, 'codes', static function (JoinClause $join) {
                $join->on('referral_programs.id', '=', 'codes.program_id');
            })
            ->select('id', 'name', 'description', 'tenant_id', 'codes.code', 'codes.refcount')
            ->get()
            ->map(static function ($program) use ($wallet) {
                if (empty($program->code)) {
                    // Register/Generate a code for the user if it does not exist yet
                    $code = $program->codes()->create(['user_id' => $wallet->user_id]);

                    $program->code = $code->code;
                }

                $code = new ReferralCode();
                $code->code = $program->code;
                $code->program = $program; // @phpstan-ignore-line

                $entry = [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'refcount' => $program->refcount ?? 0,
                    'url' => $code->signupUrl(),
                    'qrCode' => $code->qrCode(true),
                ];
                return $entry;
            });

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
     * @return JsonResponse
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
        $page = (int) (request()->input('page')) ?: 1;
        $hasMore = false;
        $isAdmin = $this instanceof Admin\WalletsController;

        if ($transaction = request()->input('transaction')) {
            // Get sub-transactions for the specified transaction ID, first
            // check access rights to the transaction's wallet

            /** @var ?Transaction $transaction */
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

        $result = $result->map(static function ($item) use ($isAdmin, $wallet) {
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
     * @param Wallet $wallet The wallet
     */
    protected function getWalletNotice(Wallet $wallet): ?string
    {
        // there is no credit
        if ($wallet->balance < 0) {
            return self::trans('app.wallet-notice-nocredit');
        }

        // the discount is 100%, no credit is needed
        if ($wallet->discount && $wallet->discount->discount == 100) {
            return null;
        }

        $plan = $wallet->plan();
        $freeMonths = $plan ? $plan->free_months : 0;
        $trialEnd = $freeMonths ? $wallet->owner->created_at->copy()->addMonthsWithoutOverflow($freeMonths) : null;

        // the owner is still in the trial period
        if ($trialEnd && $trialEnd > Carbon::now()) {
            // notice of trial ending if less than 2 weeks left
            if ($trialEnd < Carbon::now()->addWeeks(2)) {
                return self::trans('app.wallet-notice-trial-end');
            }

            return self::trans('app.wallet-notice-trial');
        }

        if ($until = $wallet->balanceLastsUntil()) {
            if ($until->isToday()) {
                return self::trans('app.wallet-notice-today');
            }

            // Once in a while we got e.g. "3 weeks" instead of expected "4 weeks".
            // It's because $until uses full seconds, but $now is more precise.
            // We make sure both have the same time set.
            $now = Carbon::now()->setTimeFrom($until);

            $diffOptions = [
                'syntax' => Carbon::DIFF_ABSOLUTE,
                'parts' => 1,
            ];

            if ($now->diffAsDateInterval($until)->days > 31) {
                $diffOptions['parts'] = 2;
            }

            $params = [
                'date' => $until->toDateString(),
                'days' => $now->diffForHumans($until, $diffOptions),
            ];

            return self::trans('app.wallet-notice-date', $params);
        }

        return null;
    }
}
