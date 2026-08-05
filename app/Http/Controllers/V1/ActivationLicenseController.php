<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateActivationLicenseRequest;
use App\Models\ActivationLicense;
use App\Services\ActivationLicenseService;
use App\Status;
use App\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ActivationLicenseController extends Controller
{
    public function create(
        CreateActivationLicenseRequest $request,
        ActivationLicenseService $activationLicenseService
    ): JsonResponse {
        $validated = $request->validated();
        $type = (int) $validated['type'];

        $customCodeAlreadyExists = isset($validated['code'])
            && ActivationLicense::where('code', $validated['code'])
                ->where('type', $type)
                ->exists();

        if ($customCodeAlreadyExists) {
            return response()->json([
                'response_code' => 409,
                'response_message' => 'An activation license with this code and type already exists.',
            ], 409);
        }

        try {
            $licenses = $activationLicenseService->create($validated);
        } catch (RuntimeException) {
            return response()->json([
                'response_code' => 500,
                'response_message' => 'The activation license could not be created.',
            ], 500);
        }

        return response()->json([
            'response_code' => 201,
            'response_message' => $licenses->count() === 1
                ? 'Activation license created.'
                : 'Activation licenses created.',
            'count' => $licenses->count(),
            'licenses' => $licenses->values(),
        ], 201, [
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The endpoint will return the license, also it will return the plan days.
     * @param Request $request
     * @return JsonResponse
     */
    public function activate(Request $request): JsonResponse
    {

        $code = $request->input('code');
        $type = $request->input('type');
        $activate = $request->input('activate');

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

        if($activate === null){
            return response()->json(['response_code' => 400, 'response_message' => 'Activate is missing']);
        }


        $activationLicense = ActivationLicense::where([['code', $code], ['type', $enumType->value]])->first();

        if($activationLicense === null){
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license not found']);
        }

        if($activationLicense->status === Status::INACTIVE->value) {
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license is not activate and cant be used.']);
        }

        if($activationLicense->status === Status::ACTIVATED->value) {
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license has already been activated.']);
        }

        if($activate == 1) {
            $activationLicense->status = Status::ACTIVATED->value;
            $activationLicense->save();
        }

        return response()->json(['response_code' => 200, 'license' => $activationLicense]);

    }

    /**
     * The endpoint will return the license, also it will return the plan days.
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
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
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license is not activate and cant be used.']);
        }

        if($activationLicense->status === Status::ACTIVATED->value) {
            return response()->json(['response_code' => 400, 'response_message' => 'Activation license has already been activated.']);
        }

        return response()->json(['response_code' => 200, 'license' => $activationLicense]);

    }

}
