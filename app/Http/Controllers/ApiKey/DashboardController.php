<?php

namespace App\Http\Controllers\ApiKey;

use App\Http\Controllers\APIController;
use App\Mail\EmailKeyRequest;
use App\Models\User\User;
use App\Models\User\Key;
use App\Models\User\KeyRequest;
use App\Models\User\AccessGroupKey;

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
        $page = checkParam('page');
        $state_names = [0 => '', 1 => 'Requested', 2 => 'Approved', 3 => 'Denied'];
        $options = [
          ['name' => $state_names[0], 'value' => 0, 'selected' => $state == 0],
          ['name' => $state_names[1], 'value' => 1, 'selected' => $state == 1],
          ['name' => $state_names[2], 'value' => 2, 'selected' => $state == 2],
          ['name' => $state_names[3], 'value' => 3, 'selected' => $state == 3]
        ];

        $key_requests = KeyRequest::select('*')
        ->when($state, function ($query, $state) {
            $query->where('state', $state);
        })
        ->when($search, function ($query, $search) {
            $query
            ->where(function ($query) use ($search) {
                $query
              ->where('name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('temporary_key', 'LIKE', "%{$search}%");
            });
        })
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return view(
        'api_key.dashboard',
        compact('user', 'key_requests', 'search', 'options', 'state', 'state_names')
      );
    }

    public function sendEmail()
    {
        if (!$this->isAdmin()) {
            return $this->setStatusCode(403)->replyWithError('Unauthorized');
        }

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

    public function saveNote()
    {
        if (!$this->isAdmin()) {
            return $this->setStatusCode(403)->replyWithError('Unauthorized');
        }

        $rules = [
            'id' => 'required',
            'note' => 'required|string'
        ];
        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {
            $error_message = '';
            foreach ($validator->errors()->all() as $error) {
                $error_message .= $error . "\n";
            }
            return $this->setStatusCode(422)->replyWithError($error_message);
        } else {
            $id = checkParam('id');
            $note = checkParam('note');
            try {
                $key_request = KeyRequest::whereId($id)->first();
                $key_request->notes = $note;
                $key_request->save();
                return $this->reply($key_request);
            } catch (Exception $e) {
                return $this->setStatusCode(500)->replyWithError($e->getMessage());
            }
        }
    }

    private function validateApiKeyRequest($rules)
    {
      if (!$this->isAdmin()) {
          return $this->setStatusCode(403)->replyWithError('Unauthorized');
      }

      $validator = Validator::make(request()->all(), $rules);
      if ($validator->fails()) {
          $error_message = '';
          foreach ($validator->errors()->all() as $error) {
              $error_message .= $error . "\n";
          }
          return $this->setStatusCode(422)->replyWithError($error_message);
      } else {
        return true;
      }
    }

    public function changeApiKeyState()
    {
        $rules = [
          'key_request_id' => 'required',
          'state' => 'required'
        ];

        $validator = $this->validateApiKeyRequest($rules);
        if ($validator) {
            $key_request_id = checkParam('key_request_id');
            $key_state = checkParam('state');

            $key_request = KeyRequest::whereId($key_request_id)->first();
            $key_request->state = $key_state;
            $key_request->save();

            return $key_request;
        }
    }

    public function approveApiKey()
    {
        $rules = [
            'key_request_id' => 'required',
            'email' => 'required|email'
        ];

        $validator = $this->validateApiKeyRequest($rules);
        if ($validator) {
            $key_request_id = checkParam('key_request_id');
            $description = checkParam('description');
            $email = checkParam('email');
            $user_name = checkParam('name');
            $key = checkParam('key');

            $key_request = KeyRequest::whereId($key_request_id)->first();
            $key_request->state = 2;
            $key_request->save();

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name'  => $user_name]
            );

            $use_key_description = trim($description . ' ' . $key_request->application_url);
            $created_key = Key::firstOrCreate(
                [
                    'user_id'     => $user->id,
                    'key'         => $key
                ],
                [
                    'name'        => $key_request->application_name,
                    'description' => $use_key_description
                ]
            );

            $key_access_groups = explode(',', config('settings.apiKeyAccessGroups'));
            foreach ($key_access_groups as $access_group) {
                AccessGroupKey::firstOrCreate([
                    'key_id'          => $created_key->id,
                    'access_group_id' => $access_group,
                ]);
            }

            return $created_key;
        }
    }

    public function deleteApiKey()
    {
        $rules = [
            'key_request_id' => 'required',
            'email' => 'required|email'
        ];

        $validator = $this->validateApiKeyRequest($rules);
        if ($validator) {
            $key_request_id = checkParam('key_request_id');
            $key = checkParam('key');

            $key_request = KeyRequest::whereId($key_request_id)->first();
            $key_request->state = 3;
            $key_request->save();

            $target_key = Key::where('key', $key)->first();
            AccessGroupKey::where('key_id', $target_key->id)->delete();
            $deleted_key = $target_key->delete();

            return $target_key;
        }
    }

    public function replyWithError($message, $action = null)
    {
        return response()->json(
            [
                'error' => [
                    'message' => $message,
                    'status_code' => $this->statusCode
                ]
            ],
            $this->statusCode
        );
    }

    private function isAdmin()
    {
        $user = Auth::user() ?? $this->user;
        return $user->roles->where('slug', 'admin')->first();
    }
}
