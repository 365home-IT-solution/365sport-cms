<?php

namespace App\Exceptions;

use BezhanSalleh\FilamentExceptions\FilamentExceptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    // *** Khi nào cần debug điều chỉnh
    // public function render($request, Throwable $exception)
    // {
    //     if (config('app.debug') === true && auth()->check()) {
    //         $errorData = [
    //             'message' => $exception->getMessage(),
    //             'file' => $exception->getFile(),
    //             'line' => $exception->getLine(),
    //             'trace' => $exception->getTraceAsString(),
    //             'url' => request()->url(),
    //             'method' => request()->method(),
    //             'timestamp' => now()->format('Y-m-d H:i:s'),
    //             'user' => auth()->user()->id,
    //             'user_agent' => $request->userAgent(),
    //             'ip' => $request->ip()
    //         ];

    //         $errorId = uniqid('error_');
    //         session()->flash('error_id', $errorId);

    //         return response()->view('emails.error-report', [
    //             'errorId' => $errorId,
    //             'errorData' => $errorData
    //         ]);
    //     }

    //     return parent::render($request, $exception);
    // }

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Dự án không có route tên 'login' (mặc định của Laravel) nên chưa đăng nhập mà vào route bảo
     * vệ sẽ bị RouteNotFoundException → 500. API luôn trả JSON 401 (kể cả client quên header
     * Accept: application/json), trang web chuyển tới trang đăng nhập admin.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return redirect()->guest(route('filament.admin.auth.login'));
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e)) {
                FilamentExceptions::report($e);

                // Theo dõi lỗi ra ngoài (Sentry) — chỉ gửi khi đã cấu hình SENTRY_LARAVEL_DSN
                // trong .env (chưa có DSN thì bỏ qua, không lỗi gì cả). Trước đây lỗi chỉ nằm
                // trong log file/FilamentExceptions, không ai được cảnh báo chủ động.
                if (config('sentry.dsn') && app()->bound('sentry')) {
                    app('sentry')->captureException($e);
                }
            }
        });
    }
}
