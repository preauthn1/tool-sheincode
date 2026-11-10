<?php

use app\controller\api\Login;
use app\controller\api\Register;
use app\controller\api\Bill;
use app\controller\api\SupportChat;
use app\controller\api\System;
use app\controller\api\Task;
use app\controller\api\Withdrawal;
use app\middleware\Auth;
use think\facade\Route;

Route::post('api/v1/upload', function () {
    return app()->make(SupportChat::class)->upload();
})->middleware(Auth::class);

Route::post('v1/upload', function () {
    return app()->make(SupportChat::class)->upload();
})->middleware(Auth::class);

Route::group('webapi', function () {
    Route::post('register', function () {
        return app()->make(Register::class)->register();
    });

    Route::get('check-invite-code', function () {
        return app()->make(Register::class)->checkInviteCode();
    });

    Route::post('login', function () {
        return app()->make(Login::class)->login();
    });

    Route::get('system/settings', function () {
        return app()->make(System::class)->settings();
    });

    Route::get('system/partners', function () {
        return app()->make(System::class)->partners();
    });

    Route::get('user/public', function () {
        return app()->make(Login::class)->getPublicUserInfo();
    });

    Route::get('user/info', function () {
        return app()->make(Login::class)->getUserInfo();
    })->middleware(Auth::class);

    Route::post('user/heartbeat', function () {
        return app()->make(Login::class)->heartbeat();
    })->middleware(Auth::class);

    Route::post('user/password', function () {
        return app()->make(Login::class)->changeLoginPassword();
    })->middleware(Auth::class);

    Route::post('user/withdrawal-password', function () {
        return app()->make(Login::class)->changeWithdrawalPassword();
    })->middleware(Auth::class);

    Route::post('user/profile', function () {
        return app()->make(Login::class)->updateProfile();
    })->middleware(Auth::class);

    Route::get('user/wallet', function () {
        return app()->make(Withdrawal::class)->wallet();
    })->middleware(Auth::class);

    Route::post('user/wallet', function () {
        return app()->make(Withdrawal::class)->saveWallet();
    })->middleware(Auth::class);

    Route::post('withdrawals/submit', function () {
        return app()->make(Withdrawal::class)->submit();
    })->middleware(Auth::class);

    Route::get('bill/records', function () {
        return app()->make(Bill::class)->records();
    })->middleware(Auth::class);

    Route::group('task', function () {
        Route::get('check-start', function () {
            return app()->make(Task::class)->checkCanStart();
        });
        Route::get('today', function () {
            return app()->make(Task::class)->getTodayTasks();
        });
        Route::post('start', function () {
            return app()->make(Task::class)->start();
        });
        Route::get('orders', function () {
            return app()->make(Task::class)->getOrders();
        });
        Route::post('submit', function () {
            return app()->make(Task::class)->submitOrder();
        });
    })->middleware(Auth::class);

    Route::group('support', function () {
        Route::get('conversation', function () {
            return app()->make(SupportChat::class)->conversation();
        });
        Route::get('messages', function () {
            return app()->make(SupportChat::class)->messages();
        });
        Route::post('upload', function () {
            return app()->make(SupportChat::class)->upload();
        });
    })->middleware(Auth::class);
});
