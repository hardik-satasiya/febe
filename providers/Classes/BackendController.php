<?php namespace HS\Classes;

use Str;
use App;
use File;
use View;
use Config;
use Response;
use Exception;
use Closure;

use Illuminate\Routing\Controller as ControllerBase;

use October\Rain\Router\Helper as RouterHelper;

/**
 * The Backend controller class.
 * The base controller services back end pages.
 *
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class BackendController extends ControllerBase
{
    use \October\Rain\Extension\ExtendableTrait;

    /**
     * @var array Behaviors implemented by this controller.
     */
    public $implement;

    /**
     * @var string Allows early access to page action.
     */
    public static $action;

    /**
     * @var array Allows early access to page parameters.
     */
    public static $params;

    /**
     * Instantiate a new BackendController instance.
     */
    public function __construct()
    {
        $this->extendableConstruct();
    }

    /**
     * Extend this object properties upon construction.
     */
    public static function extend(Closure $callback)
    {
        self::extendableExtendCallback($callback);
    }

    /**
     * Finds and serves the requested backend controller.
     * If the controller cannot be found, returns the Cms page with the URL /404.
     * If the /404 page doesn't exist, returns the system 404 page.
     * @param string $url Specifies the requested page URL.
     * If the parameter is omitted, the current URL used.
     * @return string Returns the processed page content.
     */
    public function run($url = null)
    {

        $params = RouterHelper::segmentizeUrl($url);

        /*
         * Database check
         */
        if (!App::hasDatabase()) {
            return Config::get('app.debug', false)
                ? Response::make(View::make('backend::no_database'), 200)
                : App::make('Cms\Classes\Controller')->run($url);
        }

        /*
         * Look for a Main controllers
         */
        $innerControllerFound = false;
        $paramClone = $params;
        if (count($paramClone) > 1) {
            $counterClassEmel = count($paramClone) - 1;
            array_walk($paramClone, function (&$value, $index) {
                $value = Str::studly($value);
            });
            for ($i = $counterClassEmel; $i >= 0; $i--) {
                $predicatedClass = '\\HS\\Controllers\\Backend\\' . Str::studly(implode('\\', $paramClone));
                // $predicatedClass . '\Index';
                $predicatedPath = implode('/', $paramClone);
                $predicatedPath = base_path() . '/controllers/Backend/' . $predicatedPath;

                // @to solve url issues with camlisation
                if ($controllerFile = File::existsInsensitive($predicatedPath . '.php')) {
                    include_once($controllerFile);
                }

                if (class_exists($predicatedClass)) {
                    $predicatedControllerParams = array_slice($params, $i + 1);
                    $backUpParams = $predicatedControllerParams;
                    $predicatedAction = array_shift($predicatedControllerParams);
                    if (empty($predicatedAction)) {
                        $predicatedAction = 'index';
                    }
                    $controllerObj = App::make($predicatedClass);
                    // if action not found then set index action and pass as params
                    if(!$controllerObj->actionExists($predicatedAction)) {
                        // restore params and set default action to index
                        $predicatedAction = 'index';
                        $predicatedControllerParams = $backUpParams;
                    }
                    $innerControllerFound = true;
                    break;
                }

                unset($paramClone[$i]);
            }
        }


        $controllerClassPath = base_path().'/controllers/backend';
        if ($innerControllerFound) {
            $controller = $predicatedClass;
            self::$action = $action = $predicatedAction;
            self::$params = $controllerParams = $predicatedControllerParams;
            $controllerClass = $controller;
            $controllerClassPath = $predicatedPath;
        } else {
            $controller = isset($params[0]) ? $params[0] : '';
            self::$action = $action = isset($params[1]) ? $this->parseAction($params[1]) : 'index';
            self::$params = $controllerParams = array_slice($params, 2);
            $controllerClass = '\\HS\\Controllers\\Backend\\'. Str::studly($controller);
        }

        if ($controllerObj = $this->findController(
            $controllerClass,
            $action,
            $controllerClassPath
        )) {
            return $controllerObj->run($action, $controllerParams);
        }


        /*
         * Fall back to Default index controller
         */
        $controller = 'Index';
        self::$action = $action = 'index';
        self::$params = $controllerParams = [];
        $controllerClass = '\\HS\\Controllers\\Backend\\'. Str::studly($controller);
        if ($controllerObj = $this->findController (
            $controllerClass,
            $action,
            base_path().'/controllers/backend'
        )) {
            return $controllerObj->run($action, $controllerParams);
        }


        throw new Exception("No Default(Index) Controller Found.", 1);
    }

    /**
     * This method is used internally.
     * Finds a backend controller with a callable action method.
     * @param string $controller Specifies a method name to execute.
     * @param string $action Specifies a method name to execute.
     * @param string $inPath Base path for class file location.
     * @return ControllerBase Returns the backend controller object
     */
    protected function findController($controller, $action, $inPath)
    {

        /*
         * Workaround: Composer does not support case insensitivity.
         */
        if (!class_exists($controller)) {
            $controller = Str::normalizeClassName($controller);
            $controllerFile = $inPath.strtolower(str_replace('\\', '/', $controller)) . '.php';
            if ($controllerFile = File::existsInsensitive($controllerFile)) {
                include_once($controllerFile);
            }
        }
        if (!class_exists($controller)) {
            return false;
        }

        $controllerObj = App::make($controller);

        if ($controllerObj->actionExists($action)) {
            return $controllerObj;
        }

        return false;
    }

    /**
     * Process the action name, since dashes are not supported in PHP methods.
     * @param  string $actionName
     * @return string
     */
    protected function parseAction($actionName)
    {
        if (strpos($actionName, '-') !== false) {
            return camel_case($actionName);
        }

        return $actionName;
    }
}
