<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
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
            if ($payment->package === "trial") $user->tried_trial = True;

            $user->save();
            $payment->is_bought = true;

            $botToken = env('TELEGRAM_BOT_TOKEN');
            if ($payment->sub === "tokens") $text = "✅ Оплата успешно прошла! На ваш аккаунт добавлено {$payment->days} токенов";
            else $text = "<b>🎉 Классно, оплата прошла успешно!</b>\n\n❤️ Спасибо, что выбрали нас!\nЕсли будут вопросы — всегда здесь, чтобы помочь!";

            $data = [
                'chat_id' => $user->id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            Http::post($url, $data);
        } else if (($request->event === "payment.canceled" || $request->status === "canceled") && $payment->is_autopayment) {
            $user = User::find($payment->user_id);

            $try = intval(explode('_', $user->orig_tariff)[1]);

            if (intval($payment->days) === 30) {
                $PRICES = [
                    7 => 2,
//                    7 => 279,
                    30 => 569,
                    90 => 1499,
                    180 => 2899
                ];

                $this->buy(new Request([
                    "user_id" => $user->id,
                    "sub" => "pro",
                    "days" => 7,
                    "rub_summ" => $PRICES[7],
                    "summ" => $PRICES[7],
                ]));
            }

            if ($try === 4) {
                $user->orig_tariff = "free";
                $user->tariff_time = 0;
                $user->save();
                return response("ok", 200);
            }

            $user->orig_tariff = $payment->sub . "_" . ($try + 1);

            $SPISANIE_TIMES_OSN = [
                0 => 0,
                1 => 86400,   # через день
                2 => 86400,   # через 2 дня
                3 => 432000,  # через неделю
                4 => 2116800  # через месяц
            ];
            $tryIndex = $try + 1;
            $seconds = isset($SPISANIE_TIMES_OSN[$tryIndex]) ? (int)$SPISANIE_TIMES_OSN[$tryIndex] : 0;
            $user->tariff_time = Carbon::now()->addSeconds($seconds)->timestamp;

            $user->start_sub_time = 0;

            $user->save();
        }

        $payment->save();
        return response()->json();
    }
}
