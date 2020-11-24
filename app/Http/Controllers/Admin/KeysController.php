<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\APIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KeysController extends APIController
{
    public function request(Request $request)
    {
        if ($request->method() !== 'POST') {
            return view('v4.admin.request_key');
        }
        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
            'intention' => 'required|string',
            'question' => 'string',
            'agreement' => 'required',
        ];

        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // TODO on 2606 store data on the DB
        return redirect()->to(route('admin.key.requested'));
    }
    public function requested()
    {
        return view('v4.admin.requested_key');
    }
}
