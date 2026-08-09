<?php

namespace LaravelCap;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaravelCap\Http\Controllers\CapFrameController;
use LaravelCap\Middleware\VerifyCap;

class CapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cap.php', 'cap');

        $this->app->singleton(Cap::class, function ($app) {
            return new Cap(
                http: $app->make(HttpFactory::class),
                config: $app->make('config')->get('cap'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'cap');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cap');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cap.php' => config_path('cap.php'),
            ], 'cap-config');

            $this->publishes([
                __DIR__ . '/../resources/js/cap-widget.js'         => public_path('vendor/cap/cap-widget.js'),
                __DIR__ . '/../resources/css/cap-widget.css'        => public_path('vendor/cap/cap-widget.css'),
                __DIR__ . '/../resources/wasm/cap_wasm_bg.wasm'     => public_path('vendor/cap/cap_wasm_bg.wasm'),
                __DIR__ . '/../resources/wasm/cap_wasm.js'          => public_path('vendor/cap/cap_wasm.js'),
            ], 'cap-assets');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/cap'),
            ], 'cap-lang');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/cap'),
            ], 'cap-views');
        }

        $this->registerRoute();
        $this->registerMiddleware();
        $this->registerBladeDirectives();
    }

    private function registerRoute(): void
    {
        Route::get(
            config('cap.frame_route', 'cap-frame'),
            CapFrameController::class
        )->name('cap.frame');
    }

    private function registerMiddleware(): void
    {
        $this->app->make('router')->aliasMiddleware('cap.verify', VerifyCap::class);
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('cap', function (string $expression) {
            if (empty(trim($expression))) {
                return "<?php echo '<cap-widget data-cap-api-endpoint=\"' . e(config('cap.endpoint')) . '\"></cap-widget>'; ?>";
            }
            return "<?php echo '<cap-widget data-cap-api-endpoint=\"' . e(config('cap.endpoint')) . '\" data-cap-csp-nonce=\"' . e({$expression}) . '\"></cap-widget>'; ?>";
        });

        Blade::directive('capScripts', function (string $expression) {
            if (empty(trim($expression))) {
                return "<?php echo '<script>window.CAP_CUSTOM_WASM_URL=' . json_encode(asset('vendor/cap/cap_wasm_bg.wasm')) . '</script>' . '<script type=\"module\" src=\"' . e(asset('vendor/cap/cap-widget.js')) . '\"></script>'; ?>";
            }
            return "<?php echo '<script nonce=\"' . e({$expression}) . '\">window.CAP_CUSTOM_WASM_URL=' . json_encode(asset('vendor/cap/cap_wasm_bg.wasm')) . ';window.CAP_SCRIPT_NONCE=' . json_encode({$expression}) . ';</script>' . '<script type=\"module\" nonce=\"' . e({$expression}) . '\" src=\"' . e(asset('vendor/cap/cap-widget.js')) . '\"></script>'; ?>";
        });

        Blade::directive('capFrame', function (string $expression) {
            if (empty(trim($expression))) {
                return <<<HTML
                <?php
                \$_capTokenField = e(config('cap.token_field', 'cap-token'));
                \$_capFrameSrc   = e(route('cap.frame'));
                echo '<input type="hidden" name="' . \$_capTokenField . '" id="cap-frame-token">'
                   . '<iframe src="' . \$_capFrameSrc . '" id="cap-frame"'
                   . ' style="width:0;height:0;border:0;overflow:hidden;"'
                   . ' title="Cap CAPTCHA" aria-hidden="true"></iframe>'
                   . '<script>'
                   . '(function(){'
                   . 'window.addEventListener(\'message\',function(e){'
                   . 'if(e.origin!==window.location.origin)return;'
                   . 'var _f=document.getElementById(\'cap-frame\');'
                   . 'if(!_f||e.source!==_f.contentWindow)return;'
                   . 'if(!e.data||e.data.type!==\'cap:token\')return;'
                   . 'document.getElementById(\'cap-frame-token\').value=e.data.token;'
                   . '});'
                   . 'window.capSolve=function(){'
                   . 'document.getElementById(\'cap-frame\').contentWindow'
                   . '.postMessage({type:\'cap:start\'},window.location.origin);'
                   . '};'
                   . '})();'
                   . '</script>';
                ?>
                HTML;
            }

            return <<<PHP
            <?php
            \$_capTokenField = e(config('cap.token_field', 'cap-token'));
            \$_capFrameSrc   = e(route('cap.frame'));
            \$_capNonce      = e({$expression});
            echo '<input type="hidden" name="' . \$_capTokenField . '" id="cap-frame-token">'
               . '<style nonce="' . \$_capNonce . '">#cap-frame{width:0;height:0;border:0;overflow:hidden;}</style>'
               . '<iframe src="' . \$_capFrameSrc . '" id="cap-frame"'
               . ' title="Cap CAPTCHA" aria-hidden="true"></iframe>'
               . '<script nonce="' . \$_capNonce . '">'
               . '(function(){'
               . 'window.addEventListener(\'message\',function(e){'
               . 'if(e.origin!==window.location.origin)return;'
               . 'var _f=document.getElementById(\'cap-frame\');'
               . 'if(!_f||e.source!==_f.contentWindow)return;'
               . 'if(!e.data||e.data.type!==\'cap:token\')return;'
               . 'document.getElementById(\'cap-frame-token\').value=e.data.token;'
               . '});'
               . 'window.capSolve=function(){'
               . 'document.getElementById(\'cap-frame\').contentWindow'
               . '.postMessage({type:\'cap:start\'},window.location.origin);'
               . '};'
               . '})();'
               . '</script>';
            ?>
            PHP;
        });

        Blade::directive('capStyles', function () {
            return "<?php echo '<link rel=\"stylesheet\" href=\"' . e(asset('vendor/cap/cap-widget.css')) . '\">'; ?>";
        });

        Blade::directive('capConfig', function (string $expression) {
            if (empty(trim($expression))) {
                return "<?php echo '<script>'
                    . 'window.CAP_API_ENDPOINT=' . json_encode(config('cap.endpoint')) . ';'
                    . 'window.CAP_TOKEN_FIELD=' . json_encode(config('cap.token_field', 'cap-token')) . ';'
                    . '</script>'; ?>";
            }
            return "<?php echo '<script nonce=\"' . e({$expression}) . '\">'
                . 'window.CAP_API_ENDPOINT=' . json_encode(config('cap.endpoint')) . ';'
                . 'window.CAP_TOKEN_FIELD=' . json_encode(config('cap.token_field', 'cap-token')) . ';'
                . '</script>'; ?>";
        });
    }
}
