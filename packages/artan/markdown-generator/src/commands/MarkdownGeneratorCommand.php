<?php

namespace Artan\markdownGenerator\Commands;

use Illuminate\Console\Command;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Config;
use Illuminate\Support\Collection;

class MarkdownGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'markdown:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate markdown files for controllers, models and migrations';

    protected $controllers_path;
    protected $models_path;
    protected $migrations_path;
    protected $markdown_path;
    protected $tags;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->markdown_path    = Config::get('markdown-generator.markdown_path');
        $this->tags             = collect(Config::get('markdown-generator.tags'));
        $this->controllers_path  = app_path('Http/Controllers/');
        $this->models_path      = app_path();
        //$this->migrations_path  = app_path('Http/Controllers/');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $collections = $this->init();
        $this->markdownControllers($collections->get('controllers'));
        $this->info('Complete generating controllers.');
        $this->markdownModels($collections->get('models'));
        $this->info('Complete generating models.');
    }

    /**
     * Create markdown directory & generate controllers, models and tables lists
     *
     * @return Illuminate\Support\Collection
     */
    private function init()
    {
        $this->info('init markdown folder');
        if(file_exists($this->markdown_path)){
            $this->removeDirectory($this->markdown_path);
            mkdir($this->markdown_path, 0777, true);
        }else{
            mkdir($this->markdown_path, 0777, true);
        }   

        return collect([
                "controllers"   => $this->getControllersList(),
                "models"   => $this->getModelsList(),
                "database"   => $this->getDatabaseList(),
            ]);
    }

    /**
     * Remove path directory.
     *
     * @return mixed
     */
    private function removeDirectory($path)
    {
        $files = glob($path . '/*');

        foreach ($files as $file) {
            is_dir($file) ? removeDirectory($file) : unlink($file);
        }

        rmdir($path);
        
        return;
    }

    /**
     * Get controllers
     *
     * @return Collection
     */
    private function getControllersList()
    {
        $process = new Process('ls app/Http/Controllers | grep .php');
        $process->run();

		if (!$process->isSuccessful()) {
		    throw new ProcessFailedException($process);
        }
        
        return collect(explode("\n", $process->getOutput()))
                                ->reject(function($single){
                                    return empty($single) || $single == "Controller.php";
                                });
    }

    /**
     * TODO
     *
     * @return void
     */
    private function getDatabaseList()
    {
        //
    }

    /**
     * Generate controllers markdown files
     *
     * @param Collection $controllers
     * @return void
     */
    private function markdownControllers(Collection $controllers)
    {
        $this->info('Generating controllers :');

        foreach ($controllers as $controller) {
            $single_controller = $this->scanFile($controller, 'controllers');

            $this->createControllerMarkdownFile($controller, $single_controller);
        }
    }

    /**
     * Retrieve comments
     *
     * @param File $file
     * @param String $file_type
     * @return String
     */
    private function scanFile($file, $file_type = "controllers")
    {
        $output = new Collection;

        $this->comment('Generating markdown for ' . $file);

        if($file_type == 'controllers'){
            $content = file($this->controllers_path . $file);
        }elseif ($file_type == 'models') {
            $content = file($this->models_path .'\\'. $file);            
        }else {
            /**TODO : migrations */
        }
        
        $comment = false;
        $function = false;
        
        foreach ($content as $line) {
            
            $line = trim(str_replace("\n", '', $line));
            
            if(strpos($line, '/*') !== false){
                $singleFunction = new \stdClass();
                $comment = true;
            }

            if(strpos($line, '*/') !== false){
                $comment = false;
                $function = true;
            }

            if(!$comment && $function && strpos($line, 'function') !== false){
                
                $preg = preg_match_all('/(?<=(function))(\s\w*)/',$line,$matches);
                $singleFunction->function_signature = str_replace('{', '', $line);
                $singleFunction->function_name = $matches[0][0];
                $output->push($singleFunction);
            }

            if($comment){
               $line = str_replace(['*', '/'], '', $line);

               if($line){
                    $result = $this->analyseLine($line);
                    $key = array_key_first($result);
                    $singleFunction->$key[] = $result[$key];
               }
            }
        }

        return $output;
    }

    /**
     * tag line
     *
     * @param String $line
     * @return Array
     */
    private function analyseLine($line)
    {
        
        if(strpos($line, '@') !== false){
            $startIndex = strpos($line, '@');

            $paramEndingIndex = strpos($line, ' ', $startIndex);
            $param = str_replace('@', '', substr($line, $startIndex, $paramEndingIndex - $startIndex));

            $value = str_replace('@' . $param, '', $line);

            if($this->tags->contains($param)){
                return [
                    $param  => $value
                ];
            }

        }

        return [
            "description"   => $line
        ];

    }

    /**
     * Undocumented function
     *
     * @param String $controller_name
     * @param String $controller
     * @return void
     */
    private function createControllerMarkdownFile($controller_name, $controller)
    {
        $controller_name = str_replace('.php', '', $controller_name);
        $file_path = $this->markdown_path . "\\{$controller_name}.md";
        
        file_put_contents($file_path, "# {$controller_name}".PHP_EOL);
        
        foreach ($controller as $single_function) {
            $function_name = trim($single_function->function_name);
            file_put_contents($file_path, "- [{$function_name}](#{$function_name})".PHP_EOL , FILE_APPEND | LOCK_EX);
        }
        
        foreach ($controller as $single_function) {
            
            $function_name = trim($single_function->function_name);
            
            file_put_contents($file_path, "<a name='{$function_name}'></a>".PHP_EOL , FILE_APPEND | LOCK_EX);
            file_put_contents($file_path, "## {$function_name}".PHP_EOL , FILE_APPEND | LOCK_EX);
            
            foreach ($single_function as $key => $value) {
                if($key == 'function_name'){
                    file_put_contents($file_path, "### function name".PHP_EOL , FILE_APPEND | LOCK_EX);
                }else if($key == 'function_signature'){
                    file_put_contents($file_path, "### function signature".PHP_EOL , FILE_APPEND | LOCK_EX);
                }else{
                    file_put_contents($file_path, "### {$key}".PHP_EOL , FILE_APPEND | LOCK_EX);
                }

                foreach ((array) $value as $single_value) {
                    file_put_contents($file_path, "   {$single_value}".PHP_EOL , FILE_APPEND | LOCK_EX);
                }
            }
        }
    }

    /**
     * TODO : Models now
     */

    /**
     * Create Models list
     *
     * @return Collection
     */
    private function getModelsList()
    {
        $process = new Process('ls app | grep .php');
        $process->run();

		if (!$process->isSuccessful()) {
		    throw new ProcessFailedException($process);
        }
        
        return collect(
            explode("\n", $process->getOutput()))
                ->reject(function($single){
                    return empty($single);
            });
    }

    /**
     * Generate markdown files for each model
     *
     * @param Collection $models
     * @return void
     */
    private function markdownModels(Collection $models)
    {
        $this->info('Generating models :');
        
        foreach ($models as $model) {
            $single_model = $this->scanFile($model, 'models');

            $this->createModelMarkdownFile($model, $single_model);
        }
    }

    /**
     * TODO : single model
     *
     * @return void
     */
    private function scanSingleModel($model)
    {
        //
    }

    /**
     * Create markdown file for a single model
     *
     * @param String $model_name
     * @param String $model
     * @return void
     */
    private function createModelMarkdownFile($model_name, $model)
    {
        $model_name = str_replace('.php', '', $model_name);
        $file_path = $this->markdown_path . "\\{$model_name}.md";
        
        file_put_contents($file_path, "# {$model_name}".PHP_EOL);
        
        foreach ($model as $single_function) {
            $function_name = trim($single_function->function_name);
            file_put_contents($file_path, "- [{$function_name}](#{$function_name})".PHP_EOL , FILE_APPEND | LOCK_EX);
        }
        
        foreach ($model as $single_function) {
            
            $function_name = trim($single_function->function_name);
            
            file_put_contents($file_path, "<a name='{$function_name}'></a>".PHP_EOL , FILE_APPEND | LOCK_EX);
            file_put_contents($file_path, "## {$function_name}".PHP_EOL , FILE_APPEND | LOCK_EX);
            
            foreach ($single_function as $key => $value) {
                if($key == 'function_name'){
                    file_put_contents($file_path, "### function name".PHP_EOL , FILE_APPEND | LOCK_EX);
                }else if($key == 'function_signature'){
                    file_put_contents($file_path, "### function signature".PHP_EOL , FILE_APPEND | LOCK_EX);
                }else{
                    file_put_contents($file_path, "### {$key}".PHP_EOL , FILE_APPEND | LOCK_EX);
                }

                foreach ((array) $value as $single_value) {
                    file_put_contents($file_path, "   {$single_value}".PHP_EOL , FILE_APPEND | LOCK_EX);
                }
            }
        }
    }
}

