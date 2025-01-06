<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivationLicense;
use App\Status;
use App\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivationLicenseController extends Controller
{

    /**
     * The endpoint will return the license, also it will return the plan days.
     * Once called, the activation code cannot be used anymore.
     * @param Request $request
     * @return JsonResponse
     */
    public function activate(Request $request): JsonResponse
    {

        $code = $request->input('code');
        $type = $request->input('type');

        if($type === null) {
            return response()->json(['response_code' => 400, 'response_message' => 'No type provided']);
        }

        try {
            $enumType = Type::from($type);
        } catch (\ValueError) {
            return response()->json(['response_code' => 400, 'response_message' => $type . ' is not a valid type']);
        }

        if($code === null){
            return response()->json(['response_code' => 400, 'response_message' => 'No code provided']);
        }

        $activationLicense = ActivationLicense::where([['code', $code], ['type', $enumType->value]])->first();

        if($activationLicense === null){
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license not found']);
        }

        if($activationLicense->status === Status::INACTIVE->value) {
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license is not activated.']);
        }

        if($activationLicense->status === Status::ACTIVATED->value) {
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license has already been activated.']);
        }

        $activationLicense->status = Status::ACTIVATED->value;
        $activationLicense->save();

        return response()->json(['response_code' => 200, 'license' => $activationLicense]);

    }

}
