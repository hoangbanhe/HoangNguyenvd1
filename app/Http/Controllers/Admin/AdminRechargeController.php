<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentHistory;
use App\Models\RechargeHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminRechargeController extends Controller
{
    public function index()
    {
        $rechargeHistory = RechargeHistory::with('user:id,name')->orderByDesc('id')->paginate(20);
        $viewData        = [
            'rechargeHistory' => $rechargeHistory
        ];

        return view('admin.pages.recharge.index', $viewData);
    }

    public function indexPay(Request $request)
    {
        $paymentHistory = PaymentHistory::with('user:id,name')
            ->orderByDesc('id')->paginate(20);

        $viewData = [
            'paymentHistory' => $paymentHistory
        ];

        return view('admin.pages.recharge.pay', $viewData);
    }

    public function create()
    {
        $users          = User::all();
        $rechargeConfig = config('payment.method');

        $viewData = [
            'users'          => $users,
            'rechargeConfig' => $rechargeConfig
        ];

        return view('admin.pages.recharge.create', $viewData);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $data                = $request->except('_token');
            $data['created_at']  = Carbon::now();
            $data['total_money'] = $data['money'] + $data['discount'];
            $data['code']        = generateRandomString(15) . $data['user_id'];
            $rechargeHistory     = RechargeHistory::create($data);
            if ($rechargeHistory) {
                if ($rechargeHistory->status == RechargeHistory::STATUS_SUCCESS) {
                    // tiến hành cộng tiền cho người dùng
                    $user = User::find($rechargeHistory->user_id);
                    if (!$user) {
                        $rechargeHistory->note   = 'User không hợp lệ';
                        $rechargeHistory->status = RechargeHistory::STATUS_CANCEL;
                        $rechargeHistory->save();
                    } else {
                        Log::info("--- cộng tiền");
                        $user->account_balance += $rechargeHistory->total_money;
                        $user->save();
                    }
                }
            }

            DB::commit();
            return redirect()->route('get_admin.recharge.index');
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("---------------------  " . $exception->getMessage());
            return redirect()->back();
        }
    }

    public function edit($id)
    {
        $rechargeHistory = RechargeHistory::find($id);
        if ($rechargeHistory->status == RechargeHistory::STATUS_SUCCESS) {
            return redirect()->back();
        }

        $rechargeConfig  = config('payment.method');

        $viewData = [
            'rechargeHistory' => $rechargeHistory,
            'rechargeConfig'  => $rechargeConfig
        ];

        return view('admin.pages.recharge.update', $viewData);
    }

    public function update($id, Request $request) {
        try {
            DB::beginTransaction();
            $rechargeHistory = RechargeHistory::find($id);
            $rechargeHistory->total_money = $request->money + $request->discount;
            $rechargeHistory->updated_at = Carbon::now();
            $rechargeHistory->user_id = $request->user_id;
            $rechargeHistory->status = $request->status;
            $rechargeHistory->save();
            if ($rechargeHistory) {
                if ($rechargeHistory->status == RechargeHistory::STATUS_SUCCESS) {
                    // tiến hành cộng tiền cho người dùng
                    $user = User::find($rechargeHistory->user_id);
                    if (!$user) {
                        $rechargeHistory->note   = 'User không hợp lệ';
                        $rechargeHistory->status = RechargeHistory::STATUS_CANCEL;
                        $rechargeHistory->save();
                    } else {
                        Log::info("--- cộng tiền");
                        $user->account_balance += $rechargeHistory->total_money;
                        $user->save();
                    }
                }
            }

            DB::commit();
            return redirect()->route('get_admin.recharge.index');
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("---------------------  " . $exception->getMessage());
            return redirect()->back();
        }
    }
}
