<?php

namespace App\Http\Controllers\ApiKey;

use App\Http\Controllers\APIController;
use App\Models\User\KeyRequest as UserKeyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KeysController extends APIController
{
    public function request(Request $request)
    {
        if ($request->method() !== 'POST') {
            return view('api_key.request_key');
        }
        $rules = [
          'name' => 'required|string',
          'application_name' => 'required|string',
          'application_url' => 'required|string',
          'email' => 'required|email',
          'description' => 'required|string',
          'question' => 'string',
          'agreement' => 'required'
        ];
  
        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $key_request = UserKeyRequest::make(request()->all());
        $key_request->generateKey();
        $key_request->save();
        return redirect()->to(route('api_key.requested'));
    }
    public function requested()
    {
        return view('api_key.requested_key');
    }
}
