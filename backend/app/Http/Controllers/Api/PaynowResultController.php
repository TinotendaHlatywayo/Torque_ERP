<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PaynowGateway;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaynowResultController extends Controller
{
    public function __invoke(Request $request, PaynowGateway $paynow)
    {
        try {
            parse_str($request->getContent(), $callback);
            $paynow->handleCallback($callback);
        } catch (ConflictHttpException $exception) {
            report($exception);

            return response('Invalid Paynow result.', 400)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response('', 204);
    }
}
