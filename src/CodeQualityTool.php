<?php

namespace GitHooks\src;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Exception;
use Symfony\Component\Yaml\Yaml;

class CodeQualityTool extends Application
{
    private $output;
    private $input;
    private $config;
    private $ignoreFolder;

    const PHP_FILES_IN_SRC = '/(.*)(\.php)$/';
    const TWIG_FILES = '/(\.twig)$/';
    const PROJECT_DIR = __DIR__.'/../../../..';

    public function __construct()
    {
        parent::__construct('Code Quality Tool', '1.0.0');
        $this->initConfig();
    }

    public function initConfig()
    {
        $this->config = Yaml::parse(
            file_get_contents(self::PROJECT_DIR.'/git_hooks.yml')
        );
        $this->ignoreFolder = $this->config['git_hooks']['ignore_folder'] ?? '';
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln('<fg=white;options=bold;bg=red>Code Quality Tool</fg=white;options=bold;bg=red>');
        $output->writeln('<info>Fetching files</info>');
        $files = $this->extractCommitedFiles();

        if (!empty($this->ignoreFolder)) {
            $this->ignoreFolder = '/^('.preg_quote(implode('|', $this->ignoreFolder), '/').')/';
            $files = array_filter($files, function ($a) {
                return !preg_match($this->ignoreFolder, $a);
            });
        }

        $toolsConfig = [
            [
                'isEnabled' => !empty($this->config['git_hooks']['phpLint']),
                'msg' => 'Running PHPLint',
                'function' => 'phpLint',
                'errorMsg' => 'There are some PHP syntax errors!',
                'params' => [],
            ],
            [
                'isEnabled' => !empty($this->config['git_hooks']['phpCsFixer']),
                'msg' => 'Checking code style with php-cs-fixer',
                'function' => 'codeStyle',
                'errorMsg' => 'There are coding standards violations!',
                'params' => [],
            ],
            [
                'isEnabled' => !empty($this->config['git_hooks']['phpCs']),
                'msg' => 'Checking code style with PHPCS',
                'function' => 'codeStylePsr',
                'errorMsg' => 'There are PHPCS coding standards violations!',
                'params' => [],
            ],
            [
                'isEnabled' => !empty($this->config['git_hooks']['phpMd']),
                'msg' => 'Checking code mess with PHPMD',
                'function' => 'phPmd',
                'errorMsg' => 'There are PHPMD violations!',
                'params' => [],
            ],
            [
                'isEnabled' => !empty($this->config['git_hooks']['twigCs']),
                'msg' => 'Checking twig code style with TWIGCS',
                'function' => 'twigCs',
                'errorMsg' => 'There are twig code style violations!',
                'params' => [],
            ],
        ];

        foreach ($toolsConfig as $tools) {
            if ($tools['isEnabled']) {
                $output->writeln(sprintf('<info>%s</info>', $tools['msg']));
                if (!call_user_func_array([$this, $tools['function']], \array_merge([$files], $tools['params']))) {
                    throw new Exception($tools['errorMsg']);
                }
            }
        }

        $output->writeln('<info>Good job!</info>');
    }

    private function extractCommitedFiles()
    {
        $output = array();

        exec('git rev-parse --verify HEAD 2> /dev/null', $output, $rc);
        exec("git diff-index --cached --name-status HEAD | egrep '^(A|M)' | awk '{print $2;}'", $output);

        return $output;
    }

    private function phpLint($files)
    {
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match(self::PHP_FILES_IN_SRC, $file)) {
                continue;
            }

            $process = new Process(['php', '-l', $file]);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getOutput())));

                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    private function codeStyle(array $files)
    {
        $succeed = true;

        foreach ($files as $file) {
            $srcFile = preg_match(self::PHP_FILES_IN_SRC, $file);

            if (!$srcFile) {
                continue;
            }

            $process = new Process(['php', 'vendor/bin/php-cs-fixer', '--dry-run', '-vvv', 'fix', $file]);
            $process->setWorkingDirectory(self::PROJECT_DIR);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getOutput())));

                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    private function codeStylePsr(array $files)
    {
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match(self::PHP_FILES_IN_SRC, $file)) {
                continue;
            }

            $process = new Process(array('php', 'vendor/bin/phpcs', $file));
            $process->setWorkingDirectory(self::PROJECT_DIR);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getOutput())));

                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    private function phPmd($files)
    {
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match(self::PHP_FILES_IN_SRC, $file)) {
                continue;
            }

            $process = new Process(['php', 'vendor/bin/phpmd', $file, 'text', 'PmdRules.xml']);
            $process->setWorkingDirectory(realpath(self::PROJECT_DIR));
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    private function twigCs($files)
    {
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match(self::TWIG_FILES, $file)) {
                continue;
            }

            $process = new Process(['vendor/bin/twigcs', $file]);
            $process->setWorkingDirectory(realpath(self::PROJECT_DIR));
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }
}
