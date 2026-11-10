<?php

use think\facade\Route;
use app\controller\admin\AdminPermission;
use app\controller\admin\Balance;
use app\controller\admin\Dashboard;
use app\controller\admin\Good;
use app\controller\admin\Login;
use app\controller\admin\SystemConfig;
use app\controller\admin\Task;
use app\controller\admin\User;
use app\controller\admin\Withdrawal;

Route::group('api', function () {
    Route::get('index', function () {
        return "index";
    });

    Route::post('admin/login', function () {
        return app()->make(Login::class)->login();
    });

    Route::group('admin', function () {
        Route::get('dashboard/summary', function () {
            return app()->make(Dashboard::class)->summary();
        });

        Route::group('permissions', function () {
            Route::get('/', function () {
                return app()->make(AdminPermission::class)->index();
            });
            Route::post('roles', function () {
                return app()->make(AdminPermission::class)->saveRoles();
            });
            Route::post('admins', function () {
                return app()->make(AdminPermission::class)->saveAdmins();
            });
        });

        Route::group('users', function () {
            Route::get('/', function () {
                return app()->make(User::class)->getList();
            });
            Route::post('create', function () {
                return app()->make(User::class)->create();
            });
            Route::post('update', function () {
                return app()->make(User::class)->updateInfo();
            });
            Route::post('upload', function () {
                return app()->make(User::class)->upload();
            });
            Route::post('password', function () {
                return app()->make(User::class)->updatePassword();
            });
            Route::post('level', function () {
                return app()->make(User::class)->updateLevel();
            });
            Route::post('credit-score', function () {
                return app()->make(User::class)->updateCreditScore();
            });
            Route::post('toggle-status', function () {
                return app()->make(User::class)->toggleStatus();
            });
            Route::get('commissions', function () {
                return app()->make(User::class)->getCommissionRecords();
            });
            Route::get('bonuses', function () {
                return app()->make(User::class)->getBonusRecords();
            });
        });

        Route::group('member-levels', function () {
            Route::get('/', function () {
                return app()->make(User::class)->getMemberLevels();
            });
            Route::post('save', function () {
                return app()->make(User::class)->saveMemberLevel();
            });
            Route::post('delete', function () {
                return app()->make(User::class)->deleteMemberLevel();
            });
        });

        Route::group('goods', function () {
            Route::get('/', function () {
                return app()->make(Good::class)->getList();
            });
            Route::post('save', function () {
                return app()->make(Good::class)->save();
            });
            Route::post('upload', function () {
                return app()->make(Good::class)->upload();
            });
            Route::post('toggle-status', function () {
                return app()->make(Good::class)->toggleStatus();
            });
            Route::post('delete', function () {
                return app()->make(Good::class)->delete();
            });
        });

        Route::group('balance', function () {
            Route::post('update', function () {
                return app()->make(Balance::class)->update();
            });
            Route::get('records', function () {
                return app()->make(Balance::class)->getRecords();
            });
        });

        Route::group('withdrawals', function () {
            Route::get('/', function () {
                return app()->make(Withdrawal::class)->getList();
            });
            Route::post('approve', function () {
                return app()->make(Withdrawal::class)->approve();
            });
            Route::post('reject', function () {
                return app()->make(Withdrawal::class)->reject();
            });
        });

        Route::group('tasks', function () {
            Route::get('/', function () {
                return app()->make(Task::class)->getList();
            });
            Route::post('config', function () {
                return app()->make(Task::class)->config();
            });
            Route::post('reset', function () {
                return app()->make(Task::class)->reset();
            });
            Route::get('user-tasks', function () {
                return app()->make(Task::class)->getUserTasks();
            });
            Route::get('combo/list', function () {
                return app()->make(Task::class)->getComboTasks();
            });
            Route::post('combo/save', function () {
                return app()->make(Task::class)->saveComboTask();
            });
            Route::post('combo/delete', function () {
                return app()->make(Task::class)->deleteComboTask();
            });
        });

        Route::group('partners', function () {
            Route::get('/', function () {
                return app()->make(SystemConfig::class)->getPartners();
            });
            Route::post('save', function () {
                return app()->make(SystemConfig::class)->savePartner();
            });
            Route::post('upload', function () {
                return app()->make(SystemConfig::class)->uploadPartnerLogo();
            });
            Route::post('delete', function () {
                return app()->make(SystemConfig::class)->deletePartner();
            });
        });

        Route::group('settings', function () {
            Route::get('/', function () {
                return app()->make(SystemConfig::class)->getSettings();
            });
            Route::post('save', function () {
                return app()->make(SystemConfig::class)->saveSettings();
            });
        });

    })->middleware(\app\middleware\AdminAuth::class);
});
