<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Modules\AppPage\App\Models\AppPage;
use Modules\AppPage\App\Policies\AppPagePolicy;
use Modules\BladeThemeV1\App\Models\AdditionService;
use Modules\BladeThemeV1\App\Policies\AdditionServicePolicy;
use Modules\Category\Entities\Category;
use Modules\Category\Entities\CategoryPolicy;
use Modules\Comment\Entities\Comment;
use Modules\Comment\Entities\CommentPolicy;
use Modules\DataPermission\Entities\UserBranchPermission;
use Modules\DataPermission\Entities\UserBranchPermissionPolicy;
use Modules\Page\Entities\ComponentPolicy;
use Modules\Footer\Entities\Footer;
use Modules\Footer\Entities\FooterPolicy;
use Modules\Form\Entities\EmailSetting;
use Modules\Form\Entities\EmailSettingPolicy;
use Modules\Form\Entities\Form;
use Modules\Form\Entities\FormNotification;
use Modules\Form\Entities\FormNotificationPolicy;
use Modules\Form\Entities\FormPolicy;
use Modules\Form\Entities\FormSubmission;
use Modules\Form\Entities\FormSubmissionPolicy;
use Modules\Header\Entities\Header;
use Modules\Header\Entities\HeaderPolicy;
use Modules\Menu\Entities\Menu;
use Modules\Menu\Entities\MenuPolicy;
use Modules\Page\Entities\Component;
use Modules\Page\Entities\Page;
use Modules\Page\Entities\PagePolicy;
use Modules\Post\Entities\Post;
use Modules\Post\Entities\PostPolicy;
use Modules\Process\Entities\Process;
use Modules\Process\Entities\ProcessPolicy;
use Modules\Employee\Entities\AllowanceType;
use Modules\Employee\Entities\AllowanceTypePolicy;
use Modules\Employee\Entities\DeductionType;
use Modules\Employee\Entities\DeductionTypePolicy;
use Modules\Employee\Entities\Department;
use Modules\Employee\Entities\DepartmentPolicy;
use Modules\Employee\Entities\Position;
use Modules\Employee\Entities\PositionPolicy;
use Modules\Employee\Entities\SalaryTemplate;
use Modules\Employee\Entities\SalaryTemplatePolicy;
use Modules\Employee\Entities\SalaryType;
use Modules\Employee\Entities\SalaryTypePolicy;
use Modules\Employee\Entities\ConsultationLog;
use Modules\Employee\Entities\ConsultationLogPolicy;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomAmenity;
use Modules\Product\App\Models\RoomImage;
use Modules\Product\App\Models\RoomService;
use Modules\Product\App\Models\RoomSpecial;
use Modules\Product\App\Policies\ProductPolicy;
use Modules\Product\App\Policies\RoomAmenityPolicy;
use Modules\Product\App\Policies\RoomImagePolicy;
use Modules\Product\App\Policies\RoomServicePolicy;
use Modules\Product\App\Policies\RoomSpecialPolicy;
use Modules\SettingCompany\Entities\Branch;
use Modules\SettingCompany\Entities\BranchPolicy;
use Modules\SettingCompany\Entities\Business;
use Modules\SettingCompany\Entities\BusinessPolicy;
use Modules\Tag\Entities\TagPolicy;
use Modules\Zns\App\Models\ZnsNotification;
use Modules\Zns\App\Policies\ZnsNotificationPolicy;
use Spatie\Permission\Models\Role;
use Spatie\Tags\Tag;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class               => RolePolicy::class,
        Category::class           => CategoryPolicy::class,
        Comment::class            => CommentPolicy::class,
        Component::class          => ComponentPolicy::class,
        Form::class               => FormPolicy::class,
        EmailSetting::class       => EmailSettingPolicy::class,
        FormNotification::class   => FormNotificationPolicy::class,
        FormSubmission::class     => FormSubmissionPolicy::class,
        Menu::class               => MenuPolicy::class,
        Page::class               => PagePolicy::class,
        Post::class               => PostPolicy::class,
        Process::class            => ProcessPolicy::class,
        Product::class            => ProductPolicy::class,
        RoomAmenity::class        => RoomAmenityPolicy::class,
        RoomImage::class          => RoomImagePolicy::class,
        RoomService::class        => RoomServicePolicy::class,
        RoomSpecial::class        => RoomSpecialPolicy::class,
        AdditionService::class    => AdditionServicePolicy::class,
        AppPage::class            => AppPagePolicy::class,
        UserBranchPermission::class => UserBranchPermissionPolicy::class,
        ZnsNotification::class    => ZnsNotificationPolicy::class,
        Branch::class             => BranchPolicy::class,
        Business::class           => BusinessPolicy::class,
        Tag::class                => TagPolicy::class,
        User::class               => UserPolicy::class,
        // Policy của module Employee (đã có file nhưng trước đây CHƯA đăng ký -> Mẫu lương/Phòng ban/Chức danh...
        // mở cho mọi tài khoản vào admin).
        AllowanceType::class    => AllowanceTypePolicy::class,
        DeductionType::class    => DeductionTypePolicy::class,
        Department::class       => DepartmentPolicy::class,
        Position::class         => PositionPolicy::class,
        SalaryTemplate::class   => SalaryTemplatePolicy::class,
        SalaryType::class       => SalaryTypePolicy::class,
        ConsultationLog::class  => ConsultationLogPolicy::class,
        // Chấm công + kỳ lương (model nằm trong module nên Laravel không tự đoán được policy).
        \Modules\Employee\Entities\EmployeeAttendance::class => \App\Policies\EmployeeAttendancePolicy::class,
        \Modules\Employee\Entities\PayrollPeriod::class      => \App\Policies\PayrollPeriodPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
