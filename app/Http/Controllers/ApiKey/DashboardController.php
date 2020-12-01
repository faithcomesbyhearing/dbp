<?php

namespace App\Http\Controllers\ApiKey;

use App\Http\Controllers\APIController;
use App\Mail\EmailKeyRequest;
use App\Models\User\KeyRequest;
use Illuminate\Support\Facades\Validator;
use Auth;
use Exception;

class DashboardController extends APIController
{
    /**
     * Create a new controller instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('auth');
    }

    public function home()
    {
        $user = Auth::user() ?? $this->user;
        $search = checkParam('search');
        $state = checkParam('state');
        $options = [
            ['name' => 'Requested', 'value' => 1, 'selected' => $state == 1],
            ['name' => 'Approved', 'value' => 2, 'selected' => $state == 2],
            ['name' => 'Denied', 'value' => 3, 'selected' => $state == 3]
        ];

        $key_requests = KeyRequest::select('*')
            ->when($state, function ($query, $state) {
                $query->where('state', $state);
            })
            ->when($search, function ($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('temporary_key', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')->paginate(1);
        return view('api_key.dashboard', compact('user', 'key_requests', 'search', 'options', 'state'));
    }

    public function sendEmail()
    {
        $rules = [
            'id' => 'required',
            'email' => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string'
        ];
        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {
            $error_message = '';
            foreach ($validator->errors()->all() as $error) {
                $error_message .= $error . "\n";
            }
            return $this->setStatusCode(422)->replyWithError($error_message);
        } else {
            $email = checkParam('email');
            $subject = checkParam('subject');
            $message = checkParam('message');
            try {
                \Mail::to($email)->send(new EmailKeyRequest($subject, $message));
                return $this->reply('ok');
            } catch (Exception $e) {
                return $this->setStatusCode(500)->replyWithError($e->getMessage());
            }
        }
    }

    public function replyWithError($message, $action = null)
    {
        return response()->json(['error' => [
            'message'     => $message,
            'status_code' => $this->statusCode,
        ]], $this->statusCode);
    }
}
