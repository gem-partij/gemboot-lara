<?php

namespace Gemboot\Commands;

use Illuminate\Console\GeneratorCommand;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeController extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'gemboot:make-controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Gemboot controller class';

    /**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Controller';

    /**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
        $stubname = 'controller.stub';

        if($this->option('model') && $this->option('service')) {
            $stubname = $this->option('resource') ? 'controller.rest.resource.stub' : 'controller.rest.stub';
        }
        elseif($this->option('model')) {
            $stubname = $this->option('resource') ? 'controller.model.resource.stub' : 'controller.model.stub';
        }

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
		return $this->option('resource') ? $rootNamespace . '\Http\Controllers\Api\Resources' : $rootNamespace . '\Http\Controllers\Api';
	}

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in the base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $controllerNamespace = $this->getNamespace($name);

        $replace = [];

        if ($this->option('model')) {
            $replace = $this->buildModelReplacements($replace);
        }

        if ($this->option('service')) {
            $replace = $this->buildServiceReplacements($replace);
        }

        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildModelReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->option('model'));

        if (! class_exists($modelClass)) {
            if ($this->confirm("A {$modelClass} model does not exist. Do you want to generate it?", true)) {
                $this->call('gemboot:make-model', ['name' => $modelClass]);
            }
        }

        return array_merge($replace, [
            'DummyFullModelClass' => $modelClass,
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
        ]);
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Build the service replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildServiceReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->option('model'));
        $serviceClass = $this->parseService($this->option('service'));

        if (! class_exists($serviceClass)) {
            if ($this->confirm("A {$serviceClass} service does not exist. Do you want to generate it?", true)) {
                $this->call('gemboot:make-service', [
                    'name' => $serviceClass,
                    'model' => class_basename($modelClass),
                    '--resource' => $this->option('resource'),
                ]);
            }
        }

        return array_merge($replace, [
            'DummyFullServiceClass' => $serviceClass,
            '{{ namespacedService }}' => $serviceClass,
            '{{namespacedService}}' => $serviceClass,
            'DummyServiceClass' => class_basename($serviceClass),
            '{{ service }}' => class_basename($serviceClass),
            '{{service}}' => class_basename($serviceClass),
            'DummyServiceVariable' => lcfirst(class_basename($serviceClass)),
            '{{ serviceVariable }}' => lcfirst(class_basename($serviceClass)),
            '{{serviceVariable}}' => lcfirst(class_basename($serviceClass)),
        ]);
    }

    /**
     * Get the fully-qualified service class name.
     *
     * @param  string  $service
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseService($service)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $service)) {
            throw new InvalidArgumentException('Service name contains invalid characters.');
        }

        return $this->qualifyService($service);
    }

    /**
     * Qualify the given service class base name.
     *
     * @param  string  $service
     * @return string
     */
    protected function qualifyService(string $service)
    {
        $service = ltrim($service, '\\/');

        $service = str_replace('/', '\\', $service);

        $rootNamespace = $this->rootNamespace();

        return $rootNamespace.'Services\\'.$service;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the controller already exists'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Generate a resource controller for the given model.'],
            ['service', 's', InputOption::VALUE_REQUIRED, 'Generate a resource controller for the given service.'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller class.'],
        ];
    }
}
