<?php

namespace Gemboot\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeModel extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'gemboot:make-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Gemboot model class';

    /**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Model';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return false;
        }

        if ($this->option('all')) {
            $this->input->setOption('controller', true);
            $this->input->setOption('service', true);
            $this->input->setOption('resource', true);
        }

        if($this->option('service')) {
            $this->createService();
        }

        if ($this->option('controller') || $this->option('resource')) {
            $this->createController();
        }
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $service = $controller;

        $modelName = $this->qualifyClass($this->getNameInput());

        $this->call('gemboot:make-controller', array_filter([
            'name'  => "{$controller}Controller",
            '--model' => $modelName,
            '--service' => "{$service}Service",
            '--resource' => $this->option('resource'),
        ]));
    }

    /**
     * Create a service for the model.
     *
     * @return void
     */
    protected function createService()
    {
        $service = Str::studly(class_basename($this->argument('name')));

        $modelName = $this->qualifyClass($this->getNameInput());

        $this->call('gemboot:make-service', array_filter([
            'name'  => "{$service}Service",
            'model' => $modelName,
            '--resource' => $this->option('resource'),
        ]));
    }

    /**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
        $stubname = 'model.stub';
		return __DIR__ . '/../../stubs/' . $stubname;
	}

    /**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
        return is_dir(app_path('Models')) ? $rootNamespace.'\\Models' : $rootNamespace;
	}

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a service and resource controller for the model'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model'],
            ['service', 's', InputOption::VALUE_NONE, 'Generate a new service for the model.'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller'],
        ];
    }
}
