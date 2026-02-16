<?php

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Generators\ModuleGenerator;
use Illuminate\Console\Command;

class MakeModuleCommand extends Command
{
    protected $signature = 'aztec:make-module {name : The name of the module}';

    protected $description = 'Create a new module';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (file_exists(base_path('Modules/' . $name))) {
            if (!$this->confirm("Module [{$name}] already exists. This will overwrite existing files. Do you want to continue?", false)) {
                $this->info("Action cancelled.");
                return self::SUCCESS;
            }
        }

        $this->info("Creating module: {$name}...");

        $generator = new ModuleGenerator($name);
        $generator->generate();
        
        $this->registerServiceProvider($name);

        $this->info("Module [{$name}] created successfully.");

        return self::SUCCESS;
    }

    protected function registerServiceProvider(string $moduleName): void
    {
        $providersFile = base_path('bootstrap/providers.php');
        
        if (!file_exists($providersFile)) {
            return;
        }

        $namespace = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
        
        $content = file_get_contents($providersFile);
        
        // Simple check to avoid duplication
        if (str_contains($content, $namespace)) {
            return;
        }

        // Use AST parser to safely insert into array
        $parser = (new \PhpParser\ParserFactory)->createForHostVersion();
        
        try {
            $ast = $parser->parse($content);
            $traverser = new \PhpParser\NodeTraverser();
            $visitor = new class($namespace) extends \PhpParser\NodeVisitorAbstract {
                private $namespace;
                public function __construct($namespace) { $this->namespace = $namespace; }
                
                public function leaveNode(\PhpParser\Node $node) {
                    if ($node instanceof \PhpParser\Node\Stmt\Return_ 
                        && $node->expr instanceof \PhpParser\Node\Expr\Array_) {
                        
                        $node->expr->items[] = new \PhpParser\Node\Expr\ArrayItem(
                            new \PhpParser\Node\Expr\ClassConstFetch(
                                new \PhpParser\Node\Name($this->namespace),
                                'class'
                            )
                        );
                        return \PhpParser\NodeVisitor::STOP_TRAVERSAL;
                    }
                }
            };
            
            $traverser->addVisitor($visitor);
            $modifiedAst = $traverser->traverse($ast);
            
            $prettyPrinter = new \PhpParser\PrettyPrinter\Standard();
            $newContent = $prettyPrinter->prettyPrintFile($modifiedAst);
            
            file_put_contents($providersFile, $newContent);
            $this->info("Registered ServiceProvider in bootstrap/providers.php");
            
        } catch (\Exception $e) {
            $this->warn("Could not register ServiceProvider automatically: " . $e->getMessage());
        }
    }
}
