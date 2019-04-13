<?php

namespace SoliDry\Exceptions;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SoliDry\Extension\JSONApiInterface;

trait ErrorHandler
{
    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param Exception $e
     * @return JsonResponse
     */
    private function prepareRender($request, Exception $e) : JsonResponse
    {
        return response()->json(
            [
                JSONApiInterface::CONTENT_ERRORS => [
                    [
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                        'uri'     => $request->getUri(),
                        'meta'    => $e->getTraceAsString(),
                    ],
                ]
            ]
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param Exception $e
     * @return Response
     */
    public function getErrorResponse($request, Exception $e) : Response
    {
        return response(response()->json(
            [
                JSONApiInterface::CONTENT_ERRORS => [
                    [
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                        'uri'     => $request->getUri(),
                        'meta'    => $e->getTraceAsString(),
                    ],
                ]
            ]
        )->content());
    }

    /**
     * Render an exception as a JSON API error response.
     *
     * @param Request $request
     * @param Exception $e
     * @return JsonResponse
     */
    public function renderJsonApi(Request $request, Exception $e): JsonResponse
    {
        return $this->prepareRender($request, $e);
    }
}