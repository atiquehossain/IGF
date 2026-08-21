<?php

namespace App\Http\Controllers\Swa;

use App\Http\Controllers\Controller;

class AuthController extends Controller {
    /**
     * @OA\Post(
     * * path="/v1/auth/login",
     *   tags={"[ECW] auth"},
     *   summary="Login ECW App",
     *   operationId="login",
     *
     *   @OA\Parameter(
     *      name="phone_no",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *  @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),

     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *        response=422,
     *        description="Validation error",
     *    ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      )
     * )
     * */

     /**
     * @OA\Post(
     * * path="/v1/auth/login-2fa",
     *   tags={"[ECW] auth"},
     *   summary="Login ECW App 2fa",
     *   operationId="login2fa",
     *
     *   @OA\Parameter(
     *      name="phone_no",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *  @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),

     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *        response=422,
     *        description="Validation error",
     *    ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      )
     * )
     * */
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     * * path="/v1/auth/verify2fa",
     *   tags={"[ECW] auth"},
     *   summary="2FA OTP Verify ECW App",
     *   operationId="verify2fa",
     *
     *   @OA\Parameter(
     *      name="access_token",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="secret",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="code",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *    @OA\Response(
     *          response=422,
     *          description="Validation error",
     *    ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      )
     * )
     * */
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     * * path="/v1/auth/social",
     *   tags={"[ECW] auth"},
     *   summary="Social Login ECW App",
     *   operationId="social",
     *
     *   @OA\Parameter(
     *      name="access_token",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="provider",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *           default="google"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *    @OA\Response(
     *          response=422,
     *          description="Validation error",
     *    ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      )
     * )
     * */
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     * * path="/v1/auth/logout",
     *   tags={"[ECW] auth"},
     *   summary="Logout ECW App",
     *   operationId="logout",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *      response=200,
     *      description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *  @OA\Response(
     *     response=422,
     *     description="Validation error",
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not found"
     *   ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      )
     * )
     * */
    /**
     * Logout api
     *

     *
     * @return \Illuminate\Http\Response
     */
}
