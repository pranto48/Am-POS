<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Module;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ModulesController extends Controller
{
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        //Get list of all modules.
        $modules = Module::toCollection()->toArray();
        //print_r($modules);exit;

        foreach ($modules as $module => $details) {
            $modules[$module]['is_installed'] = $this->moduleUtil->isModuleInstalled($details['name']) ? true : false;

            //Get version information.
            if ($modules[$module]['is_installed']) {
                $modules[$module]['version'] = $this->moduleUtil->getModuleVersionInfo($details['name']);
            }

            //Install Link.
            try {
                $modules[$module]['install_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@index');
            } catch (\Exception $e) {
                $modules[$module]['install_link'] = '#';
            }

            //Update Link.
            try {
                $modules[$module]['update_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@update');
            } catch (\Exception $e) {
                $modules[$module]['update_link'] = '#';
            }

            //Uninstall Link.
            try {
                $modules[$module]['uninstall_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@uninstall');
            } catch (\Exception $e) {
                $modules[$module]['uninstall_link'] = '#';
            }
        }

        $is_demo = (config('app.env') == 'demo');
        $mods = $this->__available_modules();
        
        return view('install.modules.index')
            ->with(compact('modules', 'is_demo', 'mods'));

        //Option to uninstall

        //Option to activate/deactivate

        //Upload module.
    }

    public function regenerate()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            Artisan::call('module:publish');
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Activate/Deaactivate the specified module.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $module = Module::find($module_name);

            //php artisan module:disable Blog
            if ($request->action_type == 'activate') {
                $module->enable();
            } elseif ($request->action_type == 'deactivate') {
                $module->disable();
            }
            // Publish assets for this specific module after status change
            Artisan::call('module:publish', ['module' => $module_name, '--force' => true]);

            // Clear module assets cache when module is activated/deactivated
            Cache::forget('module_assets');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Deletes the module.
     *
     * @param  string  $module_name
     * @return \Illuminate\Http\Response
     */
    public function destroy($module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $module = Module::find($module_name);
            // $module->delete();

            $path = $module->getPath();

            // Clear module assets cache when module is deleted
            Cache::forget('module_assets');

            die("To delete the module delete this folder <br/>" . $path . '<br/> Go back after deleting');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Upload the module.
     */
    public function uploadModule(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $request->validate([
                'module' => 'required|file|mimes:zip|max:10240', // 10MB max
            ]);

            //get zipped file
            $module = $request->file('module');
            $module_name = Str::slug(str_replace('.zip', '', $module->getClientOriginalName()));

            //check if 'Modules' folder exist or not, if not exist create
            $path = '../Modules';
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            //extract the zipped file in given path
            $zip = new ZipArchive();
            if ($zip->open($module) === true) {
                $zip->extractTo($path.'/');
                $zip->close();

                // Check for required files after extraction
                $module_dir = $path . '/' . $module_name;
                $data_controller_path = $module_dir . '/Http/Controllers/DataController.php';
                if (!(file_exists($module_dir . '/composer.json')
                    && file_exists($module_dir . '/module.json')
                    && file_exists($module_dir . '/Config/config.php')
                    && file_exists($data_controller_path))
                ) {
                    \File::deleteDirectory($module_dir);
                    $output = ['success' => false,
                        'msg' => __('messages.something_went_wrong'),

                        // 
                    ];
                    return redirect()->back()->with(['status' => $output]);
                }

                // Clear module assets cache when new module is uploaded
                Cache::forget('module_assets');

                // Publish assets for the uploaded module using its name
                try {
                    Artisan::call('module:publish', ['module' => $module_name, '--force' => true]);
                } catch (\Throwable $e) {
                    // Fallback to publishing all if targeted signature not supported
                    Artisan::call('module:publish');
                }
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function __available_modules()
    {
        $modules = [
            (object)[
                'n' => 'Essentials',
                'dn' => 'Essentials Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Essentials features for every growing businesses.'
            ],
            (object)[
                'n' => 'Superadmin',
                'dn' => 'Superadmin Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Turn your POS to SaaS application and start earning by selling subscriptions'
            ],
            (object)[
                'n' => 'Woocommerce',
                'dn' => 'Woocommerce Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Sync your Woocommerce store with POS'
            ],
            (object)[
                'n' => 'Manufacturing',
                'dn' => 'Manufacturing Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Manufacture products from raw materials, organise recipe & ingredients'
            ],
            (object)[
                'n' => 'Project',
                'dn' => 'Project Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Manage Projects, tasks, tasks time logs, activities and much more.'
            ],
            (object)[
                'n' => 'Repair',
                'dn' => 'Repair Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Repair module helps with complete repair service management of electronic goods like Cellphone, Computers, Desktops, Tablets, Television, Watch, Wireless devices, Printers, Electronic instruments and many more similar devices which you can imagine!'
            ],
            (object)[
                'n' => 'Crm',
                'dn' => 'CRM Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Customer relationship management module'
            ],
            (object)[
                'n' => 'ProductCatalogue',
                'dn' => 'ProductCatalogue',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Digital Product catalogue Module'
            ],
            (object)[
                'n' => 'Accounting',
                'dn' => 'Accounting Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Accounting & Book keeping module for UltimatePOS'
            ],
            (object)[
                'n' => 'AiAssistance',
                'dn' => 'AiAssistance Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'AI Assistant module for UltimatePOS. This module used openAI API to help with in copywriting & reporting'
            ],
            (object)[
                'n' => 'AssetManagement',
                'dn' => 'AssetManagement Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Useful for managing all kinds of assets.'
            ],
            (object)[
                'n' => 'Cms',
                'dn' => 'Cms Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Mini CMS (content management system) Module for UltimatePOS to help manage all frontend contents like Landing page, Blogs, Contact us & many other pages.'
            ],
            (object)[
                'n' => 'Connector',
                'dn' => 'Connector/API Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Provide the API for POS.'
            ],
            (object)[
                'n' => 'Gym',
                'dn' => 'Gym Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Gym Management module for UltimatePOS'
            ],
            (object)[
                'n' => 'Hms',
                'dn' => 'Hotel Management Module',
                'u' => 'https://portal.itsupport.com.bd/',
                'd' => 'Hotel Management System module for UltimatePOS, provides features for room bookings, extras, coupons & related features'
            ]
        ];

        return serialize($modules);
    }
}
