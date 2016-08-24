<?php namespace Barryvdh\Elfinder;

use Barryvdh\Elfinder\Session\LaravelSession;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory;
use League\Flysystem\Filesystem;
use File;

class ElfinderController extends Controller
{
    protected $package = 'elfinder';

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function showIndex()
    {
        return $this->app['view']
        ->make($this->package . '::elfinder')
        ->with($this->getViewVars());
    }

    public function showTinyMCE()
    {
        return $this->app['view']
        ->make($this->package . '::tinymce')
        ->with($this->getViewVars());
    }

    public function showTinyMCE4()
    {
        return $this->app['view']
        ->make($this->package . '::tinymce4')
        ->with($this->getViewVars());
    }

    public function showCKeditor4()
    {
        return $this->app['view']
        ->make($this->package . '::ckeditor4')
        ->with($this->getViewVars());
    }

    public function showPopup($input_id)
    {
        return $this->app['view']
        ->make($this->package . '::standalonepopup')
        ->with($this->getViewVars())
        ->with(compact('input_id'));
    }

    public function showFilePicker($input_id)
    {
        $type = Request::input('type');
        return $this->app['view']
        ->make($this->package . '::filepicker')
        ->with($this->getViewVars())
        ->with(compact('input_id','type'));
    }

    public function showConnector()
    {
        $roots = $this->app->config->get('elfinder.roots', []);
        if (empty($roots)) {
            $dirs = (array) $this->app['config']->get('elfinder.dir', []);
            foreach ($dirs as $dir) {
                $path = public_path($dir).'/'.\Auth::user()->id;
                $url = url($dir).'/'.\Auth::user()->id;
                if(!File::exists($path ))
                    File::makeDirectory($path);
                $roots[] = [
                    'driver' => 'LocalFileSystem', // driver for accessing file system (REQUIRED)
                    'path' => $path, // path to files (REQUIRED)
                    'URL' => $url, // URL to files (REQUIRED)
                    'accessControl' => $this->app->config->get('elfinder.access'), // filter callback (OPTIONAL)  
                    'tmbPath' => $path . '/_thumbs',  
                    'tmbURL' => $url . '/_thumbs',
                    'tmbSize' => '300px', 
                    'tmbBgColor' => 'transparent',
                    'attributes' => array(
                        array(
                            'pattern' => '~/_thumbs$~',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                            )

                        )              
                    ];
                }

                $disks = (array) $this->app['config']->get('elfinder.disks', []);
                foreach ($disks as $key => $root) {
                    if (is_string($root)) {
                        $key = $root;
                        $root = [];
                    }
                    $disk = app('filesystem')->disk($key);
                    if ($disk instanceof FilesystemAdapter) {
                        $filesystem = $disk->getDriver();
                        if (method_exists($filesystem, 'getAdapter')) {
                            $adapter = $filesystem->getAdapter();
                            if ( ! $adapter instanceof CachedAdapter) {
                                $adapter = new CachedAdapter($adapter, new Memory());
                                $filesystem = new Filesystem($adapter);
                            }
                        }
                        $defaults = [
                        'driver' => 'Flysystem',
                        'filesystem' => $filesystem,
                        'alias' => $key,
                        ];
                        $roots[] = array_merge($defaults, $root);
                    }
                }
            }

            if (app()->bound('session.store')) {
                $sessionStore = app('session.store');
                $session = new LaravelSession($sessionStore);
            } else {
                $session = null;
            }

            $rootOptions = $this->app->config->get('elfinder.root_options', array());
            foreach ($roots as $key => $root) {
                $roots[$key] = array_merge($rootOptions, $root);
            }

            $opts = $this->app->config->get('elfinder.options', array());
            $opts = array_merge($opts, ['roots' => $roots, 'session' => $session]);
            // dump($opts);exit();

        // run elFinder
            $connector = new Connector(new \elFinder($opts));
            $connector->run();
            return $connector->getResponse();
        }

        protected function getViewVars()
        {
            $dir = 'packages/barryvdh/' . $this->package;
            $locale = str_replace("-",  "_", $this->app->config->get('app.locale'));
            if (!file_exists($this->app['path.public'] . "/$dir/js/i18n/elfinder.$locale.js")) {
                $locale = false;
            }
            $csrf = true;
            return compact('dir', 'locale', 'csrf');
        }
    }
