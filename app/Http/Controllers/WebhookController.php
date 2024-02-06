<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function insertResponse(Request $request)
    {
        $request->validate([
            'event' => 'required',
            'task_id' => 'required',
            'webhook_id' => 'required'
        ]);

        $x_signature = $request->header('X-Signature', -1);

        $event = $request->event;
        $history_items = $request->history_items;
        $task_id = $request->task_id;
        $webhook_id = $request->webhook_id;

        $secret = '2ITJCKC6YANX3MBQU3SOSKAJP04S8ZI7GUHKKKLBK9UUGM8QN6K8L35D1WIEHCEY';

        $body = $request->getContent();
        $header = json_encode($request->headers->all());

        $signature = hash_hmac('sha256', $body, $secret);

        DB::table('log')->insert([
            'x_signature' => $x_signature,
            'signature' => $signature,
            'event' => $event,
            'webhook_id' => $webhook_id,
            'body' => $body,
            'header' => $header,
            'create_date_time' => date('Y-m-d H:i:s')
        ]);

        if ($x_signature == $signature) {
            $response = DB::table('api_response')->insert([
                'event' => $event,
                'history_items' => json_encode($history_items),
                'task_id' => $task_id,
                'webhook_id' => $webhook_id
            ]);
    
            if ($response) {
                return response()->json(['status' => 'success'], 200);
            } else {
                return response()->json(['status' => 'failed'], 500);
            }
        } else {
            return response()->json(['status' => 'unauthorized'], 401);
        }
    }

    public function changeStatus(Request $request) 
    {
        $x_signature = $request->header('X-Signature', -1);
        $secret = 'SECRET_KEY';

        $body = $request->getContent();
        $header = json_encode($request->headers->all());
        $signature = hash_hmac('sha256', $body, $secret);

        $event = $request->event;
        $webhook_id = $request->webhook_id;

        $log_id = DB::table('log')->insertGetId([
            'x_signature' => $x_signature,
            'signature' => $signature,
            'event' => $event,
            'webhook_id' => $webhook_id,
            'body' => $body,
            'header' => $header,
            'create_date_time' => date('Y-m-d H:i:s')
        ]);

        if ($x_signature == $signature) {
            
            $task_id = $request->task_id;
            $parent_id = $request->parent_id;
            $item = $request->history_items;

            $status = $item->after->status;

            $status_char = '';
            switch (strtolower($status)) {
                case 'new': 
                    $status_char = 'N';
                    break;
                case 'sent to support':
                    $status_char = 'W';
                    break;
                case 'in progress':
                    $status_char = 'I';
                    break;
                case 'hold':
                    $status_char = 'H';
                    break;
                case 're open':
                    $status_char = 'R';
                    break;
                case 'solved':
                    $status_char = 'S';
                    break;
                case 'cancel':
                    $status_char = 'C';
                    break;
                case 'not done':
                    $status_char = 'X';
                    break;
            }

            if ($parent_id == '') {
                $result = DB::table('t_ticket')
                    ->where('clickup_task_id', $task_id)
                    ->update(['ticket_status', $status_char]);
            } else {
                $result = DB::table('t_ticket+wo')
                    ->where('clickup_subtask_id', $task_id)
                    ->update(['wo_status', $status_char]);
            }

            DB::table('t_webhook_log')
                ->where('id', $log_id)
                ->update(['status' => 'success', 'affected_row' => $result]);

            return response()->json(['status' => 'success'], 200);

        } else {
            DB::table('t_webhook_log')
                ->where('id', $log_id)
                ->update(['status' => 'unauthorized']);
                
            return response()->json(['status' => 'unauthorized'], 401);
        }
    }
}
