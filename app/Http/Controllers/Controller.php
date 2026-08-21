<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{

    /**
     * @OA\Info(
     *      version="1.0.0",
     *      title="ECW App OpenApi  Documentation",
     *      description="ECW App Swagger OpenApi description",
     *      @OA\Contact(
     *          email="mamunmo21@gmail.com"
     *      ),
     *      @OA\License(
     *          name="Apache 2.0",
     *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
     *      )
     * )
     * @OA\SecurityScheme(
     *      securityScheme="bearerAuth",
     *      in="header",
     *      name="Authorization",
     *      type="http",
     *      scheme="Bearer",
     *      bearerFormat="JWT",
     * ),
     * @OA\Server(
     *      url=L5_SWAGGER_CONST_HOST,
     *      description="ECW App API Server"
     * )

     *
     * @OA\Tag(
     *     name="Projects",
     *     description="API Endpoints of Projects"
     * )
     */
    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests;
}
