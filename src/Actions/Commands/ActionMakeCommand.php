<?php

namespace PerfectOblivion\ActionServiceResponder\Actions\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ActionMakeCommand extends GeneratorCommand
{
    /** @var string */
    protected $signature = 'asr:action {name} {--auto-service} {--responder}';

    /** @var string */
    protected $description = 'Create a new action';

    /** @var string */
    protected $type = 'Action';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/action.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getMethodName(): string
    {
        return Config::get('asr.action_method', '__invoke');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\'.Config::get('asr.action_namespace', '');
    }

    /**
     * Get the desired class name from the input.
     */
    protected function getNameInput(): string
    {
        $input = $input = Str::studly(trim($this->argument('name')));
        $suffix = Config::get('asr.action_suffix');

        if (Config::get('asr.action_override_duplicate_suffix')) {
            return Str::finish($input, $suffix);
        }

        return $input.$suffix;
    }

    /**
     * Get the full service class name from the input.
     */
    protected function getServiceImportReplacement(): string
    {
        $input =  Str::studly(trim($this->argument('name')));
        $suffix = Config::get('asr.service_suffix');

        if (Config::get('asr.service_override_duplicate_suffix')) {
            $serviceName = Str::finish($input, $suffix);
        } else {
            $serviceName = $input.$suffix;
        }

        $serviceRootNamespace = Config::get('asr.service_namespace');

        return trim($this->rootNamespace(), '\\').'\\'.$serviceRootNamespace.'\\'.$serviceName;
    }

    /**
     * Get the full responder class name from the input.
     */
    protected function getResponderImportReplacement(): string
    {
        $input =  Str::studly(trim($this->argument('name')));
        $suffix = Config::get('asr.responder_suffix');

        if (Config::get('asr.responder_override_duplicate_suffix')) {
            $responderName = Str::finish($input, $suffix);
        } else {
            $responderName = $input.$suffix;
        }

        $responderRootNamespace = Config::get('asr.responder_namespace');

        return trim($this->rootNamespace(), '\\').'\\'.$responderRootNamespace.'\\'.$responderName;
    }

    /**
     * Get the service class name from the input.
     */
    protected function getServiceParameterReplacement(): string
    {
        $classname = class_basename($this->getServiceImportReplacement());

        return "{$classname} \$service, ";
    }

    /**
     * Get the responder property.
     */
    protected function getResponderPropertyReplacement(): string
    {
        $fullClassname = $this->getResponderImportReplacement();
        $classname = class_basename($fullClassname);
        $action = class_basename($this->getNameInput());

        return "    /** @var \\{$fullClassname} **/\n    private \$responder;\n\n"
            ."    /**\n"
            ."     * Construct a new {$action}.\n"
            ."     *\n"
            ."     * @param  \\{$fullClassname}  \$responder\n"
            ."     */\n"
            ."    public function __construct({$classname} \$responder)\n"
            ."    {\n"
            ."        \$this->responder = \$responder;\n"
            ."    }\n";
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceMethod($stub)->replaceService($stub)->replaceResponder($stub)->replaceClass($stub, $name);
    }

    /**
     * Replace the method name in the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceMethod(&$stub): ActionMakeCommand
    {
        $stub = str_replace(
            ['DummyMethod'],
            [$this->getMethodName()],
            $stub
        );

        return $this;
    }

    /**
     * Replace the service in the given stub.
     *
     * @param  string  $stub
     */
    protected function replaceService(&$stub): ActionMakeCommand
    {
        $stub = str_replace(
            ['DummyServiceFullNamespace', 'DummyServiceDocBlock', 'DummyServiceParameter'],
            [
                $this->option('auto-service') ? 'use '.$this->getServiceImportReplacement().';' : '',
                $this->option('auto-service') ? '\\'.$this->getServiceImportReplacement().'  $service' : '',
                $this->option('auto-service') ? $this->getServiceParameterReplacement() : '',
            ],
            $stub
        );

        return $this;
    }

    /**
     * Replace the responder in the given stub.
     *
     * @param  string  $stub
     */
    protected function replaceResponder(&$stub): ActionMakeCommand
    {
        $stub = str_replace(
            ['DummyResponderFullNamespace', 'DummyResponderProperty'],
            [
                $this->option('responder') ? 'use '.$this->getResponderImportReplacement().';' : '',
                $this->option('responder') ? $this->getResponderPropertyReplacement() : '',
            ],
            $stub
        );

        return $this;
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string
    {
        $replaced = str_replace('DummyClass', str_replace($this->getNamespace($name).'\\', '', $name), $stub);

        $linesRemoved = $this->removeUnnecessaryBlankLines($replaced);

        return $this->removeLinesThatMatch("     *  @param  \r", $linesRemoved);
    }

    /**
     * Replace multi-blank lines with single blank line.
     *
     * @param  string  $stub
     */
    private function removeUnnecessaryBlankLines(string $stub): string
    {
        $text = explode(PHP_EOL, $stub);
        $deleted = [];
        foreach ($text as $lineNumber => $line) {
            if ($this->lineShouldntBeFollowedByBlank($line, $text, $lineNumber)) {
                if ($text[$lineNumber+1] == "\r") {
                    $deleted[] = $lineNumber+1;
                }
            }
        }
        foreach ($deleted as $remove) {
            unset($text[$remove]);
        }

        return implode(PHP_EOL, $text);
    }

    /**
     * Replace lines that match the given pattern.
     *
     * @param  string  $pattern
     * @param  string  $stub
     */
    private function removeLinesThatMatch(string $pattern, string $stub): string
    {
        $text = explode(PHP_EOL, $stub);
        $deleted = [];
        foreach ($text as $lineNumber => $line) {
            if ($line == $pattern) {
                $deleted[] = $lineNumber;
            }
        }
        foreach ($deleted as $remove) {
            unset($text[$remove]);
        }

        return implode(PHP_EOL, $text);
    }

    /**
     * Determine if a line should not be followed by a blank line.
     *
     * @param  string  $line
     * @param  array  $text
     * @param  int  $lineNumber
     */
    private function lineShouldntBeFollowedByBlank(string $line, array $text, int $lineNumber): bool
    {
        return $line == "\r" || $line == "{\r" || (Str::startsWith($line, 'use') && Str::startsWith($text[$lineNumber+2], 'use'));
    }
}
