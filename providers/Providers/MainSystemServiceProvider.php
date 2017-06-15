<?php namespace HS\Providers;

use App;
use View;
use Yaml;
use File;
use Lang;
use Event;
use Route;
use Config;
use Backend;
use Request;
use Response;
use Validator;
use BackendMenu;
use BackendAuth;
use Twig_Environment;
use Twig_Loader_String;

use Illuminate\Foundation\AliasLoader;

use System\Classes\ErrorHandler;
use System\Classes\MarkupManager;
use System\Classes\PluginManager;
use System\Classes\SettingsManager;
use System\Twig\Engine as TwigEngine;
use System\Twig\Loader as TwigLoader;
use System\Twig\Extension as TwigExtension;
use System\Models\EventLog;
use System\Models\MailSetting;
use System\Models\MailTemplate;
use System\Classes\CombineAssets;
use Backend\Classes\WidgetManager;

use October\Rain\Support\ModuleServiceProvider;
use October\Rain\Router\Helper as RouterHelper;
use October\Rain\Parse\Yaml as YamlParse;
use October\Rain\Parse\Markdown as MarkdownParse;
use October\Rain\Parse\Twig as TwigParse;
use October\Rain\Parse\Ini as IniParse;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\AjaxException;

class MainSystemServiceProvider extends ModuleServiceProvider
{

        /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->addPathSymboles();
        $this->registerModulesLanguageViewsAndConfig();
        $this->registerRoutes();
        $this->registerHelpers();
        $this->registerTwigParser();
        $this->registerMailTemplates();
        $this->registerWidgets();
        $this->registerExceptionHandler();
        $this->registerExtenstionFunctionality();

        $this->registerBePermissions();

        if (App::runningInBackend()) {
            $this->registerBeMainMenu();
        }
    }

    /**
     * Bootstrap the module events.
     *
     * @return void
     */
    public function boot()
    {
    }

    /*
     * Register text twig parser
     */
    protected function registerTwigParser()
    {
        /*
         * Register system Twig environment
         */
        App::singleton('twig.environment', function ($app) {
            $twig = new Twig_Environment(new TwigLoader, ['auto_reload' => true]);
            $twig->addExtension(new TwigExtension);
            return $twig;
        });

        /*
         * Register .htm extension for Twig views
         */
        App::make('view')->addExtension('htm', 'twig', function () {
            return new TwigEngine(App::make('twig.environment'));
        });
    }

    public function addPathSymboles()
    {
        app('files')->pathSymbols['-'] = base_path() . '/models';
    }

    public function registerModulesLanguageViewsAndConfig()
    {
        $module = 'cms';
        $modulePath = base_path() . '/modules/' . $module;
        $this->loadViewsFrom($modulePath . '/views', $module);
        $this->loadTranslationsFrom($modulePath . '/lang', $module);
        $this->loadConfigFrom($modulePath . '/config', $module);

        $module = 'backend';
        $modulePath = base_path() . '/modules/' . $module;
        $this->loadViewsFrom($modulePath . '/views', $module);
        $this->loadTranslationsFrom($modulePath . '/lang', $module);
        $this->loadConfigFrom($modulePath . '/config', $module);

        $module = 'system';
        $modulePath = base_path() . '/modules/' . $module;
        $this->loadViewsFrom($modulePath . '/views', $module);
        $this->loadTranslationsFrom($modulePath . '/lang', $module);
        $this->loadConfigFrom($modulePath . '/config', $module);

        $module = 'app';
        $this->loadViewsFrom(File::symbolizePath('~') . '/views', $module);
        $this->loadTranslationsFrom(File::symbolizePath('~') .'/lang', $module);
        $this->loadConfigFrom(File::symbolizePath('~') . '/config', $module);

        View::addNamespace('exception', base_path() . '/views');
    }


    public function registerRoutes()
    {

        // strickly do not allow backend to anyone without main backend controller
        Route::group(['prefix'=>'backend'], function () {
            Route::any('/', function () {
                return Response::make(Lang::get('backend::lang.page.access_denied.label'), 403);
            });
            Route::any('{slug}', function () {
                return Response::make(Lang::get('backend::lang.page.access_denied.label'), 403);
            })->where('slug', '(.*)?');
        });

        // router correction for frontend and backend
        Route::any(Config::get('hs.backendUri', 'admin'), '\HS\Classes\BackendController@run');

        Route::group(['prefix' => Config::get('hs.backendUri', 'admin')], function () {
            Route::any('{slug}', '\HS\Classes\BackendController@run')->where('slug', '(.*)?');
        });


        Route::any('{slug}', '\HS\Classes\FrontendController@run')->where('slug', '(.*)?');

        $routesFile = base_path() . '/config/routes.php';
        require $routesFile;

        /*Route::group(['namespace' => 'HS\Providers'], function () {
        Route::any('{slug}', '\HS\Providers\MainController@run')->where('slug', '(.*)?');
        });*/
    }

    public function registerHelpers()
    {

        // register parser
        $this->app['parse.markdown'] = $this->app->share(function ($app) {
            return new MarkdownParse;
        });

        $this->app['parse.yaml'] = $this->app->share(function ($app) {
            return new YamlParse;
        });

        $this->app['parse.twig'] = $this->app->share(function ($app) {
            return new TwigParse;
        });

        $this->app['parse.ini'] = $this->app->share(function ($app) {
            return new IniParse;
        });

        // helpers -- backend helper replaced
        App::singleton('backend.helper', function () {
            return new \HS\Helpers\Backend;
        });

        App::singleton('backend.menu', function () {
            return \Backend\Classes\NavigationManager::instance();
        });

        App::singleton('backend.auth', function () {
            return \HS\AuthManager\BeAuthManager::instance();
        });

        $alias = AliasLoader::getInstance();
        $alias->alias('Auth', 'HS\Facades\Auth');

        App::singleton('frontend.auth', function () {
            return \HS\AuthManager\FeAuthManager::instance();
        });
    }

    public function registerMailTemplates()
    {
        //register mail templates
        MailTemplate::registerCallback(function ($template) {
            $template->registerMailTemplates([
                'app::mail.invite'  => 'Invitation for newly created administrators.',
                'app::mail.restore' => 'Password reset instructions for backend-end administrators.',
            ]);
        });

        // fe users
        MailTemplate::registerCallback(function ($template) {
            $template->registerMailTemplates([
                'app::mail.user.activate'   => 'Activation email sent to new users.',
                'app::mail.user.welcome'    => 'Welcome email sent when a user is activated.',
                'app::mail.user.restore'    => 'Password reset instructions for front-end users.',
                'app::mail.user.new_user'   => 'Sent to administrators when a new user joins.',
                'app::mail.user.reactivate' => 'Notification for users who reactivate their account.',
            ]);
        });
    }


    public function registerWidgets()
    {
        //register widgets
        WidgetManager::instance()->registerReportWidgets(function ($manager) {
            $manager->registerReportWidget('System\ReportWidgets\Status', [
            'label'   => 'backend::lang.dashboard.status.widget_title_default',
            'context' => 'dashboard'
            ]);
        });

        WidgetManager::instance()->registerFormWidgets(function ($manager) {
            $manager->registerFormWidget('Backend\FormWidgets\CodeEditor', 'codeeditor');
            $manager->registerFormWidget('Backend\FormWidgets\RichEditor', 'richeditor');
            $manager->registerFormWidget('Backend\FormWidgets\MarkdownEditor', 'markdown');
            $manager->registerFormWidget('Backend\FormWidgets\FileUpload', 'fileupload');
            $manager->registerFormWidget('Backend\FormWidgets\Relation', 'relation');
            $manager->registerFormWidget('Backend\FormWidgets\DatePicker', 'datepicker');
            $manager->registerFormWidget('Backend\FormWidgets\TimePicker', 'timepicker');
            $manager->registerFormWidget('Backend\FormWidgets\ColorPicker', 'colorpicker');
            $manager->registerFormWidget('Backend\FormWidgets\DataTable', 'datatable');
            $manager->registerFormWidget('Backend\FormWidgets\RecordFinder', 'recordfinder');
            $manager->registerFormWidget('Backend\FormWidgets\Repeater', 'repeater');
            $manager->registerFormWidget('Backend\FormWidgets\TagList', 'taglist');
            $manager->registerFormWidget('Cms\FormWidgets\MediaFinder', 'mediafinder');
        });
    }


    public function registerExceptionHandler()
    {
        Event::listen('exception.beforeRender', function ($exception, $httpCode, $request) {

            $proposedException = $exception;

            // Disable the error handler for test and CLI environment
            if (App::runningUnitTests() || App::runningInConsole()) {
                return;
            }


            // Detect AJAX request and use error 500
            if (Request::ajax()) {
                return $proposedException instanceof AjaxException
                 ? $proposedException->getContents()
                 : static::getDetailedMessage($proposedException);
            }

            // Clear the output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Friendly error pages are used
            /*if (($customError = $this->handleCustomError()) !== null) {
            return $customError;
            }*/

            // If the exception is already our brand, use it.
            if ($proposedException instanceof BaseException) {
                $exception = $proposedException;
            } // If there is an active mask prepared, use that.
            /*elseif (static::$activeMask !== null) {
            $exception = static::$activeMask;
            $exception->setMask($proposedException);
            }*/
            // Otherwise we should mask it with our own default scent.
            else {
                $exception = new ApplicationException($proposedException->getMessage(), 0);
                $exception->setMask($proposedException);
            }


            return View::make('exception::exception', ['exception' => $exception]);
        });
    }
    public function registerExtenstionFunctionality()
    {
        /*
         * Allowed file extensions, as opposed to mime types.
         * - extensions: png,jpg,txt
         */
        Validator::extend('extensions', function ($attribute, $value, $parameters) {
            $extension = strtolower($value->getClientOriginalExtension());
            return in_array($extension, $parameters);
        });

        Validator::replacer('extensions', function ($message, $attribute, $rule, $parameters) {
            return strtr($message, [':values' => implode(', ', $parameters)]);
        });
    }

    public function registerBePermissions()
    {

        BackendAuth::registerCallback(function ($manager) {
            $manager->registerPermissions('HS.Backend', [
                'backend.access_dashboard' => [
                    'label' => 'system::lang.permissions.view_the_dashboard',
                    'tab'   => 'system::lang.permissions.name'
                ],
                'backend.manage_users' => [
                    'label' => 'system::lang.permissions.manage_other_administrators',
                    'tab'   => 'system::lang.permissions.name'
                ],
                'backend.manage_preferences' => [
                    'label' => 'system::lang.permissions.manage_preferences',
                    'tab'   => 'system::lang.permissions.name'
                ],
                'backend.manage_editor' => [
                    'label' => 'system::lang.permissions.manage_editor',
                    'tab'   => 'system::lang.permissions.name'
                ],
                'backend.manage_branding' => [
                    'label' => 'system::lang.permissions.manage_branding',
                    'tab'   => 'system::lang.permissions.name'
                ]
            ]);
        });

        BackendAuth::registerCallback(function ($manager) {
            $manager->registerPermissions('HS.System', [
                'system.manage_mail_settings' => [
                    'label' => 'system::lang.permissions.manage_mail_settings',
                    'tab' => 'system::lang.permissions.name'
                ],
                'system.manage_mail_templates' => [
                    'label' => 'system::lang.permissions.manage_mail_templates',
                    'tab' => 'system::lang.permissions.name'
                ]
            ]);
        });

        $permissionsFiles = glob(File::symbolizePath('~/providers/Permissions/*.yaml'));
        foreach ($permissionsFiles as $value) {
            $permissionYaml = Yaml::parse(file_get_contents($value));
            $itemCode = 'HS.' . ucfirst($permissionYaml['itemcode']);
            $permissionYaml = $permissionYaml['permissions'];
            BackendAuth::registerCallback(function ($manager) use ($itemCode, $permissionYaml) {
                $manager->registerPermissions($itemCode, $permissionYaml);
            });
        }
    }

    public function registerBeMainMenu()
    {
        /*
         * Register the sidebar for the System main menu
         */
        BackendMenu::registerContextSidenavPartial(
            'HS.System',
            'system',
            '~/modules/system/partials/_system_sidebar.htm'
        );

        // regiater all menus
        BackendMenu::registerCallback(function ($manager) {
            $manager->registerMenuItems('HS.Backend', [
                'dashboard' => [
                    'label'       => 'backend::lang.dashboard.menu_label',
                    'icon'        => 'icon-dashboard',
                    'iconSvg'     => 'modules/backend/assets/images/dashboard-icon.svg',
                    'url'         => '/admin/',
                    'permissions' => ['backend.access_dashboard'],
                    'order'       => 1
                ]
            ]);
        });

        $navigationFiles = glob(File::symbolizePath('~/providers/Navigation/*.yaml'));
        foreach ($navigationFiles as $value) {
            $navYaml = Yaml::parse(file_get_contents($value));
            $itemCode = 'HS.' . ucfirst($navYaml['itemcode']);
            $navYaml = $navYaml['navigation'];
            BackendMenu::registerCallback(function ($manager) use ($itemCode, $navYaml) {
                $manager->registerMenuItems($itemCode, $navYaml);
            });
        }

        SettingsManager::instance()->registerCallback(function ($manager) {

            $manager->registerSettingItems('HS.Backend', [
                'branding' => [
                    'label'       => 'backend::lang.branding.menu_label',
                    'description' => 'backend::lang.branding.menu_description',
                    'category'    => SettingsManager::CATEGORY_SYSTEM,
                    'icon'        => 'icon-paint-brush',
                    'url'         => Backend::url('admin/settings/settings/update/hs/backend/branding'),
                    'classModel'  => 'HS\Models\Backend\BrandSetting',
                    'permissions' => ['backend.manage_branding'],
                    'order'       => 500,
                    'keywords'    => 'brand style'
                ],
                'editor' => [
                    'label'       => 'backend::lang.editor.menu_label',
                    'description' => 'backend::lang.editor.menu_description',
                    'category'    => SettingsManager::CATEGORY_SYSTEM,
                    'icon'        => 'icon-code',
                    'url'         => Backend::url('admin/settings/settings/update/hs/backend/editor'),
                    'classModel'  => 'HS\Models\Backend\EditorSetting',
                    'permissions' => ['backend.manage_editor'],
                    'order'       => 500,
                    'keywords'    => 'html code class style'
                ],

                'administrators' => [
                    'label'       => 'backend::lang.user.menu_label',
                    'description' => 'backend::lang.user.menu_description',
                    'category'    => SettingsManager::CATEGORY_SYSTEM,
                    'icon'        => 'icon-users',
                    'url'         => Backend::url('admin/settings/users'),
                    'permissions' => ['backend.manage_users'],
                    'order'       => 400
                ],

                'myaccount' => [
                    'label'       => 'backend::lang.myaccount.menu_label',
                    'description' => 'backend::lang.myaccount.menu_description',
                    'category'    => SettingsManager::CATEGORY_MYSETTINGS,
                    'icon'        => 'icon-user',
                    'url'         => Backend::URL('admin/settings/users/myaccount'),
                    'order'       => 500,
                    'context'     => 'mysettings',
                    'keywords'    => 'backend::lang.myaccount.menu_keywords'
                ],

                'preferences' => [
                    'label'       => 'backend::lang.backend_preferences.menu_label',
                    'description' => 'backend::lang.backend_preferences.menu_description',
                    'category'    => SettingsManager::CATEGORY_MYSETTINGS,
                    'icon'        => 'icon-laptop',
                    'url'         => Backend::URL('admin/settings/preferences'),
                    'permissions' => ['backend.manage_preferences'],
                    'order'       => 510,
                    'context'     => 'mysettings'
                ]
            ]);

            $manager->registerSettingItems('HS.System', [


                'mail_settings' => [
                    'label'       => 'system::lang.mail.menu_label',
                    'description' => 'system::lang.mail.menu_description',
                    'category'    => SettingsManager::CATEGORY_MAIL,
                    'icon'        => 'icon-envelope',
                    'url'         => Backend::url('admin/settings/settings/update/hs/system/mail_settings'),
                    'classModel'  => 'HS\Models\Backend\MailSetting',
                    'permissions' => ['system.manage_mail_settings'],
                    'order'       => 600
                ],

                'mail_templates' => [
                    'label'       => 'system::lang.mail_templates.menu_label',
                    'description' => 'system::lang.mail_templates.menu_description',
                    'category'    => SettingsManager::CATEGORY_MAIL,
                    'icon'        => 'icon-envelope-square',
                    'url'         => Backend::url('admin/settings/mailtemplates'),
                    'permissions' => ['system.manage_mail_templates'],
                    'order'       => 610
                ],

            ]);

            $manager->registerSettingItems('HS.App', [

                'settings' => [
                    'label'       => 'app::lang.settings.menu_label',
                    'description' => 'app::lang.settings.menu_description',
                    'category'    => SettingsManager::CATEGORY_USERS,
                    'icon'        => 'icon-cog',
                    'url'         => Backend::url('admin/settings/settings/update/hs/app/settings'),
                    'classModel'       => 'HS\Models\User\Settings',
                    'order'       => 500,
                    'permissions' => ['app.users.access_settings'],
                ],
            ]);
        });
    }

     /**
     * Returns a more descriptive error message if application
     * debug mode is turned on.
     * @param Exception $exception
     * @return string
     */
    public static function getDetailedMessage($exception)
    {
        /*
         * Application Exceptions never display a detailed error
         */
        if (!($exception instanceof ApplicationException) && Config::get('app.debug', false)) {
            return sprintf('"%s" on line %s of %s',
            $exception->getMessage(),
            $exception->getLine(),
            $exception->getFile()
            );
        } else {
            return $exception->getMessage();
        }
    }
}
