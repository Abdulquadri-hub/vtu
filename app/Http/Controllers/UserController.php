<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponseHandler;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    //
    public function index(Request $request){
        return ApiResponseHandler::successReponse($request->user());
    }
}
