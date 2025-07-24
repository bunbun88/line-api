<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class LineWebhookController extends Controller
{
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token');
    }

    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('LINE Raw Input: ', $data);

        if (!empty($data['events'])) {
            foreach ($data['events'] as $event) {
                if (
                    isset($event['type'], $event['message']['type']) &&
                    $event['type'] === 'message' &&
                    $event['message']['type'] === 'text'
                ) {
                    $replyText = $this->generateReply($event['message']['text']);
                    $this->replyToLine($event['replyToken'], $replyText);
                }
            }
        }

        return response('OK', 200);
    }

    private function generateReply($text)
    {
        if ($text === 'BMI判定') {
            return "身長と体重を送ってください（例：身長170 体重65）";
        } elseif ($text === '尿酸値判定') {
            return "尿酸値を送ってください（例：尿酸値6.2）";
        } elseif ($text === '血糖値判定') {
            return "血糖値を送ってください（例：血糖値95）";
        }

        // BMI
        if (preg_match('/身長\s*(\d{3})\D+体重\s*(\d{2,3})/u', $text, $m) ||
            preg_match('/体重\s*(\d{2,3})\D+身長\s*(\d{3})/u', $text, $r)) {

            $height = $m[1] ?? $r[2];
            $weight = $m[2] ?? $r[1];
            $bmi = round($weight / pow($height / 100, 2), 2);
            $status = match (true) {
                $bmi < 18.5 => '低体重（やせ）',
                $bmi < 25 => '普通体重',
                $bmi < 30 => '肥満（1度）',
                default => '肥満（2度以上）',
            };

            // 条件付きでPDF教材を案内
            if ($bmi >= 25) {
                $link = $this->generatePdfLink('rails.pdf');
                return "あなたのBMIは {$bmi} で、{$status} です。\n📄 以下の教材をお読みください（10分間有効）：\n{$link}";
            }


            return "あなたのBMIは {$bmi} で、{$status} です。";
        }

        // 尿酸値
        if (preg_match('/尿酸値\s*(\d+(\.\d+)?)/u', $text, $m)) {
            $uric = floatval($m[1]);
            $status = match (true) {
                $uric < 3.0 => '低すぎる可能性があります',
                $uric <= 7.0 => '正常範囲です',
                default => '高尿酸血症の可能性があります（7.0超）',
            };
            return "あなたの尿酸値は {$uric} で、{$status}。";
        }

        // 血糖値
        if (preg_match('/血糖値\s*(\d+)/u', $text, $m)) {
            $glucose = intval($m[1]);
            $status = match (true) {
                $glucose < 70 => '低血糖の可能性があります',
                $glucose <= 109 => '正常範囲です',
                $glucose <= 125 => '境界型（糖尿病予備軍）の可能性があります',
                default => '糖尿病の可能性があります（126以上）',
            };
            return "あなたの血糖値は {$glucose} で、{$status}。";
        }

        return "「身長1700 体重65」や「尿酸値6.2」「血糖値95」のように送ってください。";
    }

    private function replyToLine($replyToken, $replyText)
    {
        $response = [
            'replyToken' => $replyToken,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $replyText,
                ],
            ],
        ];

        $ch = curl_init('https://api.line.me/v2/bot/message/reply');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($response),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        Log::info('LINE Response: ' . $result);
    }

    public function generatePdfLink($filename)
    {
        return URL::temporarySignedRoute(
            'secure.pdf',
            now()->addMinutes(10),
            ['filename' => $filename]
        );
    }
}
