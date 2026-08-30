<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Modules\Payment\Entities\Order;
use App\Services\PayOSCredentialResolver;
use PayOS\PayOS;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */

    public function getPaymentLinkInfoOfOrder(string $id)
    {
        // Đúng bộ khoá PayOS của đối tác sở hữu đơn này nếu đã cấu hình riêng, không thì PayOS
        // chung nền tảng như cũ — xem App\Services\PayOSCredentialResolver. $id ở đây thực chất
        // là order_code (PayOS orderCode), không phải orders.id — tra theo order_code khớp đúng
        // quy ước dùng ở PaymentController.
        $order = Order::where('order_code', $id)->first();
        $payOS = PayOSCredentialResolver::client($order?->partner);
        try {
            $response = $payOS->getPaymentLinkInformation($id);
            return response()->json([
                "error" => 0,
                "message" => "Success",
                "data" => $response["data"]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "error" => $th->getCode(),
                "message" => $th->getMessage(),
                "data" => null
            ]);
        }
    }

    public function cancelPaymentLinkOfOrder(Request $request, string $id)
    {
        $body = json_decode($request->getContent(), true);
        $order = Order::where('order_code', $id)->first();
        $payOS = PayOSCredentialResolver::client($order?->partner);
        try {
            $cancelBody = is_array($body) && $body["cancellationReason"] ? $body : null;
            $response = $payOS->cancelPaymentLink($id, $cancelBody);
            return response()->json([
                "error" => 0,
                "message" => "Success",
                "data" => $response["data"]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "error" => $th->getCode(),
                "message" => $th->getMessage(),
                "data" => null
            ]);
        }
    }
}
