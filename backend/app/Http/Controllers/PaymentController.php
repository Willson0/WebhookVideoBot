<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YooKassa\Client;

class PaymentController extends Controller
{
    public function buy (Request $request) {
        $user = User::where("id", $request["user_id"])->firstOrFail();
        if (!$user->email) abort (409, "Email not found");
        if (!$user->payment_method_id) abort (409, "Payment method not found");

        $client = new Client();
        $client->setAuth(env("SHOP_ID"), env("YOOKASSA_API_KEY"));

        $botname = env("BOT_NAME");
        $response = $client->createPayment(
            [
                'amount' => [
                    'value' =>  number_format(max(1, $request->rub_summ), 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'capture' => true,
                'payment_method_id' => $user->payment_method_id,
                'description' => "Подписка на $request->days дней по тарифу \"$request->sub\" в Телеграмм - сервисе @$botname",
                'receipt' => [
                    'customer' => [
                        'email' => $user->email,
                    ],
                    'items' => [
                        [
                            'description' =>  "Подписка на $request->days дней по тарифу \"$request->sub\" в Телеграмм - сервисе @$botname",
                            'quantity' => '1.00',
                            'amount' => [
                                'value' => number_format(max(1, $request->rub_summ), 2, '.', ''),
                                'currency' => 'RUB',
                            ],
                            'vat_code' => 2,
                            'payment_mode' => 'full_payment',
                            'payment_subject' => 'commodity',
                        ],
                    ],
                ],
            ],
            $user->id . "_" . $request->sub . "_" . time()
        );
        $paymentID = $response->id;

        $payment = Payment::create([
            "user_id" => $user->id,
            "is_autopayment" => 0,
            "payment_id" => $paymentID,
            "is_bought" => false,
            "rub_summ" => $request->rub_summ,
            "summ" => $request->rub_summ,
            "days" => $request->days,
            "sub" => $request->sub,
        ]);

        return response()->json(["ok" => true]);
    }

    public function webhook (Request $request)
    {
        Log::info($request);
        $payment = Payment::where("payment_id", $request->object["id"] ?? $request["id"])->first();

        if ($payment->is_bought) return response('ok', 200);
        if ($request->event === "payment.succeeded" || $request->status === "succeeded") {
            $user = User::find($payment->user_id);
            if (!$user) {
                Log::error("User not found $payment->id: $payment->user_id");
                return response('ok', 200);
            }
            if ($request->object["payment_method"]["saved"] === true ?? null) $user->payment_method_id = $request->object["payment_method"]["id"];

            $user->paid_money += $payment->rub_summ;
            if ($payment->package === "trial") {
                $user->tried_trial = True;
                $user->recurrent_package = "5";
            }
            else $user->recurrent_package = $payment->package;

            $next_charge = (new DateTime())->modify('+' . $payment->days . ' days')->getTimestamp();
            $user->recurrent_time = $next_charge;

            $user->save();
            $payment->is_bought = true;

            $botToken = env('TELEGRAM_BOT_TOKEN');
            $text = "<b>🎉 Классно, оплата прошла успешно!</b>\n\n❤️ Спасибо, что выбрали нас!\nЕсли будут вопросы — всегда здесь, чтобы помочь!";

            $data = [
                'chat_id' => $user->id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($user->referal_user_from != 0) {
                $referal = User::find($user->referal_user_from);
                if ($referal) {
                    $plus = round($payment->rub_summ * 0.3);
                    $referal->referral_balance += $plus;
                    $referal->save();
                }
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            Http::post($url, $data);
        } else if (($request->event === "payment.canceled" || $request->status === "canceled") && $payment->is_autopayment) {
            $user = User::find($payment->user_id);
            if (time() >= $user->recurrent_time) $user->recurrent_package = "0";
            $user->save();
        }

        $payment->save();
        return response()->json();
    }
}
